<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppNotification extends Model
{
    protected $fillable = [
        'title',
        'message',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(AppNotificationReceipt::class);
    }
}
