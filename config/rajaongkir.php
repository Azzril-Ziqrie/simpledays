<?php

require_once __DIR__ . '/env.php';

return [
    'base_url' => 'https://rajaongkir.komerce.id/api/v1',
    'api_key' => ddSecret('RAJAONGKIR_API_KEY'),
    'origin_city_id' => 4816,
    'default_weight_gram' => 1000,
];
