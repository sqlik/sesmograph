<?php

use App\Mcp\Servers\SesmographServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', SesmographServer::class)
    ->middleware(['api-token', 'throttle:120,1'])
    ->name('mcp');
