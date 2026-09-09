<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('sent_at');
            $table->index(['notifiable_type', 'notifiable_id', 'channel', 'read_at'], 'notification_logs_inbox_index');
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex('notification_logs_inbox_index');
            $table->dropColumn('read_at');
        });
    }
};
