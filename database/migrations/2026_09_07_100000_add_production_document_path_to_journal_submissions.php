<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_submissions', function (Blueprint $table): void {
            $table->string('production_document_path', 500)->nullable()->after('document_path');
        });
    }

    public function down(): void
    {
        Schema::table('journal_submissions', function (Blueprint $table): void {
            $table->dropColumn('production_document_path');
        });
    }
};
