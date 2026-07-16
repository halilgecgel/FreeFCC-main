<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_telemetry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('controller_model', 100)->nullable();
            $table->string('android_version', 50)->nullable();
            $table->string('firmware_version', 100)->nullable();
            $table->string('hardware_version', 100)->nullable();
            $table->string('bootloader_version', 100)->nullable();
            $table->string('aircraft_serial', 50)->nullable();
            $table->string('drone_model', 50)->nullable();
            $table->integer('detected_port')->nullable();
            $table->string('app_version', 50)->nullable();
            $table->string('network_type', 30)->nullable();
            $table->string('country_code', 10)->nullable();
            $table->string('locale', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('server_ping_ms')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_telemetry');
    }
};
