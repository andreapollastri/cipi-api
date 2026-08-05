<?php

use CipiApi\Mcp\Servers\CipiServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', CipiServer::class)
    ->middleware(['cipi.ip', 'auth:sanctum', 'ability:mcp-access']);
