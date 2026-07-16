<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('error_type', 50);
            $table->text('message');
            $table->text('stack_trace')->nullable();
            $table->string('context', 100)->nullable();
            $table->string('app_version', 50)->nullable();
            $table->string('controller_model', 100)->nullable();
            $table->timestamps();

            $table->index(['member_id', 'created_at']);
            $table->index('error_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
