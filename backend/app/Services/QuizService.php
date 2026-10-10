<?php

namespace App\Services;

use App\Jobs\SendPush;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuizService
{
    public function today(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->schedule_effective_at && CarbonImmutable::parse($user->schedule_effective_at)->lessThanOrEqualTo(now())) {
                $activeUntil = DB::table('daily_question_assignments')->where('user_id', $user->id)->where('expires_at', '>', now())->max('expires_at');
                if ($activeUntil) {
                    $user->forceFill(['schedule_effective_at' => $activeUntil])->save();
                } else {
                    $user->forceFill(['timezone' => $user->pending_timezone, 'daily_schedule' => $user->pending_daily_schedule, 'pending_timezone' => null, 'pending_daily_schedule' => null, 'schedule_effective_at' => null])->save();
                }
            }
            $local = CarbonImmutable::now($user->timezone);
            $start = $local->startOfDay()->setTimeFromTimeString($user->daily_schedule);
            if ($local->lessThan($start)) {
                $start = $start->subDay();
            }
            $date = $start->toDateString();
            $progress = DB::table('user_daily_progress')->where('user_id', $user->id)->where('local_date', $date)->first();
            if (! $progress) {
                $count = 0;
                foreach (DB::table('categories')->where('active', true)->orderBy('display_order')->get() as $category) {
                    $questions = DB::table('questions')->where('category_id', $category->id)->where('active', true)
                        ->where('publication_at', '<=', now())->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                        ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->orderBy('id')->get();
                    $selected = $questions->sortBy(fn ($q) => hash('sha256', "{$user->id}:{$date}:{$q->id}"))->take($category->daily_limit);
                    foreach ($selected as $q) {
                        $options = DB::table('question_options')->where('question_id', $q->id)->orderBy('display_order')->get();
                        $snapshot = ['text' => $q->text, 'category_id' => $q->category_id, 'type' => $q->type, 'difficulty' => $q->difficulty,
                            'options' => $options->map(fn ($o) => ['id' => $o->id, 'text' => $o->text])->all(),
                            'correct_answer' => $q->type === 'single_answer' ? $q->correct_answer : (string) $options->firstWhere('is_correct', 1)?->id,
                            'explanation' => $q->explanation, 'coin_reward' => $q->coin_reward ?? $category->default_reward, 'image_path' => $q->image_path];
                        DB::table('daily_question_assignments')->insert(['user_id' => $user->id, 'question_id' => $q->id, 'local_date' => $date,
                            'timezone' => $user->timezone, 'snapshot' => json_encode($snapshot), 'expires_at' => $start->addDay()->utc(), 'created_at' => now(), 'updated_at' => now()]);
                        $count++;
                    }
                }
                DB::table('user_daily_progress')->insert(['user_id' => $user->id, 'local_date' => $date, 'assigned' => $count, 'completed' => 0, 'correct' => 0, 'bonus_awarded' => false, 'created_at' => now(), 'updated_at' => now()]);
                if ($count > 0) {
                    DB::table('notifications')->insert(['id' => Str::uuid(), 'type' => 'daily_questions', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'data' => json_encode(['title' => 'Your daily questions are ready', 'body' => "{$count} questions are available for today's practice."]), 'created_at' => now(), 'updated_at' => now()]);
                    SendPush::dispatch($user->id, 'Your daily questions are ready', "{$count} questions are available.")->afterCommit();
                }

            }

            return DB::table('daily_question_assignments')->where('user_id', $user->id)->where('local_date', $date)->orderBy('id')->get()->map(fn ($a) => $this->present($a))->all();
        });
    }

    public function present(object $a): array
    {
        $snapshot = json_decode($a->snapshot, true);
        unset($snapshot['correct_answer'],$snapshot['explanation']);
        $answer = DB::table('user_answers')->where('assignment_id', $a->id)->first();
        if ($answer) {
            $snapshot['explanation'] = json_decode($a->snapshot, true)['explanation'];
        }

        return ['id' => $a->id, 'question_id' => $a->question_id, 'local_date' => $a->local_date, 'expires_at' => $a->expires_at, 'question' => $snapshot, 'result' => $answer ? ['answer' => $answer->answer, 'correct' => (bool) $answer->correct, 'coins_awarded' => $answer->coins_awarded] : null];
    }

    public function answer(User $user, int $id, string $answer): array
    {
        return DB::transaction(function () use ($user, $id, $answer) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $a = DB::table('daily_question_assignments')->where('id', $id)->where('user_id', $user->id)->lockForUpdate()->first();
            abort_unless($a, 404);
            abort_if(DB::table('user_answers')->where('assignment_id', $id)->exists(), 409, 'Already answered.');
            abort_if(CarbonImmutable::parse($a->expires_at)->lessThanOrEqualTo(now()), 410, 'Assignment expired.');
            $s = json_decode($a->snapshot, true);
            if ($s['type'] !== 'single_answer' && ! in_array($answer, array_map(fn ($o) => (string) $o['id'], $s['options']), true)) {
                throw ValidationException::withMessages(['answer' => 'Choose an option belonging to this question.']);
            }
            $normalize = fn ($value) => mb_strtolower(trim($value));
            $correct = $s['type'] === 'single_answer' ? $normalize($answer) === $normalize($s['correct_answer']) : $answer === $s['correct_answer'];
            $rewardReference = "question:{$user->id}:{$a->question_id}";
            $previouslyRewarded = DB::table('coin_transactions')->where('reference', $rewardReference)->exists();
            $coins = $correct && ! $previouslyRewarded ? $s['coin_reward'] : 0;
            $answerId = DB::table('user_answers')->insertGetId(['assignment_id' => $id, 'answer' => $answer, 'correct' => $correct, 'coins_awarded' => $coins, 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            if ($coins > 0) {
                DB::table('coin_transactions')->insert(['user_id' => $user->id, 'user_answer_id' => $answerId, 'reference' => $rewardReference, 'amount' => $coins, 'type' => 'answer', 'reason' => 'Correct educational answer', 'created_at' => now()]);
            }
            $p = DB::table('user_daily_progress')->where('user_id', $user->id)->where('local_date', $a->local_date)->lockForUpdate()->first();
            DB::table('user_daily_progress')->where('id', $p->id)->update(['completed' => $p->completed + 1, 'correct' => $p->correct + (int) $correct, 'updated_at' => now()]);
            if ($p->assigned > 0 && $p->completed + 1 === $p->assigned && ! $p->bonus_awarded) {
                $bonus = DB::table('rewards')->where('key', 'daily_completion')->where('active', true)->value('coins') ?? 0;
                if ($bonus > 0) {
                    DB::table('coin_transactions')->insert(['user_id' => $user->id, 'reference' => "completion:{$user->id}:{$a->local_date}", 'amount' => $bonus, 'type' => 'completion', 'reason' => 'Daily completion bonus', 'created_at' => now()]);
                }
                DB::table('user_daily_progress')->where('id', $p->id)->update(['bonus_awarded' => true]);
            }
            if ($p->assigned > 0 && $p->completed + 1 === $p->assigned) {
                DB::table('notifications')->insert(['id' => Str::uuid(), 'type' => 'daily_completion', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'data' => json_encode(['title' => 'Daily practice complete!', 'body' => 'You completed today’s learning. Come back tomorrow.']), 'created_at' => now(), 'updated_at' => now()]);
                SendPush::dispatch($user->id, 'Daily practice complete!', 'You completed today’s learning.')->afterCommit();
            }

            return ['correct' => $correct, 'coins_awarded' => $coins, 'explanation' => $s['explanation'], 'balance' => (int) DB::table('coin_transactions')->where('user_id', $user->id)->sum('amount')];
        }, 3);
    }
}
