<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', fn (Blueprint $t) => $t->text('encrypted_push_token')->nullable());
    }

    public function down(): void
    {
        Schema::table('user_profiles', fn (Blueprint $t) => $t->dropColumn('encrypted_push_token'));
    }
};
