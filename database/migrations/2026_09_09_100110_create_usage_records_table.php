<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('metric', 40);
            $table->date('period');
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'metric', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
