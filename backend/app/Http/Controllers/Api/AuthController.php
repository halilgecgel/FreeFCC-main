<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Models\Member;
use App\Models\MemberActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Auth for the FreeFCC Android app's members (not Filament admin users).
 *
 * Enforces "one account, one device": a member's ANDROID_ID device_id is
 * bound on first successful login and every subsequent login must come
 * from that same device, until an admin resets it from the panel.
 */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_id' => ['required', 'string', 'max:191'],
            'device_name' => ['nullable', 'string', 'max:191'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        $member = Member::where('username', $data['username'])->first();

        if (! $member || ! Hash::check($data['password'], $member->password)) {
            $this->logAttempt(null, $data, false, 'invalid_credentials');

            return $this->error('invalid_credentials', 'Kullanıcı adı veya şifre hatalı.', 401);
        }

        if (! $member->is_active) {
            $this->logAttempt($member, $data, false, 'inactive');

            return $this->error('inactive', 'Hesabınız pasif durumda. Yöneticinizle iletişime geçin.', 403);
        }

        if ($member->isExpired()) {
            $this->logAttempt($member, $data, false, 'expired');

            return $this->error('expired', 'Üyeliğinizin süresi doldu. Yöneticinizle iletişime geçin.', 403);
        }

        if ($member->device_id === null) {
            // First login ever (or after an admin reset) — bind this device.
            $member->forceFill([
                'device_id' => $data['device_id'],
                'device_registered_at' => now(),
            ]);
        } elseif ($member->device_id !== $data['device_id']) {
            $this->logAttempt($member, $data, false, 'device_mismatch');

            return $this->error(
                'device_mismatch',
                'Bu hesap başka bir cihazda kayıtlı. Cihaz değişikliği için yöneticinizle iletişime geçin.',
                409
            );
        }

        // Single active session per member: wipe any previous token.
        $member->tokens()->delete();
        $token = $member->createToken($data['device_name'] ?? $data['device_id']);

        $member->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'app_version' => $data['app_version'] ?? $member->app_version,
        ])->save();

        $member->markOnline($request->ip());

        $this->logAttempt($member, $data, true, 'ok');

        return response()->json([
            'status' => 'ok',
            'data' => [
                'token' => $token->plainTextToken,
                'member' => $this->memberPayload($member),
            ],
        ]);
    }

    public function me(Request $request)
    {
        $member = $request->user();

        if (! $member->isUsable()) {
            // Token is technically still valid but the account was deactivated
            // or expired since it was issued — kill the session immediately.
            $member->currentAccessToken()?->delete();

            return $this->error(
                $member->is_active ? 'expired' : 'inactive',
                $member->is_active
                    ? 'Üyeliğinizin süresi doldu. Yöneticinizle iletişime geçin.'
                    : 'Hesabınız pasif durumda. Yöneticinizle iletişime geçin.',
                403
            );
        }

        return response()->json([
            'status' => 'ok',
            'data' => [
                'member' => $this->memberPayload($member),
            ],
        ]);
    }

    public function heartbeat(Request $request)
    {
        $member = $request->user();

        if (! $member->isUsable()) {
            $member->currentAccessToken()?->delete();

            return $this->error('inactive', 'Hesap kullanılamaz.', 403);
        }

        $member->markOnline($request->ip());

        return response()->json(['status' => 'ok']);
    }

    public function offline(Request $request)
    {
        $request->user()->markOffline();

        return response()->json(['status' => 'ok']);
    }

    public function logout(Request $request)
    {
        $member = $request->user();
        $member->markOffline();
        $member->currentAccessToken()?->delete();

        return response()->json(['status' => 'ok']);
    }

    private function logAttempt(?Member $member, array $data, bool $success, string $reason): void
    {
        LoginLog::create([
            'member_id' => $member?->id,
            'username' => $data['username'],
            'device_id' => $data['device_id'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'success' => $success,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function memberPayload(Member $member): array
    {
        return [
            'username' => $member->username,
            'name' => $member->name,
            'expires_at' => $member->expires_at?->toIso8601String(),
        ];
    }

    private function error(string $code, string $message, int $status)
    {
        return response()->json([
            'status' => 'error',
            'code' => $code,
            'message' => $message,
        ], $status);
    }
}
