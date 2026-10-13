<?php

return [

    'synthetic_path' => env('SYNTHETIC_DATASET_PATH', 'aquariumpark_synthetic_demo/aquariumpark_synthetic_posts.json'),

    'synthetic_metadata_path' => 'aquariumpark_synthetic_demo/dataset_metadata.json',

    'synthetic_dataset_key' => 'aquariumpark_synthetic_demo',

    'expected_records' => 30,

    /*
    | Evidence ladder (learner-agreed). High confidence is never assigned automatically.
    */
    'min_sample' => 5,
    'medium_sample' => 10,
    'medium_max_cv' => 0.5,

];
