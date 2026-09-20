<?php

return [
    // Public identity only. No secrets or integration settings.
    'name' => 'TECH COMMERCE',
    'mark' => 'TC',
    'tagline' => 'Tecnología para lo que sigue.',
    'description' => 'Explora tecnología, compara especificaciones y encuentra tu próximo equipo en nuestro catálogo para Costa Rica.',
    'locale' => 'es_CR',
    'region' => 'Costa Rica',

    // Public contact channels for corporate enquiries. Empty by default on purpose: the pages
    // say the channel is being set up rather than showing an invented number or address.
    'contact' => [
        'whatsapp' => env('STOREFRONT_WHATSAPP', ''),
        'email' => env('STOREFRONT_CORPORATE_EMAIL', ''),
    ],
];
