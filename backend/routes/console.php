<?php

use App\Models\AdminUser;
use App\Models\User;
use App\Services\QuizService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:create {email} {name}', function () {
    $password = $this->secret('Password (minimum 12 characters)');
    if (strlen($password) < 12) {
        $this->error('Password too short.');

        return 1;
    }
    $role = DB::table('admin_roles')->where('name', 'Super Admin')->value('id');
    if (! $role) {
        $this->error('Run database seeders first.');

        return 1;
    }
    AdminUser::create(['email' => $this->argument('email'), 'name' => $this->argument('name'), 'password' => $password, 'admin_role_id' => $role]);
    $this->info('Administrator created.');
});

Artisan::command('quiz:assign', function () {
    User::where('status', 'active')->whereNotNull('email_verified_at')->chunkById(100, function ($users) {
        foreach ($users as $user) {
            app(QuizService::class)->today($user);
        }
    });
    $this->info('Daily assignments prepared.');
});
Artisan::command('privacy:expire', function () {
    foreach (['contact_submissions', 'location_submissions'] as $table) {
        DB::table($table)->where('delete_after', '<=', now())->chunkById(100, function ($rows) use ($table) {
            DB::transaction(function () use ($rows, $table) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->delete();
                    DB::table('audit_logs')->insert(['user_id' => $row->user_id, 'action' => 'privacy.retention_deleted', 'resource_type' => $table, 'resource_id' => $row->id, 'created_at' => now()]);
                }
            });
        });
    }
});
Schedule::command('quiz:assign')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('privacy:expire')->daily()->withoutOverlapping();

Artisan::command('quiz:seed-initial', function () {
    if (DB::table('categories')->exists()) {
        $this->info('Existing content preserved.');

        return;
    }
    $this->call('db:seed');
});
