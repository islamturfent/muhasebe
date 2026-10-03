<?php

return [
    // integrator mode: test | production
    'mode' => getenv('EFATURA_MODE') ?: 'test',
    // which gateway adapter to use: simulated | rest (real HTTP integrator)
    'provider' => getenv('EFATURA_PROVIDER') ?: 'simulated',
    // test vs production endpoint base for real integrators
    'test_url' => getenv('EFATURA_TEST_URL') ?: '',
    'production_url' => getenv('EFATURA_PROD_URL') ?: '',
    // integrator credentials (Basic auth) for the REST adapter
    'username' => getenv('EFATURA_USERNAME') ?: '',
    'password' => getenv('EFATURA_PASSWORD') ?: '',
];
