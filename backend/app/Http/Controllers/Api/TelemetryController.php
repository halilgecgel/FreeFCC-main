<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnectionMetric;
use App\Models\DeviceTelemetry;
use App\Models\ErrorLog;
use App\Models\FccSession;
use App\Models\FeatureUsageLog;
use App\Services\FlightGroupNotificationService;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    public function deviceTelemetry(Request $request)
    {
        $data = $request->validate([
            'controller_model' => ['nullable', 'string', 'max:100'],
            'android_version' => ['nullable', 'string', 'max:50'],
            'firmware_version' => ['nullable', 'string', 'max:100'],
            'hardware_version' => ['nullable', 'string', 'max:100'],
            'bootloader_version' => ['nullable', 'string', 'max:100'],
            'aircraft_serial' => ['nullable', 'string', 'max:50'],
            'drone_model' => ['nullable', 'string', 'max:50'],
            'detected_port' => ['nullable', 'integer'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'network_type' => ['nullable', 'string', 'max:30'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'locale' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'server_ping_ms' => ['nullable', 'integer', 'min:0'],
        ]);

        $member = $request->user();

        DeviceTelemetry::create(array_merge($data, [
            'member_id' => $member->id,
        ]));

        return response()->json(['status' => 'ok']);
    }

    public function fccSession(Request $request, FlightGroupNotificationService $flightNotifier)
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'in:fcc_enable,fcc_disable,keepalive_start,keepalive_stop,auto_fcc'],
            'success' => ['required', 'boolean'],
            'duration_seconds' => ['nullable', 'integer'],
            'keepalive_count' => ['nullable', 'integer'],
            'ce_reset_blocks' => ['nullable', 'integer'],
            'aircraft_serial' => ['nullable', 'string', 'max:50'],
            'controller_model' => ['nullable', 'string', 'max:100'],
            'device_model' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'province' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'neighborhood' => ['nullable', 'string', 'max:150'],
            'failure_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $session = FccSession::create(array_merge($data, [
            'member_id' => $request->user()->id,
        ]));

        $location = [
            'device_model' => $data['device_model'] ?? null,
            'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
            'province' => $data['province'] ?? null,
            'district' => $data['district'] ?? null,
            'neighborhood' => $data['neighborhood'] ?? null,
        ];

        $member = $request->user();

        dispatch(function () use ($flightNotifier, $member, $session, $location) {
            $flightNotifier->notifySession($member, $session, $location);
        })->afterResponse();

        return response()->json(['status' => 'ok']);
    }

    public function featureUsage(Request $request)
    {
        $data = $request->validate([
            'feature' => ['required', 'string', 'max:50'],
            'success' => ['required', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        FeatureUsageLog::create(array_merge($data, [
            'member_id' => $request->user()->id,
        ]));

        return response()->json(['status' => 'ok']);
    }

    public function featureUsageBatch(Request $request)
    {
        $data = $request->validate([
            'events' => ['required', 'array', 'max:50'],
            'events.*.feature' => ['required', 'string', 'max:50'],
            'events.*.success' => ['required', 'boolean'],
            'events.*.metadata' => ['nullable', 'array'],
            'events.*.timestamp' => ['nullable', 'string'],
        ]);

        $memberId = $request->user()->id;

        foreach ($data['events'] as $event) {
            FeatureUsageLog::create([
                'member_id' => $memberId,
                'feature' => $event['feature'],
                'success' => $event['success'],
                'metadata' => $event['metadata'] ?? null,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    public function errorLog(Request $request)
    {
        $data = $request->validate([
            'error_type' => ['required', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:2000'],
            'stack_trace' => ['nullable', 'string', 'max:5000'],
            'context' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'controller_model' => ['nullable', 'string', 'max:100'],
        ]);

        ErrorLog::create(array_merge($data, [
            'member_id' => $request->user()->id,
        ]));

        return response()->json(['status' => 'ok']);
    }

    public function errorLogBatch(Request $request)
    {
        $data = $request->validate([
            'errors' => ['required', 'array', 'max:20'],
            'errors.*.error_type' => ['required', 'string', 'max:50'],
            'errors.*.message' => ['required', 'string', 'max:2000'],
            'errors.*.stack_trace' => ['nullable', 'string', 'max:5000'],
            'errors.*.context' => ['nullable', 'string', 'max:100'],
            'errors.*.app_version' => ['nullable', 'string', 'max:50'],
            'errors.*.controller_model' => ['nullable', 'string', 'max:100'],
        ]);

        $memberId = $request->user()->id;

        foreach ($data['errors'] as $error) {
            ErrorLog::create(array_merge($error, [
                'member_id' => $memberId,
            ]));
        }

        return response()->json(['status' => 'ok']);
    }

    public function connectionMetrics(Request $request)
    {
        $data = $request->validate([
            'connect_time_ms' => ['nullable', 'integer'],
            'command_latency_ms' => ['nullable', 'integer'],
            'disconnection_count' => ['nullable', 'integer'],
            'crc_error_count' => ['nullable', 'integer'],
            'port_used' => ['nullable', 'integer'],
            'controller_model' => ['nullable', 'string', 'max:100'],
        ]);

        ConnectionMetric::create(array_merge($data, [
            'member_id' => $request->user()->id,
        ]));

        return response()->json(['status' => 'ok']);
    }
}
