<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class SharingController extends Controller
{
    public function settings()
    {
        return ['enabled' => (bool) config('sharing.enabled'), 'purpose' => config('sharing.purpose'), 'policy_version' => config('sharing.policy_version'), 'retention_days' => config('sharing.retention_days')];
    }

    public function consent(Request $r)
    {
        abort_unless(config('sharing.enabled') && config('sharing.purpose'), 503, 'Optional sharing is not configured.');
        $v = $r->validate(['purpose' => 'required|in:contacts,location', 'policy_version' => 'required|in:'.config('sharing.policy_version'), 'authorized_fields' => 'required|array|min:1|max:2', 'authorized_fields.*' => 'required|string|distinct', 'granted' => 'required|accepted']);
        $allowed = $v['purpose'] === 'contacts' ? ['name', 'phone'] : ['latitude', 'longitude'];
        abort_unless(empty(array_diff($v['authorized_fields'], $allowed)), 422);
        $id = DB::transaction(function () use ($r, $v) {
            DB::table('users')->where('id', $r->user()->id)->lockForUpdate()->first();
            DB::table('user_consents')->where('user_id', $r->user()->id)->where('purpose', $v['purpose'])->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);

            return DB::table('user_consents')->insertGetId(['user_id' => $r->user()->id, 'purpose' => $v['purpose'], 'policy_version' => $v['policy_version'], 'authorized_fields' => json_encode($v['authorized_fields']), 'granted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['id' => $id], 201);
    }

    private function activeConsent(Request $r, string $purpose, array $fields)
    {
        abort_unless(config('sharing.enabled') && config('sharing.purpose'), 503);
        $consent = DB::table('user_consents')->where('user_id', $r->user()->id)->where('purpose', $purpose)->whereNull('revoked_at')->where('policy_version', config('sharing.policy_version'))->orderByDesc('id')->first();
        abort_unless($consent && empty(array_diff($fields, json_decode($consent->authorized_fields, true))), 403, 'Explicit consent for these fields is required.');

        return $consent;
    }

    public function contacts(Request $r)
    {
        $v = $r->validate(['contacts' => 'required|array|min:1|max:20', 'contacts.*' => 'required|array:name,phone', 'contacts.*.name' => 'required|string|max:120', 'contacts.*.phone' => 'required|string|max:30']);
        DB::transaction(function () use ($r, $v) {
            DB::table('users')->where('id', $r->user()->id)->lockForUpdate()->first();
            $c = $this->activeConsent($r, 'contacts', ['name', 'phone']);
            $id = DB::table('contact_submissions')->insertGetId(['user_id' => $r->user()->id, 'user_consent_id' => $c->id, 'delete_after' => now()->addDays(config('sharing.retention_days')), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($v['contacts'] as $item) {
                DB::table('contact_submission_items')->insert(['contact_submission_id' => $id, 'encrypted_payload' => Crypt::encryptString(json_encode($item)), 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return response()->json(['message' => 'Selected contact saved.'], 201);
    }

    public function location(Request $r)
    {
        $v = $r->validate(['latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180']);
        DB::transaction(function () use ($r, $v) {
            DB::table('users')->where('id', $r->user()->id)->lockForUpdate()->first();
            $c = $this->activeConsent($r, 'location', ['latitude', 'longitude']);
            $payload = ['latitude' => round($v['latitude'], 2), 'longitude' => round($v['longitude'], 2)];
            DB::table('location_submissions')->insert(['user_id' => $r->user()->id, 'user_consent_id' => $c->id, 'encrypted_payload' => Crypt::encryptString(json_encode($payload)), 'collected_at' => now(), 'delete_after' => now()->addDays(config('sharing.retention_days')), 'created_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['message' => 'Approximate location saved.'], 201);
    }

    public function delete(Request $r, string $purpose)
    {
        abort_unless(in_array($purpose, ['contacts', 'location']), 404);
        DB::transaction(function () use ($r, $purpose) {
            DB::table('users')->where('id', $r->user()->id)->lockForUpdate()->first();
            DB::table($purpose === 'contacts' ? 'contact_submissions' : 'location_submissions')->where('user_id', $r->user()->id)->delete();
            DB::table('user_consents')->where('user_id', $r->user()->id)->where('purpose', $purpose)->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
        });

        return response()->noContent();
    }
}
