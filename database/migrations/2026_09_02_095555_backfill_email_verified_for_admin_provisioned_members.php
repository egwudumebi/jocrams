<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('users')
            ->whereNull('email_verified_at')
            ->whereIn('id', function ($query): void {
                $query->select('user_id')
                    ->from('members')
                    ->whereNotNull('approved_by');
            })
            ->update(['email_verified_at' => $now]);

        DB::table('users')
            ->whereNull('email_verified_at')
            ->where('has_changed_password', true)
            ->whereIn('id', function ($query): void {
                $query->select('user_id')->from('members');
            })
            ->update(['email_verified_at' => $now]);
    }

    public function down(): void
    {
        // Non-destructive backfill; no rollback needed.
    }
};
