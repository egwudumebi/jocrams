<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('author_id')->index();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->string('author_name', 150);
            $table->string('author_email', 255);
            $table->text('abstract');
            $table->string('category', 120);
            $table->string('keywords', 500)->nullable();
            $table->unsignedInteger('mins_read')->nullable();
            $table->json('references')->nullable();
            $table->string('document_path', 500);
            $table->string('status', 40)->index();
            $table->string('visibility', 20)->default('all')->index();
            $table->uuid('reviewer_id')->nullable()->index();
            $table->text('review_comment')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_reviewer_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('submission_id')->index();
            $table->uuid('assigned_by')->index();
            $table->uuid('reviewer_id')->index();
            $table->string('priority', 20)->default('normal');
            $table->timestamp('due_at')->nullable();
            $table->string('status', 30)->default('assigned')->index();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_submission_timelines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('submission_id')->index();
            $table->uuid('actor_id')->nullable()->index();
            $table->string('event', 120)->index();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        Schema::create('journal_editorial_comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('submission_id')->index();
            $table->uuid('author_id')->index();
            $table->string('author_role', 50);
            $table->uuid('parent_id')->nullable()->index();
            $table->text('comment');
            $table->timestamps();
        });

        Schema::create('journal_submission_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('submission_id')->index();
            $table->uuid('submitted_by')->index();
            $table->unsignedInteger('revision_number');
            $table->string('document_path', 500);
            $table->text('note')->nullable();
            $table->string('status', 40)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_submission_revisions');
        Schema::dropIfExists('journal_editorial_comments');
        Schema::dropIfExists('journal_submission_timelines');
        Schema::dropIfExists('journal_reviewer_assignments');
        Schema::dropIfExists('journal_submissions');
    }
};
