<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 80);
            $table->string('shortcut', 30)->nullable();
            $table->text('body');
            $table->uuid('created_by_id')->nullable();
            $table->timestamps();

            $table->unique('shortcut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_replies');
    }
};
