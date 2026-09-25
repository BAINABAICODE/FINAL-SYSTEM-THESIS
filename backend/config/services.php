<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AGAPORA AI assistants (post-computation only)
    |--------------------------------------------------------------------------
    |
    | Default text provider: Groq (OpenAI-compatible free tier).
    | Alternatives: openrouter, openai, or any OpenAI-compatible base URL.
    | These services explain/visualize results — they never calculate genetics.
    |
    */
    'agapora_ai' => [
        'enabled' => env('AGAPORA_AI_ENABLED', false),
        'auto' => env('AGAPORA_AI_AUTO', true),
        'visual_description_enabled' => env('AGAPORA_AI_VISUAL_DESCRIPTION_ENABLED', true),
        'provider' => env('AGAPORA_AI_PROVIDER', 'groq'),
        'api_key' => env('AGAPORA_AI_API_KEY', env('GROQ_API_KEY')),
        'base_url' => env('AGAPORA_AI_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('AGAPORA_AI_MODEL', 'llama-3.3-70b-versatile'),
        'timeout' => env('AGAPORA_AI_TIMEOUT', 90),
    ],

    'offspring_image' => [
        'enabled' => env('OFFSPRING_IMAGE_ENABLED', false),
        'auto' => env('OFFSPRING_IMAGE_AUTO', false),
        'provider' => env('OFFSPRING_IMAGE_PROVIDER', 'openrouter'),
        'fallback_provider' => env('OFFSPRING_IMAGE_FALLBACK_PROVIDER', 'huggingface'),
        'request_delay_ms' => env('OFFSPRING_IMAGE_REQUEST_DELAY_MS', 1500),
        'openrouter_exhausted_ttl' => env('OPENROUTER_EXHAUSTED_TTL', 21600),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenRouter Image Generation (primary visualizer)
    |--------------------------------------------------------------------------
    |
    | Token must live only in backend .env (OPENROUTER_API_KEY). Never expose to React.
    | When credits/tokens are exhausted, chick images fall back to Hugging Face.
    |
    */
    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'image_model' => env('OPENROUTER_IMAGE_MODEL', 'google/gemini-3-pro-image'),
        'timeout' => env('OPENROUTER_IMAGE_TIMEOUT', 180),
        'aspect_ratio' => env('OPENROUTER_IMAGE_ASPECT_RATIO', '4:3'),
        'http_referer' => env('OPENROUTER_HTTP_REFERER', env('APP_URL', 'http://localhost')),
        'app_title' => env('OPENROUTER_APP_TITLE', env('APP_NAME', 'AGAPORA')),
        'ca_bundle' => env('OPENROUTER_CA_BUNDLE', env('HF_CA_BUNDLE')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hugging Face Image Generation (visualization only)
    |--------------------------------------------------------------------------
    |
    | Token must live only in backend .env (HF_TOKEN). Never expose to React.
    | Images visualize RBGIA outcomes — they never calculate genetics.
    |
    */
    'huggingface' => [
        'token' => env('HF_TOKEN'),
        'model' => env('HF_IMAGE_MODEL', 'black-forest-labs/FLUX.1-dev'),
        'provider' => env('HF_IMAGE_PROVIDER', 'auto'),
        'endpoint' => env('HF_IMAGE_ENDPOINT', 'https://router.huggingface.co'),
        'timeout' => env('HF_IMAGE_TIMEOUT', 180),
        // CA bundle for HTTPS verification (fixes cURL error 60 on Windows PHP).
        // Falls back to php.ini curl.cainfo, then storage/certs/cacert.pem.
        'ca_bundle' => env('HF_CA_BUNDLE'),
    ],

];
