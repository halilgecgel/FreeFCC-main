<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FccSession extends Model
{
    protected $fillable = [
        'member_id',
        'action',
        'success',
        'duration_seconds',
        'keepalive_count',
        'ce_reset_blocks',
        'aircraft_serial',
        'controller_model',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
