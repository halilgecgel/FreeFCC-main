<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connection_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->integer('connect_time_ms')->nullable();
            $table->integer('command_latency_ms')->nullable();
            $table->integer('disconnection_count')->default(0);
            $table->integer('crc_error_count')->default(0);
            $table->integer('port_used')->nullable();
            $table->string('controller_model', 100)->nullable();
            $table->timestamps();

            $table->index(['member_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connection_metrics');
    }
};
