<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contact_id');
            $table->string('channel', 30)->default('whatsapp');
            $table->uuid('whatsapp_session_id')->nullable();
            $table->string('wa_chat_id', 80)->nullable();
            $table->uuid('assigned_user_id')->nullable();
            $table->uuid('sede_id')->nullable();
            $table->string('status', 20)->default('OPEN');
            $table->string('priority', 20)->default('normal');
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->index(['contact_id', 'channel']);
            $table->index(['status', 'last_message_at']);
            $table->index('assigned_user_id');
            $table->index('whatsapp_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
