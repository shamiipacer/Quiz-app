<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\User;
use App\Services\QuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): void
    {
        $this->seed();
        $a = AdminUser::create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'EditorPassword123', 'admin_role_id' => DB::table('admin_roles')->where('name', 'Question Manager')->value('id')]);
        $this->actingAs($a, 'admin');
    }

    public function test_question_creation_revision_and_manual_reassignment(): void
    {
        $this->manager();
        $u = User::factory()->create();
        $a = app(QuizService::class)->today($u)[0];
        $category = $a['question']['category_id'];
        $data = ['category_id' => $category, 'text' => 'Is learning valuable?', 'type' => 'true_false', 'difficulty' => 'easy', 'options' => "True\nFalse", 'correct_answer' => 'True', 'explanation' => 'Practice builds knowledge.', 'publication_at' => now()->subMinute()->toDateTimeString(), 'active' => 1];
        $this->post('/admin/questions', $data)->assertRedirect();
        $id = DB::table('questions')->where('text', $data['text'])->value('id');
        $this->assertDatabaseHas('question_revisions', ['question_id' => $id]);
        $this->post('/admin/schedule/reassign', ['assignment_id' => $a['id'], 'question_id' => $id, 'reason' => 'Replace introductory content'])->assertRedirect();
        $this->assertDatabaseHas('daily_question_assignments', ['id' => $a['id'], 'question_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'assignment.replaced']);
        $this->get('/admin/questions/'.$id.'/history')->assertOk();
    }

    public function test_csv_import_rolls_back_all_rows_if_any_row_is_invalid(): void
    {
        $this->manager();
        $category = DB::table('categories')->value('id');
        $before = DB::table('questions')->count();
        $header = "category_id,text,type,difficulty,options,correct_answer,explanation,coin_reward,publication_at,active\n";
        $valid = "$category,Imported quiz,multiple_choice,easy,One|Two,One,Sample,10,2026-01-01 00:00:00,1\n";
        $bad = "$category,Invalid quiz,multiple_choice,easy,One|Two,Missing,Sample,10,2026-01-01 00:00:00,1\n";
        $this->post('/admin/questions/import/csv', ['csv' => UploadedFile::fake()->createWithContent('questions.csv', $header.$valid.$bad)])->assertStatus(422);
        $this->assertDatabaseCount('questions', $before);
        $this->post('/admin/questions/import/csv', ['csv' => UploadedFile::fake()->createWithContent('questions.csv', $header.$valid)])->assertRedirect();
        $this->assertDatabaseCount('questions', $before + 1);
    }

    public function test_reseeding_preserves_configured_rewards_and_categories(): void
    {
        $this->seed();
        $id = DB::table('categories')->where('name', 'English')->value('id');
        DB::table('categories')->where('id', $id)->update(['active' => false, 'default_reward' => 25]);
        DB::table('rewards')->where('key', 'daily_completion')->update(['coins' => 99]);
        $this->seed();
        $this->assertDatabaseHas('categories', ['id' => $id, 'active' => false, 'default_reward' => 25]);
        $this->assertDatabaseHas('rewards', ['key' => 'daily_completion', 'coins' => 99]);
        $this->assertDatabaseCount('questions', 20);
    }

    public function test_malformed_registration_and_question_ids_are_validated(): void
    {
        $this->postJson('/api/v1/register', ['email' => ['bad']])->assertUnprocessable();
        $this->getJson('/api/v1/questions/not-a-number')->assertNotFound();
    }
    public function test_reports_keep_assignment_category_after_question_is_moved(): void
    {
        $this->manager();
        $user = User::factory()->create();
        $quiz = app(QuizService::class);
        $assignment = $quiz->today($user)[0];
        $quiz->answer($user, $assignment['id'], (string) $assignment['question']['options'][0]['id']);
        $physics = DB::table('categories')->where('name', 'Physics')->value('id');
        DB::table('questions')->where('id', $assignment['question_id'])->update(['category_id' => $physics]);
        $analyst = AdminUser::create(['name' => 'Analyst', 'email' => 'analyst@example.com', 'password' => 'AnalystPassword123', 'admin_role_id' => DB::table('admin_roles')->where('name', 'Read-only Analyst')->value('id')]);
        $this->actingAs($analyst, 'admin');
        $this->get('/admin/reports?from='.today()->toDateString().'&to='.today()->toDateString())->assertOk()->assertSee('English')->assertDontSee('Physics');
    }

}
