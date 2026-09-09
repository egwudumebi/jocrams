<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_volumes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 255);
            $table->unsignedSmallInteger('volume_number');
            $table->unsignedSmallInteger('year');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['volume_number', 'year']);
        });

        Schema::create('journal_issues', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('journal_volume_id')->constrained('journal_volumes')->cascadeOnDelete();
            $table->string('title', 255);
            $table->unsignedSmallInteger('issue_number');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['journal_volume_id', 'issue_number']);
        });

        Schema::table('journal_calls_for_papers', function (Blueprint $table): void {
            $table->foreignId('journal_issue_id')->nullable()->after('created_by')->constrained('journal_issues')->nullOnDelete();
        });

        Schema::table('journal_submissions', function (Blueprint $table): void {
            $table->foreignId('journal_issue_id')->nullable()->after('call_for_papers_id')->constrained('journal_issues')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_submissions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('journal_issue_id');
        });

        Schema::table('journal_calls_for_papers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('journal_issue_id');
        });

        Schema::dropIfExists('journal_issues');
        Schema::dropIfExists('journal_volumes');
    }
};
