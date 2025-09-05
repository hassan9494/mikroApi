<?php

return [
    'api_url' => env('JO_FOTARA_API', 'https://backend.jofotara.gov.jo'),
    'taxpayer_id' => env('JO_TAXPAYER_ID'),
    'client_id' => env('JO_CLIENT_ID'),
    'secret_key' => env('JO_SECRET_KEY'),
    'encryption_key' => env('JO_ENCRYPTION_KEY'),
    'currency' => 'JOD',
    'currency_attribute' => 'JO',
    'tax_rate' => 16,
    'company_id'=>'13461320',
    'seller_name'=>'منتصر و محمود للالكترونيات',
    'postal_code'=>'33554',
    'seller_income_source'=>'13322320',
    'buyer_city'=>'JO-AM',
    'app_phase' => env('APP_PHASE','testing')
];
