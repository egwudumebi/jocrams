<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_issues', function (Blueprint $table): void {
            $table->boolean('is_current')->default(false)->after('status');
        });

        Schema::create('published_articles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->json('authors');
            $table->text('abstract');
            $table->string('keywords')->nullable();
            $table->string('article_type')->default('Research Article');
            $table->date('received_at')->nullable();
            $table->date('revised_at')->nullable();
            $table->date('accepted_at')->nullable();
            $table->date('published_at')->nullable();
            $table->foreignId('journal_issue_id')->nullable()->constrained('journal_issues')->nullOnDelete();
            $table->unsignedInteger('volume_number')->nullable();
            $table->unsignedInteger('issue_number')->nullable();
            $table->string('page_start')->nullable();
            $table->string('page_end')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_original_filename')->nullable();
            $table->text('citation')->nullable();
            $table->string('copyright')->nullable();
            $table->string('license')->nullable();
            $table->string('doi')->nullable()->unique();
            $table->string('status')->default('draft');
            $table->boolean('is_crossref_sample')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['volume_number', 'issue_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('published_articles');

        Schema::table('journal_issues', function (Blueprint $table): void {
            $table->dropColumn('is_current');
        });
    }
};
