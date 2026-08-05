<?php

namespace CipiApi\Mcp\Servers;

use CipiApi\Mcp\Tools\AliasAddTool;
use CipiApi\Mcp\Tools\AliasListTool;
use CipiApi\Mcp\Tools\AliasRemoveTool;
use CipiApi\Mcp\Tools\AppCreateTool;
use CipiApi\Mcp\Tools\AppDeleteTool;
use CipiApi\Mcp\Tools\AppDeployConfigShowTool;
use CipiApi\Mcp\Tools\AppDeployConfigUpdateTool;
use CipiApi\Mcp\Tools\AppDeployRollbackTool;
use CipiApi\Mcp\Tools\AppDeployTool;
use CipiApi\Mcp\Tools\AppDeployUnlockTool;
use CipiApi\Mcp\Tools\AppEditTool;
use CipiApi\Mcp\Tools\AppListTool;
use CipiApi\Mcp\Tools\AppShowTool;
use CipiApi\Mcp\Tools\ApiLogShowTool;
use CipiApi\Mcp\Tools\AppArtisanTool;
use CipiApi\Mcp\Tools\AppAuthJsonCreateTool;
use CipiApi\Mcp\Tools\AppAuthJsonDeleteTool;
use CipiApi\Mcp\Tools\AppAuthJsonShowTool;
use CipiApi\Mcp\Tools\AppAuthJsonUpdateTool;
use CipiApi\Mcp\Tools\AppBasicAuthDisableTool;
use CipiApi\Mcp\Tools\AppBasicAuthEnableTool;
use CipiApi\Mcp\Tools\AppBasicAuthStatusTool;
use CipiApi\Mcp\Tools\AppEnvShowTool;
use CipiApi\Mcp\Tools\AppEnvUpdateTool;
use CipiApi\Mcp\Tools\AppLogsTool;
use CipiApi\Mcp\Tools\AppRunCommandsTool;
use CipiApi\Mcp\Tools\AppRunTool;
use CipiApi\Mcp\Tools\AppSuspendTool;
use CipiApi\Mcp\Tools\AppUnsuspendTool;
use CipiApi\Mcp\Tools\DbBackupTool;
use CipiApi\Mcp\Tools\DbCreateTool;
use CipiApi\Mcp\Tools\DbDeleteTool;
use CipiApi\Mcp\Tools\DbEnginesTool;
use CipiApi\Mcp\Tools\DbListTool;
use CipiApi\Mcp\Tools\DbPasswordTool;
use CipiApi\Mcp\Tools\DbRestoreTool;
use CipiApi\Mcp\Tools\JobShowTool;
use CipiApi\Mcp\Tools\ServerStatusTool;
use CipiApi\Mcp\Tools\ServiceListTool;
use CipiApi\Mcp\Tools\SslForceTool;
use CipiApi\Mcp\Tools\SslInstallTool;
use CipiApi\Mcp\Tools\WwwAddTool;
use CipiApi\Mcp\Tools\WwwClearTool;
use CipiApi\Mcp\Tools\WwwForceFromRootTool;
use CipiApi\Mcp\Tools\WwwForceToRootTool;
use CipiApi\Mcp\Tools\WwwStatusTool;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server;

#[Name('Cipi Server')]
#[Version('1.0.0')]
#[Instructions('Cipi server management: apps, aliases, www redirects, databases (MariaDB/PostgreSQL), SSL, jobs, logs, and server status. Requires mcp-access token ability only.')]
class CipiServer extends Server
{
    /**
     * Laravel MCP paginates tools/list (default 15). Cursor does not fetch further pages,
     * so expose all Cipi tools in a single list response.
     */
    public int $defaultPaginationLength = 80;

    public int $maxPaginationLength = 80;

    protected array $tools = [
        AppListTool::class,
        AppShowTool::class,
        AppArtisanTool::class,
        AppRunTool::class,
        AppRunCommandsTool::class,
        AppDeployConfigShowTool::class,
        AppDeployConfigUpdateTool::class,
        AppEnvShowTool::class,
        AppEnvUpdateTool::class,
        AppAuthJsonShowTool::class,
        AppAuthJsonCreateTool::class,
        AppAuthJsonUpdateTool::class,
        AppAuthJsonDeleteTool::class,
        AppCreateTool::class,
        AppEditTool::class,
        AppDeleteTool::class,
        AppDeployTool::class,
        AppDeployRollbackTool::class,
        AppDeployUnlockTool::class,
        AppSuspendTool::class,
        AppUnsuspendTool::class,
        AppBasicAuthStatusTool::class,
        AppBasicAuthEnableTool::class,
        AppBasicAuthDisableTool::class,
        AliasListTool::class,
        AliasAddTool::class,
        AliasRemoveTool::class,
        WwwStatusTool::class,
        WwwAddTool::class,
        WwwForceToRootTool::class,
        WwwForceFromRootTool::class,
        WwwClearTool::class,
        DbEnginesTool::class,
        DbListTool::class,
        DbCreateTool::class,
        DbDeleteTool::class,
        DbBackupTool::class,
        DbRestoreTool::class,
        DbPasswordTool::class,
        SslInstallTool::class,
        SslForceTool::class,
        JobShowTool::class,
        AppLogsTool::class,
        ApiLogShowTool::class,
        ServerStatusTool::class,
        ServiceListTool::class,
    ];
}
