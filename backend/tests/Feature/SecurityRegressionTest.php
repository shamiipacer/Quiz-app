<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\QuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_signed_verification_cannot_verify_account(): void
    {
        $u = User::factory()->unverified()->create();
        $this->get('/email/verify/'.$u->id.'/'.sha1($u->email))->assertForbidden();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), ['id' => $u->id, 'hash' => sha1($u->email)]);
        $this->get($url)->assertOk();
        $this->assertNotNull($u->fresh()->email_verified_at);
    }

    public function test_incorrect_answers_earn_nothing_and_snapshot_grading_survives_edits(): void
    {
        $this->seed();
        $u = User::factory()->create();
        $quiz = app(QuizService::class);
        $all = $quiz->today($u);
        $a = $all[0];
        $snapshot = json_decode(DB::table('daily_question_assignments')->where('id', $a['id'])->value('snapshot'), true);
        DB::table('questions')->where('id', $a['question_id'])->update(['coin_reward' => 999]);
        $wrong = collect($snapshot['options'])->first(fn ($o) => (string) $o['id'] !== $snapshot['correct_answer']);
        $result = $quiz->answer($u, $a['id'], (string) $wrong['id']);
        $this->assertFalse($result['correct']);
        $this->assertSame(0, $result['balance']);
        $next = $all[1];
        $original = json_decode(DB::table('daily_question_assignments')->where('id', $next['id'])->value('snapshot'), true);
        DB::table('question_options')->where('question_id', $next['question_id'])->update(['is_correct' => false]);
        $this->assertTrue($quiz->answer($u, $next['id'], $original['correct_answer'])['correct']);
    }

    public function test_same_question_does_not_award_coins_again_on_another_day(): void
    {
        $this->seed();
        $u = User::factory()->create();
        $q = app(QuizService::class);
        $a = $q->today($u)[0];
        $s = json_decode(DB::table('daily_question_assignments')->where('id', $a['id'])->value('snapshot'), true);
        $q->answer($u, $a['id'], $s['correct_answer']);
        $this->travel(1)->days();
        $all = $q->today($u);
        $next = collect($all)->firstWhere('question_id', $a['question_id']);
        $result = $q->answer($u, $next['id'], $s['correct_answer']);
        $this->assertSame(0, $result['coins_awarded']);
        $this->assertSame(10, $result['balance']);
        $this->travelBack();
    }

    public function test_expired_token_is_rejected(): void
    {
        $u = User::factory()->create();
        $token = bin2hex(random_bytes(32));
        DB::table('api_tokens')->insert(['user_id' => $u->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->subSecond(), 'created_at' => now(), 'updated_at' => now()]);
        $this->withToken($token)->getJson('/api/v1/profile')->assertUnauthorized();
    }
}
