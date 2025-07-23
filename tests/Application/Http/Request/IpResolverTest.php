<?php

declare(strict_types=1);

namespace App\Tests\Application\Http\Request;

use App\Application\Http\Request\IpResolver;
use App\Infrastructure\Config\Config;
use OpenSwoole\Http\Request;
use PHPUnit\Framework\TestCase;

class IpResolverTest extends TestCase
{
    public function testResolveClientIpWithoutTrustedProxies(): void
    {
        $config = new Config([
            'security.trusted_proxies' => [],
            'security.trust_forwarded_headers' => false
        ]);
        
        $resolver = new IpResolver($config);
        $request = $this->createMock(Request::class);
        $request->server = ['remote_addr' => '203.0.113.1'];
        $request->header = ['x-forwarded-for' => '198.51.100.1'];
        
        $result = $resolver->resolveClientIp($request);
        
        $this->assertEquals('203.0.113.1', $result);
    }

    public function testResolveClientIpWithTrustedProxyButDisabledHeaders(): void
    {
        $config = new Config([
            'security.trusted_proxies' => ['203.0.113.0/24'],
            'security.trust_forwarded_headers' => false
        ]);
        
        $resolver = new IpResolver($config);
        $request = $this->createMock(Request::class);
        $request->server = ['remote_addr' => '203.0.113.1'];
        $request->header = ['x-forwarded-for' => '198.51.100.1'];
        
        $result = $resolver->resolveClientIp($request);
        
        $this->assertEquals('203.0.113.1', $result);
    }

    public function testResolveClientIpFromTrustedProxy(): void
    {
        $config = new Config([
            'security.trusted_proxies' => ['203.0.113.0/24'],
            'security.trust_forwarded_headers' => true,
            'security.forwarded_header' => 'x-forwarded-for'
        ]);
        
        $resolver = new IpResolver($config);
        $request = $this->createMock(Request::class);
        $request->server = ['remote_addr' => '203.0.113.1']; // Trusted proxy
        $request->header = ['x-forwarded-for' => '198.51.100.1']; // Real client
        
        $result = $resolver->resolveClientIp($request);
        
        $this->assertEquals('198.51.100.1', $result);
    }

    public function testResolveClientIpFromUntrustedProxy(): void
    {
        $config = new Config([
            'security.trusted_proxies' => ['203.0.113.0/24'],
            'security.trust_forwarded_headers' => true
        ]);
        
        $resolver = new IpResolver($config);
        $request = $this->createMock(Request::class);
        $request->server = ['remote_addr' => '192.168.1.1']; // Not trusted
        $request->header = ['x-forwarded-for' => '198.51.100.1'];
        
        $result = $resolver->resolveClientIp($request);
        
        $this->assertEquals('192.168.1.1', $result);
    }

    public function testResolveClientIpWithMultipleForwardedIps(): void
    {
        $config = new Config([
            'security.trusted_proxies' => ['203.0.113.1'],
            'security.trust_forwarded_headers' => true
        ]);
        
        $resolver = new IpResolver($config);
        $request = $this->createMock(Request::class);
        $request->server = ['remote_addr' => '203.0.113.1'];
        $request->header = ['x-forwarded-for' => '198.51.100.1, 203.0.113.2, 203.0.113.3'];
        
        $result = $resolver->resolveClientIp($request);
        
        $this->assertEquals('198.51.100.1', $result);
    }

    public function testResolveClientIpWithPrivateIpInChain(): void
    {
        $config = new Config([
            'security.trusted_proxies' => ['203.0.113.1'],
            'security.trust_forwarded_headers' => true
        ]);
        
        $resolver = new IpResolver($config);
        $request = $this->createMock(Request::class);
        $request->server = ['remote_addr' => '203.0.113.1'];
        $request->header = ['x-forwarded-for' => '192.168.1.1, 198.51.100.1'];
        
        $result = $resolver->resolveClientIp($request);
        
        // Should skip private IP and use the public one
        $this->assertEquals('198.51.100.1', $result);
    }

    public function testResolveClientIpWithOnlyPrivateIps(): void
    {
        $config = new Config([
            'security.trusted_proxies' => ['203.0.113.1'],
            'security.trust_forwarded_headers' => true
        ]);
        
        $resolver = new IpResolver($config);
        $request = $this->createMock(Request::class);
        $request->server = ['remote_addr' => '203.0.113.1'];
        $request->header = ['x-forwarded-for' => '192.168.1.1, 10.0.0.1'];
        
        $result = $resolver->resolveClientIp($request);
        
        // Should fall back to remote_addr when no public IPs found
        $this->assertEquals('203.0.113.1', $result);
    }

    public function testResolveClientIpWithMissingForwardedHeader(): void
    {
        $config = new Config([
            'security.trusted_proxies' => ['203.0.113.1'],
            'security.trust_forwarded_headers' => true
        ]);
        
        $resolver = new IpResolver($config);
        $request = $this->createMock(Request::class);
        $request->server = ['remote_addr' => '203.0.113.1'];
        $request->header = []; // No forwarded header
        
        $result = $resolver->resolveClientIp($request);
        
        $this->assertEquals('203.0.113.1', $result);
    }

