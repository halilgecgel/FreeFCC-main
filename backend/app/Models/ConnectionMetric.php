<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionMetric extends Model
{
    protected $fillable = [
        'member_id',
        'connect_time_ms',
        'command_latency_ms',
        'disconnection_count',
        'crc_error_count',
        'port_used',
        'controller_model',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
