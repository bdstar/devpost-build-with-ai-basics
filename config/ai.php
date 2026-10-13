<?php

return [

    /*
    | "fixtures": deterministic, labelled demo outputs from resources/fixtures (no API key).
    | "live": Gemini API calls (requires GEMINI_API_KEY). Never falls back silently.
    */
    'mode' => env('AI_MODE', 'fixtures') === 'live' ? 'live' : 'fixtures',

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
        'text_model' => env('GEMINI_TEXT_MODEL', 'gemini-3.8-flash'),
        'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'),
        'timeout' => 60,
        // Generated images are written here and served from image_url. Git-ignored.
        'image_dir' => public_path('generated'),
        'image_url' => '/generated',
    ],

];
