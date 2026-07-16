<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fcc_sessions', function (Blueprint $table) {
            $table->string('device_model', 100)->nullable()->after('controller_model');
            $table->decimal('latitude', 10, 7)->nullable()->after('device_model');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('province', 100)->nullable()->after('longitude');
            $table->string('district', 100)->nullable()->after('province');
            $table->string('neighborhood', 150)->nullable()->after('district');
        });
    }

    public function down(): void
    {
        Schema::table('fcc_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'device_model',
                'latitude',
                'longitude',
                'province',
                'district',
                'neighborhood',
            ]);
        });
    }
};
