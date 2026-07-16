<?php

namespace App\Filament\Resources\AppReleases\Pages;

use App\Filament\Resources\AppReleases\AppReleaseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateAppRelease extends CreateRecord
{
    protected static string $resource = AppReleaseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! empty($data['apk_path'])) {
            $data['apk_size'] = Storage::disk('public')->size($data['apk_path']);
        }

        return $data;
    }
}
