<?php

namespace App\Services;

use App\Models\FccSession;
use App\Models\Member;

/**
 * Closes an open flight when the member goes offline (e.g. RC powered off)
 * and notifies the flight WhatsApp group.
 */
class FlightAutoCloseService
{
    public const REASON_AUTO_CLOSED_OFFLINE = 'auto_closed_offline';

    public function __construct(
        protected FlightGroupNotificationService $notifier,
    ) {}

    /**
     * If the member's last successful flight event is an enable (no disable yet),
     * create a synthetic fcc_disable and send the end notice.
     */
    public function closeOpenFlightIfNeeded(Member $member): ?FccSession
    {
        $start = $this->resolveOpenFlightStart($member);

        if ($start === null) {
            return null;
        }

        $durationSeconds = max(0, (int) $start->created_at->diffInSeconds(now()));

        $session = FccSession::create([
            'member_id' => $member->id,
            'action' => 'fcc_disable',
            'success' => true,
            'duration_seconds' => $durationSeconds,
            'aircraft_serial' => $start->aircraft_serial,
            'controller_model' => $start->controller_model,
            'device_model' => $start->device_model,
            'latitude' => $start->latitude,
            'longitude' => $start->longitude,
            'province' => $start->province,
            'district' => $start->district,
            'neighborhood' => $start->neighborhood,
            'failure_reason' => self::REASON_AUTO_CLOSED_OFFLINE,
        ]);

        $location = [
            'device_model' => $session->device_model,
            'latitude' => $session->latitude !== null ? (float) $session->latitude : null,
            'longitude' => $session->longitude !== null ? (float) $session->longitude : null,
            'province' => $session->province,
            'district' => $session->district,
            'neighborhood' => $session->neighborhood,
        ];

        $this->notifier->notifySession($member, $session, $location);

        return $session;
    }

    protected function resolveOpenFlightStart(Member $member): ?FccSession
    {
        $last = FccSession::query()
            ->where('member_id', $member->id)
            ->where('success', true)
            ->whereIn('action', FccSession::FLIGHT_ACTIONS)
            ->orderByDesc('id')
            ->first();

        if ($last === null || ! in_array($last->action, FccSession::FLIGHT_START_ACTIONS, true)) {
            return null;
        }

        return $last;
    }
}
