<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_board_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('user_id')->nullable()->index();
            $table->string('name', 150);
            $table->string('role_title', 150);
            $table->string('affiliation', 255)->nullable();
            $table->longText('bio')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('journal_calls_for_papers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('body')->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->decimal('submission_fee', 12, 2)->default(0);
            $table->decimal('publication_fee', 12, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->string('status', 20)->default('draft')->index();
            $table->json('topics')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_call_editorial_board', function (Blueprint $table): void {
            $table->foreignId('journal_call_for_papers_id')->constrained('journal_calls_for_papers')->cascadeOnDelete();
            $table->foreignId('editorial_board_member_id')->constrained('editorial_board_members')->cascadeOnDelete();
            $table->primary(['journal_call_for_papers_id', 'editorial_board_member_id'], 'journal_call_board_primary');
        });

        Schema::table('journal_submissions', function (Blueprint $table): void {
            $table->foreignId('call_for_papers_id')->nullable()->after('author_id')->constrained('journal_calls_for_papers')->nullOnDelete();
            $table->decimal('submission_fee_amount', 12, 2)->nullable()->after('status');
            $table->decimal('publication_fee_amount', 12, 2)->nullable()->after('submission_fee_amount');
            $table->foreignId('submission_payment_id')->nullable()->after('publication_fee_amount')->constrained('payments')->nullOnDelete();
            $table->foreignId('publication_payment_id')->nullable()->after('submission_payment_id')->constrained('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_submissions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('publication_payment_id');
            $table->dropConstrainedForeignId('submission_payment_id');
            $table->dropConstrainedForeignId('call_for_papers_id');
            $table->dropColumn(['submission_fee_amount', 'publication_fee_amount']);
        });

        Schema::dropIfExists('journal_call_editorial_board');
        Schema::dropIfExists('journal_calls_for_papers');
        Schema::dropIfExists('editorial_board_members');
    }
};
