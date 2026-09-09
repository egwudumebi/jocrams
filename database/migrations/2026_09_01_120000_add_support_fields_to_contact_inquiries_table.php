<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_inquiries', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('uuid')->constrained()->nullOnDelete();
            $table->string('category', 80)->nullable()->after('message');
            $table->string('source', 20)->default('contact')->after('category');
            $table->text('last_response')->nullable()->after('assigned_to');
            $table->timestamp('resolved_at')->nullable()->after('responded_at');

            $table->index(['source', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('contact_inquiries', function (Blueprint $table) {
            $table->dropIndex(['source', 'status', 'created_at']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['category', 'source', 'last_response', 'resolved_at']);
        });
    }
};
