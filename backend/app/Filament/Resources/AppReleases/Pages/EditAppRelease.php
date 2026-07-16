<?php

namespace App\Filament\Resources\AppReleases\Pages;

use App\Filament\Resources\AppReleases\AppReleaseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditAppRelease extends EditRecord
{
    protected static string $resource = AppReleaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['apk_path'])) {
            $data['apk_size'] = Storage::disk('public')->size($data['apk_path']);
        }

        return $data;
    }
}
