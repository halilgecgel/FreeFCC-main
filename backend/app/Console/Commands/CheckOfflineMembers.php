<?php

namespace App\Console\Commands;

use App\Models\Member;
use Illuminate\Console\Command;

class CheckOfflineMembers extends Command
{
    protected $signature = 'members:check-offline';

    protected $description = 'Heartbeat zaman aşımına uğrayan üyeleri offline olarak işaretle';

    public function handle(): void
    {
        $timeout = now()->subMinutes(Member::HEARTBEAT_TIMEOUT_MINUTES);

        $members = Member::where('is_online', true)
            ->where(function ($query) use ($timeout) {
                $query->whereNull('last_heartbeat_at')
                    ->orWhere('last_heartbeat_at', '<', $timeout);
            })
            ->get();

        foreach ($members as $member) {
            $member->markOffline();
        }

        if ($members->isNotEmpty()) {
            $this->info("{$members->count()} üye offline olarak işaretlendi.");
        }
    }
}
