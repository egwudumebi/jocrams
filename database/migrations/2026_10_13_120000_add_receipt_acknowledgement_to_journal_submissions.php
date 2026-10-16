<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_submissions', function (Blueprint $table): void {
            $table->timestamp('receipt_acknowledged_at')->nullable()->after('reviewed_at');
            $table->uuid('receipt_acknowledged_by')->nullable()->index()->after('receipt_acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('journal_submissions', function (Blueprint $table): void {
            $table->dropColumn(['receipt_acknowledged_at', 'receipt_acknowledged_by']);
        });
    }
};
