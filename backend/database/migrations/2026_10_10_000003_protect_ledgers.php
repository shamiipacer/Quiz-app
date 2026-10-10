<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['coin_transactions', 'audit_logs'] as $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $name = $table.'_no_'.strtolower($operation);
                if (DB::getDriverName() === 'sqlite') {
                    DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON {$table} BEGIN SELECT RAISE(ABORT, 'Append-only ledger'); END");
                } elseif (DB::getDriverName() === 'mysql') {
                    DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Append-only ledger'");
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['coin_transactions', 'audit_logs'] as $table) {
            foreach (['update', 'delete'] as $operation) {
                DB::unprepared('DROP TRIGGER IF EXISTS '.$table.'_no_'.$operation);
            }
        }
    }
};
