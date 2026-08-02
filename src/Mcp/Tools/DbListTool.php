<?php

namespace CipiApi\Mcp\Tools;

use CipiApi\Exceptions\MysqlDatabaseListingUnavailableException;
use CipiApi\Services\CipiDatabaseListCliService;
use CipiApi\Services\CipiValidationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tool;

#[Description('List databases via `cipi db list` (same as GET /api/dbs). Optional engine=mariadb|pgsql filter (Cipi 4.8+).')]
#[IsReadOnly]
class DbListTool extends Tool
{
    public function __construct(
        protected CipiDatabaseListCliService $dbListCli,
        protected CipiValidationService $validator,
    ) {}

    public function handle(Request $request): Response
    {
        $engineRaw = $request->get('engine');
        $engine = is_string($engineRaw) && $engineRaw !== '' ? $engineRaw : null;
        if ($err = $this->validator->engineError($engine)) {
            return Response::text("Error: {$err}");
        }
        if ($engine !== null) {
            $engine = $this->validator->normalizeEngine($engine);
        }

        try {
            $rows = $this->dbListCli->list($engine);
        } catch (MysqlDatabaseListingUnavailableException $e) {
            return Response::text('Error: ' . $e->getMessage());
        }

        if ($rows === []) {
            return Response::text('No databases found (excluding system schemas).');
        }

        $lines = array_map(function (array $r) {
            $parts = [];
            if (! empty($r['engine'])) {
                $parts[] = $r['engine'];
            }
            $parts[] = $r['name'] ?? '';
            if (! empty($r['user'])) {
                $parts[] = 'user=' . $r['user'];
            }
            if (! empty($r['size'])) {
                $parts[] = $r['size'];
            }

            return implode(' — ', array_filter($parts, fn ($p) => $p !== ''));
        }, $rows);

        return Response::text(implode("\n", $lines));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'engine' => $schema->string()->description('Optional filter: mariadb or pgsql (Cipi 4.8+)'),
        ];
    }
}
