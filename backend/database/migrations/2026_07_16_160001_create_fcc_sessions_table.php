<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fcc_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->enum('action', ['fcc_enable', 'fcc_disable', 'keepalive_start', 'keepalive_stop', 'auto_fcc']);
            $table->boolean('success')->default(true);
            $table->integer('duration_seconds')->nullable();
            $table->integer('keepalive_count')->nullable();
            $table->integer('ce_reset_blocks')->nullable();
            $table->string('aircraft_serial', 50)->nullable();
            $table->string('controller_model', 100)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fcc_sessions');
    }
};
