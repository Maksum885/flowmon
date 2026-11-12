<?php

return [
    'mqtt' => [
        'host' => env('MQTT_HOST', '127.0.0.1'),
        'port' => (int) env('MQTT_PORT', 1883),
        'username' => env('MQTT_USERNAME'),
        'password' => env('MQTT_PASSWORD'),
        'topic' => env('MQTT_TOPIC', '#'),
        'client_id' => env('MQTT_CLIENT_ID', 'flowmon-listener'),
        // hanya untuk perintah uji flowmon:publish-test
        'test_username' => env('MQTT_TEST_USERNAME', 'r40a'),
        'test_password' => env('MQTT_TEST_PASSWORD', 'r40a_dev'),
    ],
];
