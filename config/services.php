<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'whatsapp' => [
        'url'    => env('WA_SERVICE_URL', 'http://localhost:3500'),
        'secret' => env('WA_SECRET', 'estuaire-wa-secret'),
    ],

    'call_center' => [
        'phones' => array_filter([
            env('CALL_CENTER_PHONE_1'),
            env('CALL_CENTER_PHONE_2'),
        ]),
    ],

    'kpay' => [
        'api_key'        => env('KPAY_API_KEY'),
        'secret_key'     => env('KPAY_SECRET_KEY'),
        'webhook_secret' => env('KPAY_WEBHOOK_SECRET'),
        'base_url'       => env('KPAY_BASE_URL', 'https://lite.kpay.site/api/v1'),
    ],

    // Paiement par compte GFS (Global Financial Solution) — livraison offerte
    'gfs' => [
        'api_key'      => env('GFS_API_KEY'),
        'api_secret'   => env('GFS_API_SECRET'), // optionnel : 2e clé acceptée pour la signature des webhooks
        'base_url'     => env('GFS_BASE_URL', 'https://backend.gfinancials.com/api/v1'),
        'callback_url' => env('GFS_CALLBACK_URL'), // défaut : APP_URL/api/webhooks/gfs
        'return_url'   => env('GFS_RETURN_URL', 'com.chrisdev237.myinsamresto1://payment/gfs'),
    ],

    // SMS NEXAH (smsvas.com), même fournisseur que le projet GFS
    // Restaurants affichés en premier dans l'app (IDs séparés par des virgules)
    'pinned_restaurant_ids' => array_filter(array_map('trim', explode(',', (string) env('PINNED_RESTAURANT_IDS', '')))),

    'nexah' => [
        'user'      => env('NEXAH_SMS_USER'),
        'password'  => env('NEXAH_SMS_PASSWORD'),
        'sender_id' => env('NEXAH_SMS_SENDER_ID', 'GFS'),
        'url'       => env('NEXAH_SMS_URL', 'https://smsvas.com/api/v1/sendsms'),
        // Numéros prévenus à chaque nouvelle commande pour relancer un livreur (séparés par des virgules)
        'order_alert_phones' => array_filter(array_map('trim', explode(',', (string) env('ORDER_ALERT_PHONES', '')))),
    ],

    'ultramsg' => [
        'instance_id' => env('ULTRAMSG_INSTANCE_ID'),
        'token' => env('ULTRAMSG_TOKEN'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', '1070472586568-njjtn28ulh40e32gd5hslhdckd3a20p0.apps.googleusercontent.com'),
        // Identifiants acceptés pour la connexion Google (web, iOS, Android)
        'client_ids' => array_values(array_filter(array_map('trim', explode(',', env('GOOGLE_CLIENT_IDS',
            '1070472586568-njjtn28ulh40e32gd5hslhdckd3a20p0.apps.googleusercontent.com,'
            . '1070472586568-q700duhak98lua5h6ug6kgpve3dkq3rm.apps.googleusercontent.com,'
            . '1070472586568-3vfffqq2tcc9kbed4vas1okjnvnal5hm.apps.googleusercontent.com'
        ))))),
    ],


    // Connexion Apple : identifiants (bundle ID) des apps autorisées
    'apple' => [
        'client_ids' => array_values(array_filter(array_map('trim', explode(',', env('APPLE_CLIENT_IDS',
            'com.chrisdev237.estuaireresto,com.chrisdev237.expressinsam'
        ))))),
    ],

];
