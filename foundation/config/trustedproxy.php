<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Comma separated addresses allowed to set X-Forwarded-* headers. The default
    | covers a reverse proxy such as nginx running on the same host. Behind that
    | proxy the scheme, host and port arrive as headers; without trusting it the
    | application builds http:// URLs and secure cookies break. Only the listed
    | addresses are believed, so those headers cannot be spoofed from outside.
    | Use a specific address per environment; never "*" on a publicly reachable
    | application server.
    |
    */

    'proxies' => env('TRUSTED_PROXIES', '127.0.0.1,::1'),

];