    public function testResolveClientIpWithCustomForwardedHeader(): void
    {
        $config = new Config([
            'security.trusted_proxies' => ['203.0.113.1'],
            'security.trust_forwarded_headers' => true,
            'security.forwarded_header' => 'x-real-ip'
        ]);
        
        $resolver = new IpResolver($config);
        $request = $this->createMock(Request::class);
        $request->server = ['remote_addr' => '203.0.113.1'];
        $request->header = ['x-real-ip' => '198.51.100.1'];
        
        $result = $resolver->resolveClientIp($request);
        
        $this->assertEquals('198.51.100.1', $result);
    }

    public function testIpv4CidrMatching(): void
    {
        $config = new Config([
            'security.trusted_proxies' => ['203.0.113.0/24'],
            'security.trust_forwarded_headers' => true
        ]);
        
        $resolver = new IpResolver($config);
        $request = $this->createMock(Request::class);
        
        // Test IP within CIDR range
        $request->server = ['remote_addr' => '203.0.113.50'];
        $request->header = ['x-forwarded-for' => '198.51.100.1'];
        
        $result = $resolver->resolveClientIp($request);
        $this->assertEquals('198.51.100.1', $result);
        
        // Test IP outside CIDR range
        $request->server = ['remote_addr' => '203.0.114.1'];
        $result2 = $resolver->resolveClientIp($request);
        $this->assertEquals('203.0.114.1', $result2);
    }

    public function testExactIpMatching(): void
    {
        $config = new Config([
            'security.trusted_proxies' => ['203.0.113.1', '203.0.113.2'],
            'security.trust_forwarded_headers' => true
        ]);
        
        $resolver = new IpResolver($config);
        $request = $this->createMock(Request::class);
        
        // Test exact match
        $request->server = ['remote_addr' => '203.0.113.1'];
        $request->header = ['x-forwarded-for' => '198.51.100.1'];
        
        $result = $resolver->resolveClientIp($request);
        $this->assertEquals('198.51.100.1', $result);
        
        // Test non-match
        $request->server = ['remote_addr' => '203.0.113.3'];
        $result2 = $resolver->resolveClientIp($request);
        $this->assertEquals('203.0.113.3', $result2);
    }

    public function testMultipleTrustedProxies(): void
    {
        $config = new Config([
            'security.trusted_proxies' => ['203.0.113.0/24', '198.51.100.1', '192.168.1.0/24'],
            'security.trust_forwarded_headers' => true
        ]);
        
        $resolver = new IpResolver($config);
        $request = $this->createMock(Request::class);
        $request->header = ['x-forwarded-for' => '8.8.8.8'];
        
        // Test different trusted proxy types
        $testCases = [
            '203.0.113.50',   // CIDR range
            '198.51.100.1',   // Exact IP
            '192.168.1.100'   // Another CIDR range
        ];
        
        foreach ($testCases as $proxyIp) {
            $request->server = ['remote_addr' => $proxyIp];
            $result = $resolver->resolveClientIp($request);
            $this->assertEquals('8.8.8.8', $result, "Failed for proxy IP: $proxyIp");
        }
    }

    public function testMalformedForwardedHeader(): void
    {
        $config = new Config([
            'security.trusted_proxies' => ['203.0.113.1'],
            'security.trust_forwarded_headers' => true
        ]);
        
        $resolver = new IpResolver($config);
        $request = $this->createMock(Request::class);
        $request->server = ['remote_addr' => '203.0.113.1'];
        
        // Test various malformed headers
        $malformedHeaders = [
            'invalid-ip',
            'not.an.ip.address',
            '999.999.999.999',
            '',
            '   ',
        ];
        
        foreach ($malformedHeaders as $header) {
            $request->header = ['x-forwarded-for' => $header];
            $result = $resolver->resolveClientIp($request);
            $this->assertEquals('203.0.113.1', $result, "Failed for malformed header: '$header'");
        }
    }

    public function testMissingRemoteAddr(): void
    {
        $config = new Config([
            'security.trusted_proxies' => [],
            'security.trust_forwarded_headers' => false
        ]);
        
        $resolver = new IpResolver($config);
        $request = $this->createMock(Request::class);
        $request->server = []; // No remote_addr
        
        $result = $resolver->resolveClientIp($request);
        
        $this->assertEquals('127.0.0.1', $result);
    }

    public function testConfigurationDefaults(): void
    {
        $config = new Config([]); // Empty config
        $resolver = new IpResolver($config);
        $request = $this->createMock(Request::class);
        $request->server = ['remote_addr' => '203.0.113.1'];
        $request->header = ['x-forwarded-for' => '198.51.100.1'];
        
        // Should use remote_addr since trust_forwarded_headers defaults to false
        $result = $resolver->resolveClientIp($request);
        
        $this->assertEquals('203.0.113.1', $result);
    }
}