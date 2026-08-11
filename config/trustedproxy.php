<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Reverse proxies whose X-Forwarded-* headers are trusted, as a
    | comma-separated list of IPs/CIDRs (read by Laravel's TrustProxies
    | middleware). The login and 2FA rate limiters key on the client IP, so
    | trusting a proxy that forwards a client-controlled X-Forwarded-For
    | would let an attacker rotate it to dodge the 5/min limit.
    |
    | Default: the local web server that fronts PHP on plain hosting. Behind
    | a CDN such as Cloudflare, either configure the web server to set the
    | real client IP (nginx real_ip + CF-Connecting-IP) and leave this at the
    | loopback, or list the CDN's proxy ranges here. Set to '*' only when the
    | network blocks all direct access to the origin.
    |
    */

    'proxies' => env('TRUSTED_PROXIES', '127.0.0.1,::1'),
];
