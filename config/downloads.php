<?php

return [
    'signed_url_ttl_minutes' => (int) env('DOWNLOAD_SIGNED_URL_TTL', 15),
    'private_disk' => env('DOWNLOAD_PRIVATE_DISK', 'local'),
];
