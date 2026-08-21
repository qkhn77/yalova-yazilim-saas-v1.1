<?php

$config = require base_path('vendor/livewire/livewire/config/livewire.php');

$previewMimes = (array) ($config['temporary_file_upload']['preview_mimes'] ?? []);

foreach (['jfif', 'jfif-tbnl'] as $mimeExtension) {
    if (! in_array($mimeExtension, $previewMimes, true)) {
        $previewMimes[] = $mimeExtension;
    }
}

$config['temporary_file_upload']['preview_mimes'] = $previewMimes;
$config['temporary_file_upload']['rules'] = ['required', 'file', 'max:40960'];
$config['temporary_file_upload']['max_upload_time'] = 10;

return $config;
