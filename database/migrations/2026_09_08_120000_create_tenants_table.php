<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 60)->unique();
            $table->string('schema_name', 60)->unique();
            $table->string('razon_social', 200);
            $table->string('nombre_comercial', 150)->nullable();
            $table->string('ruc', 11)->nullable()->unique();
            $table->string('email_admin', 150);
            $table->string('telefono', 20)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('estado', 20)->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->boolean('onboarding_completado')->default(false);
            $table->unsignedSmallInteger('onboarding_paso')->default(0);
            $table->string('timezone', 50)->default('America/Lima');
            $table->string('locale', 10)->default('es_PE');
            $table->string('canal_adquisicion', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
