<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['English', 'What is the plural of child?', 'Children', 'Childs', 'What is a synonym of happy?', 'Glad', 'Angry'],
            ['Physics', 'What is the SI unit of force?', 'Newton', 'Joule', 'What travels fastest in a vacuum?', 'Light', 'Sound'],
            ['Biology', 'Which organ pumps blood?', 'Heart', 'Lung', 'Which structure contains DNA?', 'Nucleus', 'Cell wall'],
            ['Mathematics', 'What is 7 × 8?', '56', '54', 'What is the square root of 81?', '9', '8'],
            ['Chemistry', 'What is the chemical symbol for oxygen?', 'O', 'Au', 'What is the formula for water?', 'H2O', 'CO2'],
            ['Computer Science', 'What does CPU stand for?', 'Central Processing Unit', 'Computer Power Utility', 'Which data structure uses FIFO?', 'Queue', 'Stack'],
            ['General Knowledge', 'How many days are in a leap year?', '366', '365', 'How many months are in a year?', '12', '10'],
            ['History', 'Which civilization built the pyramids at Giza?', 'Ancient Egyptians', 'Romans', 'Who was the first US president?', 'George Washington', 'Abraham Lincoln'],
            ['Geography', 'What is the largest ocean?', 'Pacific', 'Atlantic', 'On which continent is Kenya?', 'Africa', 'Asia'],
            ['Islamic Studies', 'How many pillars of Islam are there?', 'Five', 'Four', 'What is the holy book of Islam?', 'Quran', 'Torah'],
        ];
        foreach ($subjects as $order => $s) {
            DB::table('categories')->insertOrIgnore(['name' => $s[0], 'description' => "Daily {$s[0]} practice", 'icon' => 'book', 'display_order' => $order, 'active' => true, 'daily_limit' => 2, 'default_reward' => 10, 'created_at' => now(), 'updated_at' => now()]);
            $category = DB::table('categories')->where('name', $s[0])->value('id');
            if (DB::table('questions')->where('category_id', $category)->exists()) {
                continue;
            }
            foreach ([1, 4] as $i) {
                if (DB::table('questions')->where('category_id', $category)->where('text', $s[$i])->exists()) {
                    continue;
                }
                $id = DB::table('questions')->insertGetId(['category_id' => $category, 'text' => $s[$i], 'type' => 'multiple_choice', 'difficulty' => 'easy', 'explanation' => "The answer is {$s[$i + 1]}.", 'publication_at' => now()->subDay(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
                foreach ([1, 2] as $j) {
                    DB::table('question_options')->insert(['question_id' => $id, 'text' => $s[$i + $j], 'is_correct' => $j === 1, 'display_order' => $j, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
        DB::table('rewards')->insertOrIgnore(['key' => 'daily_completion', 'coins' => 20, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $permissions = ['categories.manage', 'questions.manage', 'users.manage', 'rewards.manage', 'privacy.manage', 'reports.view', 'notifications.manage'];
        foreach ($permissions as $permission) {
            DB::table('admin_permissions')->insertOrIgnore(['name' => $permission, 'created_at' => now(), 'updated_at' => now()]);
        }
        $roles = ['Super Admin' => $permissions, 'Question Manager' => ['categories.manage', 'questions.manage'], 'Support Manager' => ['users.manage'], 'Privacy/Data Manager' => ['privacy.manage'], 'Read-only Analyst' => ['reports.view']];
        foreach ($roles as $role => $perms) {
            $created = DB::table('admin_roles')->insertOrIgnore(['name' => $role, 'created_at' => now(), 'updated_at' => now()]);
            if (! $created) {
                continue;
            }
            $id = DB::table('admin_roles')->where('name', $role)->value('id');
            foreach ($perms as $p) {
                DB::table('admin_permission_role')->updateOrInsert(['admin_role_id' => $id, 'admin_permission_id' => DB::table('admin_permissions')->where('name', $p)->value('id')]);
            }
        }
    }
}
