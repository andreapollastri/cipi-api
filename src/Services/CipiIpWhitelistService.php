<?php

namespace CipiApi\Services;

/**
 * API/MCP client IP allowlist backed by /etc/cipi/api-ip-whitelist.
 *
 * Missing file, empty file, or a "*" entry = allow all (default).
 * Otherwise one IPv4/IPv6 address or CIDR per line.
 */
class CipiIpWhitelistService
{
    public function __construct(
        protected CipiCliService $cli,
    ) {}

    public function filePath(): string
    {
        return (string) config('cipi.ip_whitelist_file', '/etc/cipi/api-ip-whitelist');
    }

    /**
     * @return array{allow_all: bool, entries: list<string>, file: string}
     */
    public function status(): array
    {
        $entries = $this->readEntries();
        $allowAll = $this->isAllowAll($entries);

        return [
            'allow_all' => $allowAll,
            'entries' => $allowAll ? ['*'] : $entries,
            'file' => $this->filePath(),
        ];
    }

    public function allows(string $ip): bool
    {
        $ip = $this->normalizeClientIp($ip);
        if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $entries = $this->readEntries();
        if ($this->isAllowAll($entries)) {
            return true;
        }

        foreach ($entries as $rule) {
            if ($this->ipMatches($ip, $rule)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strip IPv4-mapped IPv6 (::ffff:x.x.x.x) so whitelist entries can be plain IPv4.
     */
    public function normalizeClientIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return '';
        }

        if (str_starts_with(strtolower($ip), '::ffff:')) {
            $v4 = substr($ip, 7);
            if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $v4;
            }
        }

        return $ip;
    }

    /**
     * @param  list<string>  $entries
     * @return array{allow_all: bool, entries: list<string>, file: string}
     */
    public function set(array $entries): array
    {
        $normalized = $this->normalizeEntries($entries);
        if ($normalized === ['*'] || $normalized === []) {
            return $this->allowAll();
        }

        $ips = implode(',', $normalized);
        $result = $this->cli->run('api ip-whitelist set --ips=' . escapeshellarg($ips));
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');

            throw new \RuntimeException($detail !== '' ? $detail : 'cipi api ip-whitelist set failed');
        }

        return $this->status();
    }

    public function add(string $entry): array
    {
        $entry = trim($entry);
        if ($entry === '' || ! $this->isValidEntry($entry)) {
            throw new \InvalidArgumentException('Invalid IP or CIDR: '.$entry);
        }

        $result = $this->cli->run('api ip-whitelist add ' . escapeshellarg($entry));
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');

            throw new \RuntimeException($detail !== '' ? $detail : 'cipi api ip-whitelist add failed');
        }

        return $this->status();
    }

    public function remove(string $entry): array
    {
        $entry = trim($entry);
        if ($entry === '') {
            throw new \InvalidArgumentException('IP or CIDR is required');
        }

        $result = $this->cli->run('api ip-whitelist remove ' . escapeshellarg($entry));
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');

            throw new \RuntimeException($detail !== '' ? $detail : 'cipi api ip-whitelist remove failed');
        }

        return $this->status();
    }

    public function allowAll(): array
    {
        $result = $this->cli->run('api ip-whitelist allow-all');
        if ($result['exit_code'] !== 0) {
            $detail = trim($result['output'] ?? '');

            throw new \RuntimeException($detail !== '' ? $detail : 'cipi api ip-whitelist allow-all failed');
        }

        return $this->status();
    }

    public function isValidEntry(string $entry): bool
    {
        if ($entry === '*') {
            return true;
        }

        if (str_contains($entry, '/')) {
            [$ip, $prefix] = explode('/', $entry, 2);
            if (! ctype_digit($prefix)) {
                return false;
            }
            $prefix = (int) $prefix;
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $prefix >= 0 && $prefix <= 32;
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return $prefix >= 0 && $prefix <= 128;
            }

            return false;
        }

        return filter_var($entry, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * @return list<string>
     */
    protected function readEntries(): array
    {
        $path = $this->filePath();
        $content = $this->readFile($path);
        if ($content === null) {
            return ['*'];
        }

        $entries = [];
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (($hash = strpos($line, '#')) !== false) {
                $line = trim(substr($line, 0, $hash));
            }
            $line = preg_replace('/\s+/', '', $line) ?? '';
            if ($line !== '') {
                $entries[] = $line;
            }
        }

        return $entries;
    }

    /**
     * @param  list<string>  $entries
     */
    protected function isAllowAll(array $entries): bool
    {
        if ($entries === []) {
            return true;
        }

        return in_array('*', $entries, true);
    }

    /**
     * @param  list<string>  $entries
     * @return list<string>
     */
    protected function normalizeEntries(array $entries): array
    {
        $out = [];
        foreach ($entries as $entry) {
            if (! is_string($entry)) {
                continue;
            }
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            if (! $this->isValidEntry($entry)) {
                throw new \InvalidArgumentException('Invalid IP or CIDR: '.$entry);
            }
            if ($entry === '*') {
                return ['*'];
            }
            if (! in_array($entry, $out, true)) {
                $out[] = $entry;
            }
        }

        return $out;
    }

    protected function readFile(string $path): ?string
    {
        if (! file_exists($path)) {
            return null;
        }

        if (is_readable($path)) {
            $content = file_get_contents($path);

            return $content !== false ? $content : null;
        }

        $escaped = escapeshellarg($path);
        $output = [];
        exec("sudo /bin/cat {$escaped} 2>/dev/null", $output, $exitCode);

        return $exitCode === 0 ? implode("\n", $output) : null;
    }

    public function ipMatches(string $ip, string $rule): bool
    {
        if ($rule === '*') {
            return true;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (! str_contains($rule, '/')) {
            return $this->ipsEqual($ip, $rule);
        }

        [$subnet, $prefix] = explode('/', $rule, 2);
        if (! ctype_digit($prefix) || ! filter_var($subnet, FILTER_VALIDATE_IP)) {
            return false;
        }

        $prefix = (int) $prefix;
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $maxBits = strlen($ipBin) * 8;
        if ($prefix < 0 || $prefix > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (~((1 << (8 - $remainingBits)) - 1)) & 0xFF;

        return (ord($ipBin[$fullBytes]) & $mask) === (ord($subnetBin[$fullBytes]) & $mask);
    }

    protected function ipsEqual(string $a, string $b): bool
    {
        $pa = inet_pton($a);
        $pb = inet_pton($b);
        if ($pa === false || $pb === false) {
            return false;
        }

        return $pa === $pb;
    }
}
