<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_onboarding_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email', 255)->index();
            $table->string('phone', 50);
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->boolean('email_verified')->default(false);
            $table->string('state', 40)->index()->default('personal_info_submitted');
            $table->json('professional_payload')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('membership_onboarding_otps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('session_id')->index();
            $table->string('otp_hash', 255);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_onboarding_otps');
        Schema::dropIfExists('membership_onboarding_sessions');
    }
};
