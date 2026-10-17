<?php

use App\Support\UrlGuard;

it('accepts public IP literals and returns the pinned address', function () {
    expect((new UrlGuard)->check('https://93.184.216.34:8443/x'))->toBe(['93.184.216.34', 8443, '93.184.216.34']);
});

it('uses default ports by scheme', function () {
    expect((new UrlGuard)->check('http://93.184.216.34/x')[1])->toBe(80)
        ->and((new UrlGuard)->check('https://93.184.216.34/x')[1])->toBe(443);
});

it('rejects loopback, private, link-local and reserved ranges', function (string $ip) {
    (new UrlGuard)->check("http://{$ip}/");
})->with(['127.0.0.1', '10.1.2.3', '172.16.0.1', '192.168.1.1', '169.254.169.254', '0.0.0.0'])->throws(InvalidArgumentException::class);
