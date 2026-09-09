<?php

return [
    'interval_seconds' => (int) env('MONITOR_INTERVAL_SECONDS', 60),

    'targets' => [
        'app' => rtrim((string) env('MONITOR_APP_URL', env('APP_URL', 'http://localhost')), '/').'/up',
    ],
];
