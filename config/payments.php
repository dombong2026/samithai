<?php

return [
    // No provider adapter or customer payment route is installed by default.
    // These values alone cannot enable checkout; provider-specific code is required.
    'enabled' => (bool) env('SAMITH_PAYMENTS_ENABLED', false),
    'provider' => env('SAMITH_PAYMENT_PROVIDER', 'disabled'),
    'currency' => 'USD',
    'supported_providers' => [], // Must be populated only by an audited, signed provider adapter.
];
