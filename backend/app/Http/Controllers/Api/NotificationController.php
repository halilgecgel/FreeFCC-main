<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Returns active notifications newer than the given ID.
     *
     * Query params:
     *   after_id – only return notifications with id > this value (default 0)
     */
    public function index(Request $request)
    {
        $afterId = (int) $request->query('after_id', 0);

        $notifications = AppNotification::where('is_active', true)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(50)
            ->get(['id', 'title', 'message', 'type', 'created_at']);

        return response()->json([
            'notifications' => $notifications,
        ]);
    }
}
