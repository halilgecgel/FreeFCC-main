<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppRelease extends Model
{
    protected $fillable = [
        'version',
        'version_code',
        'title',
        'changelog',
        'apk_path',
        'apk_size',
        'sha256',
        'is_force',
        'force_after_hours',
        'min_supported_version',
        'published_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'version_code' => 'integer',
            'apk_size' => 'integer',
            'is_force' => 'boolean',
            'force_after_hours' => 'integer',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Whether this release should be forced on the given client right now.
     */
    public function isForcedFor(?string $clientVersion, ?int $clientVersionCode): bool
    {
        if ($this->is_force) {
            return true;
        }

        if ($this->force_after_hours !== null && $this->published_at !== null) {
            if ($this->published_at->copy()->addHours($this->force_after_hours)->isPast()) {
                return true;
            }
        }

        if ($this->min_supported_version !== null && $clientVersion !== null) {
            if (version_compare($clientVersion, $this->min_supported_version, '<')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The datetime after which this release becomes mandatory (null = never auto-forced).
     */
    public function forceAfterDate(): ?\Carbon\Carbon
    {
        if ($this->is_force) {
            return $this->published_at;
        }

        if ($this->force_after_hours !== null && $this->published_at !== null) {
            return $this->published_at->copy()->addHours($this->force_after_hours);
        }

        return null;
    }
}
