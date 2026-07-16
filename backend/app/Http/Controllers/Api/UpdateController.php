<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UpdateController extends Controller
{
    /**
     * Returns the latest active release and whether the client should update.
     *
     * Query params:
     *   current_version  – e.g. "1.4.7"
     *   version_code     – e.g. 15
     */
    public function check(Request $request)
    {
        $clientVersion = $request->query('current_version', '0.0.0');
        $clientCode = (int) $request->query('version_code', 0);

        $release = AppRelease::where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('version_code')
            ->first();

        if (! $release || $release->version_code <= $clientCode) {
            return response()->json([
                'has_update' => false,
            ]);
        }

        $isForced = $release->isForcedFor($clientVersion, $clientCode);
        $forceAfter = $release->forceAfterDate();

        // Always serve via the API download endpoint. Public /storage URLs
        // break when `php artisan storage:link` is missing on the host.
        $downloadUrl = url("/api/v1/download/{$release->id}");

        return response()->json([
            'has_update' => true,
            'is_forced' => $isForced,
            'force_after' => $forceAfter?->toIso8601String(),
            'version' => $release->version,
            'version_code' => $release->version_code,
            'title' => $release->title,
            'changelog' => $release->changelog ?? '',
            'download_url' => $downloadUrl,
            'apk_size' => $release->apk_size,
            'sha256' => $release->sha256,
            'published_at' => $release->published_at->toIso8601String(),
        ]);
    }

    /**
     * Streams the APK file for the given release (fallback if public URL is unavailable).
     */
    public function download(AppRelease $release)
    {
        if (! $release->is_active || ! $release->apk_path) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($release->apk_path)) {
            abort(404);
        }

        return response()->file(
            Storage::disk('public')->path($release->apk_path),
            [
                'Content-Type' => 'application/vnd.android.package-archive',
                'Content-Disposition' => 'attachment; filename="freefcc_v'.$release->version.'.apk"',
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }
}
