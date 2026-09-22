<?php

use App\Support\Brand;

// Provisional brand. This is the single place to change it: the logo mark is derived from it, in the
// store and in the admin alike. APP_NAME is deliberately not used: Laravel derives the session cookie
// and cache prefixes from it, so renaming the brand there would sign everyone out. When the brand is
// final, also set MAIL_FROM_NAME, which e-mails use as the sender name.
$name = 'TECH COMMERCE';

return [
    // Public identity only. No secrets or integration settings.
    'name' => $name,
    'mark' => Brand::markFor($name),
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
