<?php

namespace CipiApi\Services;

class CipiOutputParser
{
    public function parse(string $type, string $output, bool $success = true): ?array
    {
        $plain = $this->stripAnsi($output);

        $result = match ($type) {
            'app-create' => $this->parseAppCreate($plain),
            'app-edit' => $this->parseAppEdit($plain),
            'app-delete' => $this->parseAppDelete($plain),
            'alias-create' => $this->parseAliasCreate($plain),
            'alias-delete' => $this->parseAliasDelete($plain),
            'ssl-install' => $this->parseSslInstall($plain),
            default => null,
        };

        if (! $success && $result === null) {
            $result = ['error' => $this->extractErrorMessage($plain)];
        }

        return $result;
    }

    protected function extractErrorMessage(string $text): ?string
    {
        if (preg_match('/\[ERROR\]\s*(.+?)(?:\n|$)/', $text, $m)) {
            return trim($m[1]);
        }
        $lines = array_filter(explode("\n", $text), fn ($l) => trim($l) !== '');
        $last = end($lines);
        return $last ? trim($last) : null;
    }

    public function stripAnsi(string $text): string
    {
        return preg_replace('/\x1b\[[0-9;]*m/', '', $text);
    }

    protected function extractLabel(string $text, string $label): ?string
    {
        if (preg_match('/' . preg_quote($label, '/') . '\s*([^\n]+)/', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    protected function parseAppCreate(string $text): ?array
    {
        $app = null;
        if (preg_match('/APP\s+CREATED:\s*(\S+)/', $text, $m)) {
            $app = trim($m[1]);
        }
        if (! $app) {
            return null;
        }

        $domain = $this->extractLabel($text, 'Domain:');
        $php = $this->extractLabel($text, 'PHP:');
        $home = $this->extractLabel($text, 'Home:');

        $sshUser = $sshPassword = null;
        if (preg_match('/SSH\s+(\S+)\s*\/\s*(\S+)/', $text, $m)) {
            $sshUser = $m[1];
            $sshPassword = $m[2];
        }

        $dbUser = $dbPassword = null;
        if (preg_match('/Database\s+(\S+)\s*\/\s*(\S+)/', $text, $m)) {
            $dbUser = $m[1];
            $dbPassword = $m[2];
        }

        $deployKey = null;
        if (preg_match('/(ssh-(?:ed25519|rsa)\s+[A-Za-z0-9+\/=]+\s+\S+)/', $text, $m)) {
            $deployKey = trim($m[1]);
        }

        $webhook = $this->extractLabel($text, 'Webhook');
        if (! $webhook && $domain) {
            $webhook = "https://{$domain}/cipi/webhook";
        }

        $token = $this->extractLabel($text, 'Token');

        return array_filter([
            'app' => $app,
            'domain' => $domain,
            'php' => $php,
            'home' => $home,
            'ssh' => ($sshUser || $sshPassword) ? [
                'user' => $sshUser,
                'password' => $sshPassword,
            ] : null,
            'database' => ($dbUser || $dbPassword) ? [
                'user' => $dbUser,
                'password' => $dbPassword,
            ] : null,
            'deploy_key' => $deployKey,
            'webhook' => $webhook,
            'webhook_token' => $token,
        ], fn ($v) => $v !== null);
    }

    protected function parseAppEdit(string $text): ?array
    {
        $changes = [];
        if (preg_match_all('/✓\s+(.+?)(?:\n|$)/', $text, $matches)) {
            foreach ($matches[1] as $m) {
                $m = trim($m);
                if (preg_match('/^(\w+)\s*→\s*(.+)$/', $m, $c)) {
                    $changes[strtolower(trim($c[1]))] = trim($c[2]);
                } elseif ($m === 'Repository updated') {
                    $changes['repository'] = 'updated';
                }
            }
        }
        if (str_contains($text, 'Nothing changed')) {
            return ['changes' => [], 'message' => 'Nothing changed'];
        }
        return ! empty($changes) ? ['changes' => $changes] : null;
    }

    protected function parseAppDelete(string $text): ?array
    {
        if (preg_match("/'([^']+)'\s+deleted/", $text, $m)) {
            return ['app' => $m[1], 'deleted' => true];
        }
        return null;
    }

    protected function parseAliasCreate(string $text): ?array
    {
        if (preg_match("/'([^']+)'\s+added\s+to\s+'([^']+)'/", $text, $m)) {
            return ['alias' => $m[1], 'app' => $m[2]];
        }
        return null;
    }

    protected function parseAliasDelete(string $text): ?array
    {
        if (preg_match("/'([^']+)'\s+removed\s+from\s+'([^']+)'/", $text, $m)) {
            return ['alias' => $m[1], 'app' => $m[2]];
        }
        return null;
    }

    protected function parseSslInstall(string $text): ?array
    {
        if (preg_match('/SSL\s+installed\s+for\s+(\S+)/', $text, $m)) {
            return ['domain' => trim($m[1]), 'installed' => true];
        }
        return null;
    }
}
