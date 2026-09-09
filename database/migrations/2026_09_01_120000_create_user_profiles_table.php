<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->uuid('user_id')->primary();
            $table->foreign('user_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->string('profile_image_path', 500)->nullable();
            $table->string('position', 150)->nullable();
            $table->text('professional_bio')->nullable();
            $table->string('country', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('street', 255)->nullable();
            $table->json('social_links')->nullable();
            $table->json('achievements')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
