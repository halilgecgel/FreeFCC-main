<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->string('version');
            $table->unsignedInteger('version_code');
            $table->string('title');
            $table->text('changelog')->nullable();
            $table->string('apk_path');
            $table->unsignedBigInteger('apk_size')->default(0);
            $table->string('sha256')->nullable();
            $table->boolean('is_force')->default(false);
            $table->unsignedInteger('force_after_hours')->nullable();
            $table->string('min_supported_version')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('version_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
