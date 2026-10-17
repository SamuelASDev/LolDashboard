<?php

return [
    'key' => env('RIOT_KEY'),
    'queue_connection' => env('RIOT_QUEUE_CONNECTION', 'database'),
    'queue' => 'riot',
    'timeout' => 15,
    'connect_timeout' => 5,
    'requests_per_second' => (int) env('RIOT_REQUESTS_PER_SECOND', 15),
    'requests_per_two_minutes' => (int) env('RIOT_REQUESTS_PER_TWO_MINUTES', 80),
    'page_size' => 20,
    'max_pages' => 500,
    'max_retries' => 5,
    'run_hours' => 24,
];
