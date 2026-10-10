<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\QuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class QuizFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_login_logout_and_verification_gate(): void
    {
        Notification::fake();
        $data = ['name' => 'Learner', 'email' => 'learner@example.com', 'password' => 'Educational123', 'password_confirmation' => 'Educational123', 'terms' => true, 'privacy' => true, 'timezone' => 'UTC'];
        $response = $this->postJson('/api/v1/register', $data)->assertCreated();
        $token = $response->json('token');
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $token]);
        $this->withToken($token)->getJson('/api/v1/profile')->assertOk()->assertJsonMissingPath('password');
        $this->withToken($token)->getJson('/api/v1/questions/today')->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/logout')->assertNoContent();
        $this->withToken($token)->getJson('/api/v1/profile')->assertUnauthorized();
        $this->postJson('/api/v1/login', ['email' => $data['email'], 'password' => $data['password']])->assertOk();
    }

    public function test_daily_set_is_stable_private_and_duplicate_rewards_are_rejected(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $quiz = app(QuizService::class);
        $questions = $quiz->today($user);
        $this->assertCount(20, $questions);
        $this->assertSame($questions, $quiz->today($user));
        $this->assertArrayNotHasKey('correct_answer', $questions[0]['question']);
        $a = DB::table('daily_question_assignments')->where('id', $questions[0]['id'])->first();
        $correct = json_decode($a->snapshot, true)['correct_answer'];
        $result = $quiz->answer($user, $a->id, $correct);
        $this->assertSame(10, $result['balance']);
        try {
            $quiz->answer($user, $a->id, $correct);
            $this->fail('Duplicate accepted');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame(1, DB::table('coin_transactions')->count());
    }

    public function test_foreign_assignments_and_foreign_options_are_rejected(): void
    {
        $this->seed();
        $u = User::factory()->create();
        $other = User::factory()->create();
        $q = app(QuizService::class);
        $a = $q->today($u)[0];
        try {
            $q->answer($other, $a['id'], '1');
            $this->fail('Foreign access');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        $this->expectException(ValidationException::class);
        $q->answer($u, $a['id'], '999999');
    }

    public function test_completion_bonus_is_awarded_once(): void
    {
        $this->seed();
        $u = User::factory()->create();
        $q = app(QuizService::class);
        foreach ($q->today($u) as $a) {
            $s = json_decode(DB::table('daily_question_assignments')->where('id', $a['id'])->value('snapshot'), true);
            $q->answer($u, $a['id'], $s['correct_answer']);
        }
        $this->assertEquals(220, DB::table('coin_transactions')->where('user_id', $u->id)->sum('amount'));
        $this->assertEquals(1, DB::table('coin_transactions')->where('type', 'completion')->count());
    }

    public function test_day_rollover_expires_previous_assignment(): void
    {
        $this->seed();
        $u = User::factory()->create(['timezone' => 'Pacific/Auckland']);
        $q = app(QuizService::class);
        $this->travelTo(now()->startOfDay());
        $first = $q->today($u);
        $this->travel(1)->days();
        $second = $q->today($u);
        $this->assertNotSame($first[0]['local_date'], $second[0]['local_date']);
        try {
            $q->answer($u, $first[0]['id'], (string) $first[0]['question']['options'][0]['id']);
            $this->fail('Expired accepted');
        } catch (HttpException $e) {
            $this->assertSame(410, $e->getStatusCode());
        }
        $this->travelBack();
    }
}
