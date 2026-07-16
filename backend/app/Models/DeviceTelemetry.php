<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceTelemetry extends Model
{
    protected $table = 'device_telemetry';

    protected $fillable = [
        'member_id',
        'controller_model',
        'android_version',
        'firmware_version',
        'hardware_version',
        'bootloader_version',
        'aircraft_serial',
        'drone_model',
        'detected_port',
        'app_version',
        'network_type',
        'country_code',
        'locale',
        'latitude',
        'longitude',
        'server_ping_ms',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
