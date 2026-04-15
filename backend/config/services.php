<?php

return [

    'huggingface' => [
        'token' => env('HUGGINGFACE_API_TOKEN'),
        'model' => env('HUGGINGFACE_MODEL', 'cardiffnlp/twitter-roberta-base-sentiment-latest'),
        'endpoint' => 'https://router.huggingface.co/hf-inference/models/',
        'timeout' => 15,
    ],

];
