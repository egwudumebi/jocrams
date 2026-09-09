<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'starts_at']);
        });

        Schema::create('event_pricing_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->decimal('fee', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_pricing_tiers');
        Schema::dropIfExists('event_sessions');
    }
};
