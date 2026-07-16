<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceModel;
use Illuminate\Http\Request;

class DeviceModelController extends Controller
{
    /** Active device models for the app picker. */
    public function index()
    {
        $models = DeviceModel::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'slug', 'description']);

        return response()->json([
            'status' => 'ok',
            'data' => [
                'device_models' => $models->map(fn (DeviceModel $model) => [
                    'id' => $model->id,
                    'name' => $model->name,
                    'slug' => $model->slug,
                    'description' => $model->description,
                ])->values(),
            ],
        ]);
    }

    /**
     * Bind a device model to the authenticated member.
     * Once set, only an admin can change/clear it from the panel.
     */
    public function select(Request $request)
    {
        $member = $request->user();

        if ($member->device_model_id !== null) {
            return response()->json([
                'status' => 'error',
                'code' => 'already_selected',
                'message' => 'Cihaz modeliniz zaten seçilmiş. Değiştirmek için yöneticinizle iletişime geçin.',
            ], 409);
        }

        $data = $request->validate([
            'device_model_id' => ['required', 'integer', 'exists:device_models,id'],
        ]);

        $model = DeviceModel::query()
            ->active()
            ->whereKey($data['device_model_id'])
            ->first();

        if (! $model) {
            return response()->json([
                'status' => 'error',
                'code' => 'invalid_model',
                'message' => 'Seçilen cihaz modeli bulunamadı veya aktif değil.',
            ], 422);
        }

        $member->forceFill([
            'device_model_id' => $model->id,
        ])->save();

        $member->load('deviceModel');

        return response()->json([
            'status' => 'ok',
            'data' => [
                'member' => [
                    'username' => $member->username,
                    'name' => $member->name,
                    'expires_at' => $member->expires_at?->toIso8601String(),
                    'device_model' => [
                        'id' => $model->id,
                        'name' => $model->name,
                        'slug' => $model->slug,
                    ],
                ],
            ],
        ]);
    }
}
