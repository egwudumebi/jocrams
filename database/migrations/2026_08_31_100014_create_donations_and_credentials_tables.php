<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('donor_name');
            $table->string('donor_email');
            $table->boolean('is_anonymous')->default(false);
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('digital_credentials', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('title');
            $table->string('template_key', 50);
            $table->string('verification_token', 64)->unique();
            $table->foreignId('file_media_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['member_id', 'type']);
            $table->index(['type', 'revoked_at']);
        });

        Schema::create('credential_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('digital_credential_id')->constrained()->cascadeOnDelete();
            $table->string('result', 20);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('verified_at');

            $table->index(['digital_credential_id', 'verified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_verifications');
        Schema::dropIfExists('digital_credentials');
        Schema::dropIfExists('donations');
    }
};
