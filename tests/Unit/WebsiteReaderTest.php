<?php

namespace Tests\Unit;

use App\Services\WebsiteReader;
use PHPUnit\Framework\TestCase;

class WebsiteReaderTest extends TestCase
{
    public function test_public_ip_classification(): void
    {
        foreach (['93.184.216.34', '8.8.8.8', '2606:4700:4700::1111'] as $ip) {
            $this->assertTrue(WebsiteReader::isPublicIp($ip), $ip);
        }

        foreach ([
            '127.0.0.1', '10.1.2.3', '172.16.0.1', '192.168.1.1', '169.254.169.254', '0.0.0.0',
            '100.64.0.1', '::1', 'fe80::1', 'fc00::1', '::ffff:127.0.0.1',
        ] as $ip) {
            $this->assertFalse(WebsiteReader::isPublicIp($ip), $ip);
        }
    }
}
