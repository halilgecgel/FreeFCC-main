<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('name');
            $table->boolean('is_online')->default(false)->after('app_version');
            $table->timestamp('last_heartbeat_at')->nullable()->after('is_online');
            $table->unsignedInteger('total_online_seconds')->default(0)->after('last_heartbeat_at');
        });

        Schema::create('member_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->enum('event', ['online', 'offline']);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_activity_logs');

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['phone', 'is_online', 'last_heartbeat_at', 'total_online_seconds']);
        });
    }
};
