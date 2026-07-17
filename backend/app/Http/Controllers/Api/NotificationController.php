<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\AppNotificationReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Returns active notifications not yet delivered to the authenticated member.
     *
     * Query params:
     *   after_id – optional client cursor (id > this value); server receipt is the source of truth
     */
    public function index(Request $request)
    {
        $member = $request->user();
        $afterId = (int) $request->query('after_id', 0);

        $notifications = AppNotification::query()
            ->where('is_active', true)
            ->where('id', '>', $afterId)
            ->whereDoesntHave('receipts', function ($query) use ($member) {
                $query->where('member_id', $member->id)
                    ->whereNotNull('delivered_at');
            })
            ->orderBy('id')
            ->limit(50)
            ->get(['id', 'title', 'message', 'type', 'created_at']);

        return response()->json([
            'notifications' => $notifications,
        ]);
    }

    /**
     * Marks notifications as delivered (reached the device / shown).
     * Prevents the same notification from being returned again.
     */
    public function delivered(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $member = $request->user();
        $now = now();
        $validIds = $this->validNotificationIds($data['ids']);

        foreach ($validIds as $notificationId) {
            $this->upsertReceipt($member->id, $notificationId, deliveredAt: $now);
        }

        return response()->json([
            'ok' => true,
            'delivered' => count($validIds),
        ]);
    }

    /**
     * Marks notifications as read (user dismissed / acknowledged in-app).
     */
    public function read(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $member = $request->user();
        $now = now();
        $validIds = $this->validNotificationIds($data['ids']);

        foreach ($validIds as $notificationId) {
            $this->upsertReceipt($member->id, $notificationId, deliveredAt: $now, readAt: $now);
        }

        return response()->json([
            'ok' => true,
            'read' => count($validIds),
        ]);
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function validNotificationIds(array $ids): array
    {
        return AppNotification::query()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function upsertReceipt(
        int $memberId,
        int $notificationId,
        mixed $deliveredAt = null,
        mixed $readAt = null,
    ): void {
        DB::transaction(function () use ($memberId, $notificationId, $deliveredAt, $readAt) {
            $receipt = AppNotificationReceipt::query()
                ->where('app_notification_id', $notificationId)
                ->where('member_id', $memberId)
                ->lockForUpdate()
                ->first();

            if (! $receipt) {
                AppNotificationReceipt::create([
                    'app_notification_id' => $notificationId,
                    'member_id' => $memberId,
                    'delivered_at' => $deliveredAt,
                    'read_at' => $readAt,
                ]);

                return;
            }

            $updates = [];
            if ($deliveredAt !== null && $receipt->delivered_at === null) {
                $updates['delivered_at'] = $deliveredAt;
            }
            if ($readAt !== null && $receipt->read_at === null) {
                $updates['read_at'] = $readAt;
                if ($receipt->delivered_at === null && ! isset($updates['delivered_at'])) {
                    $updates['delivered_at'] = $deliveredAt ?? $readAt;
                }
            }

            if ($updates !== []) {
                $receipt->update($updates);
            }
        });
    }
}
