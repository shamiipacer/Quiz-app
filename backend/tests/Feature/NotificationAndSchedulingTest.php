<?php

namespace Tests\Feature;

use App\Jobs\SendPush;
use App\Models\User;
use App\Services\QuizService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationAndSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_is_sent_only_for_opted_in_active_users(): void
    {
        config(['push.enabled' => true]);
        Http::fake(['exp.host/*' => Http::response(['data' => ['status' => 'ok']], 200)]);
        $u = User::factory()->create();
        DB::table('user_profiles')->insert(['user_id' => $u->id, 'optional_notifications' => false, 'encrypted_push_token' => Crypt::encryptString('ExpoPushToken[test_token]')]);
        (new SendPush($u->id, 'Practice', 'New questions'))->handle();
        Http::assertNothingSent();
        DB::table('user_profiles')->where('user_id', $u->id)->update(['optional_notifications' => true]);
        (new SendPush($u->id, 'Practice', 'New questions'))->handle();
        Http::assertSentCount(1);
        DB::table('users')->where('id', $u->id)->update(['status' => 'suspended']);
        (new SendPush($u->id, 'Practice', 'New questions'))->handle();
        Http::assertSentCount(1);
    }

    public function test_schedule_change_waits_until_active_assignments_expire(): void
    {
        $this->seed();
        $u = User::factory()->create();
        $quiz = app(QuizService::class);
        $before = $quiz->today($u);
        $u->forceFill(['pending_timezone' => 'Pacific/Auckland', 'pending_daily_schedule' => '09:00:00', 'schedule_effective_at' => now()->subMinute()])->save();
        $this->assertSame($before, $quiz->today($u));
        $this->assertSame('UTC', $u->fresh()->timezone);
        $this->travel(2)->days();
        $quiz->today($u);
        $this->assertSame('Pacific/Auckland', $u->fresh()->timezone);
        $this->travelBack();
    }

    public function test_dst_day_uses_local_midnight_boundary(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-08 06:00:00', 'UTC'));
        $this->seed();
        $u = User::factory()->create(['timezone' => 'America/New_York']);
        $q = app(QuizService::class)->today($u)[0];
        $this->assertSame('2026-03-08', $q['local_date']);
        $this->assertSame('2026-03-09 04:00:00', $q['expires_at']);
        $this->travelBack();
    }

    public function test_retention_cleanup_deletes_expired_private_records(): void
    {
        $u = User::factory()->create();
        $id = DB::table('user_consents')->insertGetId(['user_id' => $u->id, 'purpose' => 'location', 'policy_version' => 'draft-1', 'authorized_fields' => '["latitude","longitude"]', 'granted_at' => now()]);
        DB::table('location_submissions')->insert(['user_id' => $u->id, 'user_consent_id' => $id, 'encrypted_payload' => Crypt::encryptString('{"latitude":0,"longitude":0}'), 'collected_at' => now()->subDays(31), 'delete_after' => now()->subDay()]);
        $this->artisan('privacy:expire')->assertSuccessful();
        $this->assertDatabaseCount('location_submissions', 0);
    }
}
