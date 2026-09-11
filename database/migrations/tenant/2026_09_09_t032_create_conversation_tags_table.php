<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_tags', function (Blueprint $table) {
            $table->uuid('conversation_id');
            $table->uuid('tag_id');
            $table->timestamps();

            $table->primary(['conversation_id', 'tag_id']);
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_tags');
    }
};
