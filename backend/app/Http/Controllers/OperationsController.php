<?php

namespace App\Http\Controllers;

use App\Jobs\SendPush;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OperationsController extends Controller
{
    private function audit(string $action, string $type, int $id): void
    {
        DB::table('audit_logs')->insert(['admin_user_id' => Auth::guard('admin')->id(), 'action' => $action, 'resource_type' => $type, 'resource_id' => $id, 'created_at' => now()]);
    }

    public function user(int $id)
    {
        $user = User::findOrFail($id);
        $answers = DB::table('user_answers')->join('daily_question_assignments', 'assignment_id', '=', 'daily_question_assignments.id')->where('user_id', $id)->select('user_answers.*', 'daily_question_assignments.question_id', 'local_date')->orderByDesc('user_answers.id')->paginate(20);
        $ledger = DB::table('coin_transactions')->where('user_id', $id)->orderByDesc('id')->limit(20)->get();
        $consents = DB::table('user_consents')->where('user_id', $id)->select('purpose', 'granted_at', 'revoked_at', 'policy_version')->orderByDesc('id')->get();

        return view('admin.user', compact('user', 'answers', 'ledger', 'consents'));
    }

    public function privacy()
    {
        $this->audit('privacy.list', 'privacy', 0);

        return view('admin.privacy', ['contacts' => DB::table('contact_submissions')->orderByDesc('id')->paginate(20, ['*'], 'contacts_page'),
            'locations' => DB::table('location_submissions')->orderByDesc('id')->paginate(20, ['*'], 'locations_page'),
            'requests' => DB::table('deletion_requests')->where('status', 'pending')->paginate(20, ['*'], 'requests_page')]);
    }

    public function sensitive(string $type, int $id)
    {
        abort_unless(in_array($type, ['contacts', 'location']), 404);
        $row = DB::table($type === 'contacts' ? 'contact_submissions' : 'location_submissions')->where('id', $id)->first();
        abort_unless($row, 404);
        $consent = DB::table('user_consents')->where('id', $row->user_consent_id)->whereNull('revoked_at')->first();
        abort_unless($consent && $row->delete_after > now()->toDateTimeString(), 410);
        $this->audit('privacy.view', $type, $id);
        $payload = $type === 'contacts' ? DB::table('contact_submission_items')->where('contact_submission_id', $id)->pluck('encrypted_payload')->map(fn ($p) => json_decode(Crypt::decryptString($p), true)) : json_decode(Crypt::decryptString($row->encrypted_payload), true);

        return response()->view('admin.sensitive', ['payload' => $payload, 'consent' => $consent])->header('Cache-Control', 'no-store');
    }

    public function deleteSensitive(string $type, int $id)
    {
        abort_unless(in_array($type, ['contacts', 'location']), 404);
        DB::transaction(function () use ($type, $id) {
            DB::table($type === 'contacts' ? 'contact_submissions' : 'location_submissions')->where('id', $id)->delete();
            $this->audit('privacy.delete', $type, $id);
        });

        return back();
    }

    public function processDeletion(int $id)
    {
        DB::transaction(function () use ($id) {
            $request = DB::table('deletion_requests')->where('id', $id)->where('status', 'pending')->lockForUpdate()->first();
            abort_unless($request, 404);
            $user = User::whereKey($request->user_id)->lockForUpdate()->firstOrFail();
            foreach (['contact_submissions', 'location_submissions', 'user_profiles', 'api_tokens', 'login_history'] as $table) {
                DB::table($table)->where('user_id', $user->id)->delete();
            }
            DB::table('user_consents')->where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $user->id)->delete();
            $user->forceFill(['name' => 'Deleted account', 'email' => Str::uuid().'@deleted.invalid', 'password' => Str::random(80), 'status' => 'deleted', 'remember_token' => null, 'timezone' => 'UTC'])->save();
            DB::table('deletion_requests')->where('id', $id)->update(['status' => 'processed', 'processed_at' => now(), 'updated_at' => now()]);
            $this->audit('account.anonymized', 'user', $user->id);
        });

        return back()->with('message', 'Personal account data removed; anonymized quiz ledger retained.');
    }

    public function reports(Request $r)
    {
        $v = $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $from = $v['from'] ?? today()->subDays(30)->toDateString();
        $to = $v['to'] ?? today()->toDateString();
        $rows = DB::table('user_answers')->join('daily_question_assignments', 'assignment_id', '=', 'daily_question_assignments.id')->join('categories', 'categories.id', '=', DB::raw("JSON_EXTRACT(daily_question_assignments.snapshot, '$.category_id')"))->whereBetween('submitted_at', [$from.' 00:00:00', $to.' 23:59:59'])->selectRaw('categories.name, COUNT(*) as answered, SUM(correct) as correct, SUM(coins_awarded) as coins')->groupBy('categories.id', 'categories.name')->get();
        if ($r->boolean('export')) {
            $this->audit('report.export', 'report', 0);

            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Category', 'Answered', 'Correct', 'Coins'], ',', '"', '');
                foreach ($rows as $row) {
                    $name = preg_match('/^[=+@\-\t\r]/', $row->name) ? "'".$row->name : $row->name;
                    fputcsv($out, [$name, $row->answered, $row->correct, $row->coins], ',', '"', '');
                } fclose($out);
            }, 'quiz-report.csv', ['Content-Type' => 'text/csv', 'Cache-Control' => 'no-store']);
        }
        $summary = ['Registrations' => DB::table('users')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->count(),
            'Daily active users' => DB::table('login_history')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->distinct()->count('user_id'),
            'Coins issued' => DB::table('coin_transactions')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->where('amount', '>', 0)->sum('amount'),
            'Consent grants' => DB::table('user_consents')->whereBetween('granted_at', [$from.' 00:00:00', $to.' 23:59:59'])->count(),
            'Deletion requests' => DB::table('deletion_requests')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->count(),
            'Large reward entries (review, not proof of abuse)' => DB::table('coin_transactions')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->where('amount', '>', 1000)->count()];

        return view('admin.reports', compact('rows', 'from', 'to', 'summary'));
    }

    public function import(Request $r)
    {
        $r->validate(['csv' => 'required|file|mimes:csv,txt|max:2048']);
        $handle = fopen($r->file('csv')->getRealPath(), 'r');
        $header = fgetcsv($handle, 0, ',', '"', '');
        abort_unless($header === ['category_id', 'text', 'type', 'difficulty', 'options', 'correct_answer', 'explanation', 'coin_reward', 'publication_at', 'active'], 422, 'Unexpected CSV header. See API documentation.');
        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            abort_unless(count($row) === count($header) && count($rows) < 500, 422, 'Invalid row or more than 500 rows.');
            $data = array_combine($header, $row);
            $data['options'] = str_replace('|', "\n", $data['options']);
            if ($data['coin_reward'] === '') {
                $data['coin_reward'] = null;
            } $rows[] = $data;
        }
        fclose($handle);
        abort_unless(count($rows) > 0, 422, 'Empty CSV.');
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                app(AdminController::class)->saveQuestion(Request::create('/admin/questions', 'POST', $row));
            }
        });

        return back()->with('message', count($rows).' questions imported.');
    }

    public function image(Request $r, int $id)
    {
        $r->validate(['image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048|dimensions:max_width=4096,max_height=4096']);
        abort_unless(DB::table('questions')->where('id', $id)->exists(), 404);
        $path = $r->file('image')->store('question-images', 'public');
        DB::transaction(function () use ($id, $path) {
            DB::table('questions')->where('id', $id)->update(['image_path' => $path, 'updated_at' => now()]);
            $this->audit('question.image', 'question', $id);
        });

        return back();
    }

    public function reassign(Request $r)
    {
        $v = $r->validate(['assignment_id' => 'required|integer', 'question_id' => 'required|integer|exists:questions,id', 'reason' => 'required|string|min:5|max:500']);
        DB::transaction(function () use ($v) {
            $pre = DB::table('daily_question_assignments')->where('id', $v['assignment_id'])->first();
            abort_unless($pre, 404);
            DB::table('users')->where('id', $pre->user_id)->lockForUpdate()->first();
            $a = DB::table('daily_question_assignments')->where('id', $pre->id)->lockForUpdate()->first();
            abort_if(DB::table('user_answers')->where('assignment_id', $a->id)->exists() || $a->expires_at <= now()->toDateTimeString(), 409, 'Only unanswered current assignments can be replaced.');
            $q = DB::table('questions')->where('id', $v['question_id'])->where('active', true)->where('publication_at', '<=', now())->where(fn ($b) => $b->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($b) => $b->whereNull('ends_at')->orWhere('ends_at', '>', now()))->first();
            abort_unless($q, 422);
            $category = DB::table('categories')->where('id', $q->category_id)->where('active', true)->first();
            abort_unless($category && $q->category_id === json_decode($a->snapshot, true)['category_id'], 422, 'Replacement must belong to the same active category.');
            abort_if(DB::table('daily_question_assignments')->where('user_id', $a->user_id)->where('local_date', $a->local_date)->where('question_id', $q->id)->exists(), 409, 'Question already assigned.');
            $options = DB::table('question_options')->where('question_id', $q->id)->orderBy('display_order')->get();
            $snapshot = ['text' => $q->text, 'category_id' => $q->category_id, 'type' => $q->type, 'difficulty' => $q->difficulty, 'options' => $options->map(fn ($o) => ['id' => $o->id, 'text' => $o->text])->all(), 'correct_answer' => $q->type === 'single_answer' ? $q->correct_answer : (string) $options->firstWhere('is_correct', 1)?->id, 'explanation' => $q->explanation, 'coin_reward' => $q->coin_reward ?? $category->default_reward, 'image_path' => $q->image_path];
            DB::table('daily_question_assignments')->where('id', $a->id)->update(['question_id' => $q->id, 'snapshot' => json_encode($snapshot), 'updated_at' => now()]);
            DB::table('audit_logs')->insert(['admin_user_id' => Auth::guard('admin')->id(), 'user_id' => $a->user_id, 'action' => 'assignment.replaced', 'resource_type' => 'assignment', 'resource_id' => $a->id, 'metadata' => json_encode(['previous_question' => $a->question_id, 'question' => $q->id, 'reason' => $v['reason']]), 'created_at' => now()]);
        });

        return back()->with('message', 'Unanswered assignment replaced and audited.');
    }

    public function history(int $id)
    {
        return view('admin.question-history', ['items' => DB::table('question_revisions')->where('question_id', $id)->orderByDesc('id')->paginate(20)]);
    }

    public function notifications()
    {
        return view('admin.notifications');
    }

    public function announce(Request $r)
    {
        $v = $r->validate(['title' => 'required|string|max:120', 'body' => 'required|string|max:2000']);
        User::where('status', 'active')->chunkById(100, function ($users) use ($v) {
            foreach ($users as $user) {
                DB::table('notifications')->insert(['id' => Str::uuid(), 'type' => 'announcement', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'data' => json_encode($v), 'created_at' => now(), 'updated_at' => now()]);
                SendPush::dispatch($user->id, $v['title'], $v['body']);
            }
        });
        $this->audit('notification.announced', 'notification', 0);

        return back()->with('message', 'In-app announcement sent.');
    }
}
