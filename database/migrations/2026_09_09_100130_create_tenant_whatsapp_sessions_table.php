<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_whatsapp_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignUuid('sede_id')
                ->nullable()
                ->constrained('sedes')
                ->nullOnDelete();
            $table->string('alias', 80);
            $table->string('openwa_session_id', 80)->nullable();
            $table->string('openwa_session_name', 120);
            $table->string('status', 32)->default('created');
            $table->string('phone', 32)->nullable();
            $table->string('push_name', 120)->nullable();
            $table->timestampTz('connected_at')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('auto_reconnect')->default(true);
            $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'openwa_session_name']);
            $table->index(['tenant_id', 'status']);
            $table->index('sede_id');
        });

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX tenant_wa_sessions_tenant_sede_unique
                 ON tenant_whatsapp_sessions (tenant_id, sede_id)
                 WHERE sede_id IS NOT NULL AND deleted_at IS NULL'
            );
        }

        if ($driver === 'pgsql') {
            DB::statement(
                "ALTER TABLE tenant_whatsapp_sessions
                 ADD CONSTRAINT chk_tenant_wa_sessions_status
                 CHECK (status IN (
                    'created',
                    'initializing',
                    'qr_ready',
                    'authenticating',
                    'ready',
                    'disconnected',
                    'failed'
                 ))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_whatsapp_sessions');
    }
};
