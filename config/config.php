<?php

define('GCS_BUCKET', 'phantomedit-images');
define('GCS_PROJECT_ID', 'phantomedit');
define('GCS_CREDENTIALS_PATH', __DIR__.'/credentials.json');

//Permetim pujar imatges fins 5MB
define('MAX_FILE_SIZE', 5*1024*1024);
define('ALLOWED_IMAGES_TYPES', ['image/jpeg', 'image/png', 'image/bmp', 'image/jpg']);
define('ALLOWED_AUDIO_TYPES', ['audio/mpeg', 'audio/wav']);

