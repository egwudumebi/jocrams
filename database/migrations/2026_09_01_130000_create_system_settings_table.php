<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group_name', 60);
            $table->string('setting_key', 120);
            $table->text('setting_value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['group_name', 'setting_key']);
            $table->index('group_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
