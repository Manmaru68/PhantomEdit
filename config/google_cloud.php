<?php
return [
    'project_id' => 'phantomedit',
    'key_file' => __DIR__ .'/credentials.json',
    'buckets' => [
        'images' => [
            'name' => 'phantomedit-images',
            'path' => 'images',
            'url' => 'https://storage.cloud.google.com/phantomedit-images/images'
        ],
        'edited' => [
            'name' => 'phantomedit-images',
            'path' => 'edited',
            'url' => 'https://storage.cloud.google.com/phantomedit-images/edited'
        ],
        'audio' => [
            'name' => 'phantomedit-audio',
            'path' => 'audio',
            'url' => 'https://storage.cloud.google.com/phantomedit-audio/audio'
        ]
    ]
];
