<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SharingController;
use App\Http\Middleware\ApiToken;
use App\Models\User;
use App\Services\QuizService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::pattern('id', '[0-9]+');

Route::prefix('v1')->middleware('throttle:60,1,api:')->group(function () {
    foreach (['register', 'login', 'forgot', 'reset'] as $action) {
        $path = match ($action) {
            'forgot' => 'password/forgot','reset' => 'password/reset',default => $action
        };
        Route::post($path, [AuthController::class, $action])->middleware('throttle:5,1,authentication:');
    }
    Route::middleware(ApiToken::class)->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('profile', fn (Request $r) => $r->user());
        Route::patch('profile', function (Request $r) {
            $v = $r->validate(['name' => 'sometimes|required|string|max:120', 'timezone' => 'sometimes|required|timezone', 'daily_schedule' => 'sometimes|required|date_format:H:i']);
            DB::transaction(function () use ($r, $v) {
                $user = User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
                if (isset($v['name'])) {
                    $user->name = $v['name'];
                }
                if (isset($v['timezone']) || isset($v['daily_schedule'])) {
                    abort_if($user->schedule_effective_at, 409, 'A schedule change is already pending.');
                    $user->pending_timezone = $v['timezone'] ?? $user->timezone;
                    $user->pending_daily_schedule = isset($v['daily_schedule']) ? $v['daily_schedule'].':00' : $user->daily_schedule;
                    $user->schedule_effective_at = now()->addDay();
                    DB::table('audit_logs')->insert(['user_id' => $user->id, 'action' => 'schedule.change_requested', 'created_at' => now()]);
                }
                $user->save();
            });

            return $r->user()->fresh();
        });
        Route::post('account/deletion-request', function (Request $r) {
            DB::table('deletion_requests')->insert(['user_id' => $r->user()->id, 'scope' => 'account', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

            return response()->json(['message' => 'Deletion requested.'], 202);
        });
        Route::get('categories', fn () => DB::table('categories')->where('active', true)->orderBy('display_order')->get());
        Route::get('coins/balance', fn (Request $r) => ['balance' => (int) DB::table('coin_transactions')->where('user_id', $r->user()->id)->sum('amount')]);
        Route::get('coins/transactions', fn (Request $r) => DB::table('coin_transactions')->where('user_id', $r->user()->id)->orderByDesc('id')->paginate(30));
        Route::get('questions/today', function (Request $r, QuizService $quiz) {
            abort_unless($r->user()->hasVerifiedEmail(), 403, 'Verify your email first.');

            return ['data' => $quiz->today($r->user())];
        });
        Route::get('questions/{id}', function (Request $r, QuizService $quiz, int $id) {
            $a = DB::table('daily_question_assignments')->where('id', $id)->where('user_id', $r->user()->id)->first();
            abort_unless($a, 404);

            return $quiz->present($a);
        });
        Route::post('questions/{id}/answer', function (Request $r, QuizService $quiz, int $id) {
            abort_unless($r->user()->hasVerifiedEmail(), 403, 'Verify your email first.');
            $v = $r->validate(['answer' => 'required|string|max:500']);

            return $quiz->answer($r->user(), $id, $v['answer']);
        });
    });
});
Route::prefix('v1')->middleware([ApiToken::class, 'throttle:30,1,sharing:'])->group(function () {
    Route::get('sharing/settings', [SharingController::class, 'settings']);
    Route::post('consents', [SharingController::class, 'consent']);
    Route::post('contacts/share', [SharingController::class, 'contacts']);
    Route::post('location/share', [SharingController::class, 'location']);
    Route::delete('{purpose}', [SharingController::class, 'delete'])->whereIn('purpose', ['contacts', 'location']);
});
Route::get('v1/notifications', function (Request $r) {
    return DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $r->user()->id)->orderByDesc('created_at')->paginate(30);
})->middleware([ApiToken::class, 'throttle:60,1,api:']);

Route::post('v1/email/verification-notification', function (Request $r) {
    if (! $r->user()->hasVerifiedEmail()) {
        $r->user()->sendEmailVerificationNotification();
    }

    return ['message' => 'Verification email requested.'];
})->middleware([ApiToken::class, 'throttle:3,1,verification:']);

Route::get('v1/answers', function (Request $r, QuizService $quiz) {
    $items = DB::table('daily_question_assignments')->where('user_id', $r->user()->id)->whereExists(fn ($q) => $q->selectRaw('1')->from('user_answers')->whereColumn('assignment_id', 'daily_question_assignments.id'))->orderByDesc('id')->paginate(30);
    $items->through(fn ($a) => $quiz->present($a));

    return $items;
})->middleware([ApiToken::class, 'throttle:60,1,api:']);
Route::middleware([ApiToken::class, 'throttle:30,1,preferences:'])->prefix('v1')->group(function () {
    Route::get('notifications/settings', fn (Request $r) => ['push_enabled' => (bool) config('push.enabled'), 'project_id' => config('push.project_id'), 'enabled' => (bool) DB::table('user_profiles')->where('user_id', $r->user()->id)->value('optional_notifications')]);
    Route::patch('notifications/preferences', function (Request $r) {
        $v = $r->validate(['enabled' => 'required|boolean', 'push_token' => 'nullable|string|max:255|regex:/^(ExponentPushToken|ExpoPushToken)\[[A-Za-z0-9_-]+\]$/']);
        abort_if($v['enabled'] && (! config('push.enabled') || empty($v['push_token'])), 422, 'Push delivery is not configured or token is missing.');
        DB::table('user_profiles')->updateOrInsert(['user_id' => $r->user()->id], ['optional_notifications' => $v['enabled'], 'encrypted_push_token' => ! empty($v['push_token']) ? Crypt::encryptString($v['push_token']) : null, 'updated_at' => now()]);

        return response()->noContent();
    });
    Route::patch('notifications/{id}/read', function (Request $r, string $id) {
        $query = DB::table('notifications')->where('id', $id)->where('notifiable_type', User::class)->where('notifiable_id', $r->user()->id);
        abort_unless((clone $query)->exists(), 404);
        $query->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->noContent();
    });
});
