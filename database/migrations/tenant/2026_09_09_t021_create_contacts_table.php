<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('phone', 20);
            $table->string('email', 150)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('sede_id')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->uuid('created_by_id')->nullable();
            $table->uuid('updated_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('phone');
            $table->index('sede_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
