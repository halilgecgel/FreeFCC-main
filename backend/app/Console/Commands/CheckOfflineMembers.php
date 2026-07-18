<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Services\FlightAutoCloseService;
use Illuminate\Console\Command;

class CheckOfflineMembers extends Command
{
    protected $signature = 'members:check-offline';

    protected $description = 'Heartbeat zaman aşımına uğrayan üyeleri offline işaretle ve açık uçuşlarını kapat';

    public function handle(FlightAutoCloseService $flightAutoClose): void
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

        // Members already offline (is_online=false) were skipped above; still close
        // any stuck open flights so history does not stay "Devam ediyor".
        $closed = $flightAutoClose->closeOrphanedOpenFlights();

        if ($closed > 0) {
            $this->info("{$closed} açık uçuş otomatik kapatıldı.");
        }
    }
}
