<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_tags', function (Blueprint $table) {
            $table->uuid('contact_id');
            $table->uuid('tag_id');
            $table->timestamps();

            $table->primary(['contact_id', 'tag_id']);
            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_tags');
    }
};
