<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('pending_timezone')->nullable();
            $t->time('pending_daily_schedule')->nullable();
            $t->timestamp('schedule_effective_at')->nullable();
        });
        Schema::create('question_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('admin_user_id')->constrained()->restrictOnDelete();
            $t->json('snapshot');
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_revisions');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['pending_timezone', 'pending_daily_schedule', 'schedule_effective_at']));
    }
};
