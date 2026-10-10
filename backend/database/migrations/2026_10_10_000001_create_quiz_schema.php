<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('status')->default('active')->index();
            $t->string('timezone')->default('UTC');
            $t->time('daily_schedule')->default('00:00:00');
            $t->timestamp('terms_accepted_at')->nullable();
            $t->timestamp('privacy_accepted_at')->nullable();
        });
        Schema::create('user_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('phone', 30)->nullable();
            $t->string('avatar_path')->nullable();
            $t->boolean('optional_notifications')->default(false);
            $t->timestamps();
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->text('description')->nullable();
            $t->string('icon')->default('book');
            $t->unsignedInteger('display_order')->default(0);
            $t->boolean('active')->default(true)->index();
            $t->unsignedSmallInteger('daily_limit')->default(2);
            $t->unsignedInteger('default_reward')->default(10);
            $t->timestamps();
        });
        Schema::create('questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->text('text');
            $t->string('type')->default('multiple_choice');
            $t->string('difficulty')->default('easy');
            $t->text('correct_answer')->nullable();
            $t->text('explanation')->nullable();
            $t->unsignedInteger('coin_reward')->nullable();
            $t->string('image_path')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamp('publication_at')->index();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamps();
            $t->index(['category_id', 'active', 'publication_at']);
        });
        Schema::create('question_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('question_id')->constrained()->cascadeOnDelete();
            $t->text('text');
            $t->boolean('is_correct')->default(false);
            $t->unsignedSmallInteger('display_order')->default(0);
            $t->timestamps();
        });
        Schema::create('daily_question_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('question_id')->constrained()->restrictOnDelete();
            $t->date('local_date');
            $t->string('timezone');
            $t->json('snapshot');
            $t->timestamp('expires_at')->index();
            $t->timestamps();
            $t->unique(['user_id', 'local_date', 'question_id'], 'assignment_unique');
        });
        Schema::create('user_answers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->unique()->constrained('daily_question_assignments')->cascadeOnDelete();
            $t->text('answer');
            $t->boolean('correct');
            $t->unsignedInteger('coins_awarded');
            $t->timestamp('submitted_at')->index();
            $t->timestamps();
        });
        Schema::create('user_daily_progress', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('local_date');
            $t->unsignedSmallInteger('assigned')->default(0);
            $t->unsignedSmallInteger('completed')->default(0);
            $t->unsignedSmallInteger('correct')->default(0);
            $t->boolean('bonus_awarded')->default(false);
            $t->timestamps();
            $t->unique(['user_id', 'local_date']);
        });
        Schema::create('rewards', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->unsignedInteger('coins')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('admin_roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::create('admin_permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::create('admin_permission_role', function (Blueprint $t) {
            $t->foreignId('admin_role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('admin_permission_id')->constrained()->cascadeOnDelete();
            $t->primary(['admin_role_id', 'admin_permission_id']);
        });
        Schema::create('admin_users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admin_role_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->boolean('active')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('coin_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_answer_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->foreignId('admin_user_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('reference')->unique();
            $t->bigInteger('amount');
            $t->string('type');
            $t->text('reason');
            $t->timestamp('created_at')->useCurrent()->index();
            $t->index(['user_id', 'created_at']);
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('user_consents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('purpose');
            $t->string('policy_version');
            $t->json('authorized_fields');
            $t->timestamp('granted_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'purpose', 'revoked_at']);
        });
        Schema::create('contact_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_consent_id')->constrained()->restrictOnDelete();
            $t->timestamp('delete_after')->index();
            $t->timestamps();
        });
        Schema::create('contact_submission_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contact_submission_id')->constrained()->cascadeOnDelete();
            $t->text('encrypted_payload');
            $t->timestamps();
        });
        Schema::create('location_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_consent_id')->constrained()->restrictOnDelete();
            $t->text('encrypted_payload');
            $t->timestamp('collected_at');
            $t->timestamp('delete_after')->index();
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admin_user_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action')->index();
            $t->string('resource_type')->nullable();
            $t->unsignedBigInteger('resource_id')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
        Schema::create('deletion_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('scope')->default('account');
            $t->string('status')->default('pending')->index();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('login_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('ip_address', 45);
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['login_history', 'deletion_requests', 'audit_logs', 'location_submissions', 'contact_submission_items', 'contact_submissions', 'user_consents', 'notifications', 'coin_transactions', 'admin_users', 'admin_permission_role', 'admin_permissions', 'admin_roles', 'rewards', 'user_daily_progress', 'user_answers', 'daily_question_assignments', 'question_options', 'questions', 'categories', 'user_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['status', 'timezone', 'daily_schedule', 'terms_accepted_at', 'privacy_accepted_at']));
    }
};
