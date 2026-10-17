<?php

namespace App\Support;

use InvalidArgumentException;

/** SSRF protection for user-supplied pull URLs. */
class UrlGuard
{
    /**
     * Validates the URL and returns [host, port, ip] to pin the connection to the vetted IP (anti DNS-rebinding).
     *
     * @return array{0:string,1:int,2:string}
     */
    public function check(string $url): array
    {
        $p = parse_url($url);
        if (! $p || ! isset($p['host'], $p['scheme']) || ! in_array(strtolower($p['scheme']), ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only http(s) URLs are allowed.');
        }
        if (isset($p['user']) || isset($p['pass'])) {
            throw new InvalidArgumentException('Credentials in the URL are not allowed.');
        }

        $host = trim($p['host'], '[]');
        $port = $p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80);
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);

        if ($ips === []) {
            throw new InvalidArgumentException('Host could not be resolved.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('URL points to a private or reserved address.');
            }
        }

        return [$host, $port, $ips[0]];
    }

    /** @return list<string> */
    protected function resolve(string $host): array
    {
        return gethostbynamel($host) ?: [];
    }
}
