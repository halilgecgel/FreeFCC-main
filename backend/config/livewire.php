<?php

return [

    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:204800'], // 200MB
        'directory' => null,
        'middleware' => 'throttle:10,1',
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 30, // 30 dakika - büyük APK dosyaları için
        'cleanup' => true,
    ],

];
