<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorLog extends Model
{
    protected $fillable = [
        'member_id',
        'error_type',
        'message',
        'stack_trace',
        'context',
        'app_version',
        'controller_model',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
