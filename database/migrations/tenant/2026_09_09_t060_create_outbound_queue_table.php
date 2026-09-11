<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_queue', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 20);
            $table->string('priority', 20)->default('drip');
            $table->string('status', 20)->default('queued');
            $table->uuid('conversation_id')->nullable();
            $table->uuid('contact_id')->nullable();
            $table->uuid('whatsapp_session_id')->nullable();
            $table->uuid('campaign_id')->nullable();
            $table->string('contact_phone', 30);
            $table->string('chat_id', 80);
            $table->string('message_type', 20)->default('text');
            $table->text('body');
            $table->json('payload')->nullable();
            $table->timestamp('available_at');
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->uuid('created_by_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at']);
            $table->index(['kind', 'status']);
            $table->index('conversation_id');
            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_queue');
    }
};
