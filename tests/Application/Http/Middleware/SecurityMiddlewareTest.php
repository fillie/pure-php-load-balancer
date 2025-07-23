<?php

declare(strict_types=1);

namespace App\Tests\Application\Http\Middleware;

use App\Application\Http\Middleware\SecurityMiddleware;
use App\Infrastructure\Config\Config;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use PHPUnit\Framework\TestCase;

class SecurityMiddlewareTest extends TestCase
{
    private Config $config;
    private SecurityMiddleware $middleware;

    protected function setUp(): void
    {
        $this->config = new Config([
            'security.max_request_size' => 1024, // 1KB for testing
            'security.rate_limit.enabled' => true,
            'security.rate_limit.requests' => 3,
            'security.rate_limit.window' => 60
        ]);
        
        $this->middleware = new SecurityMiddleware($this->config);
    }

    public function testValidRequestPasses(): void
    {
        $request = $this->createMock(Request::class);
        $request->header = ['content-length' => '500']; // Under limit
        
        $result = $this->middleware->validateRequest($request, '192.168.1.1');
        
        $this->assertNull($result);
    }

    public function testRequestTooLargeReturns413(): void
    {
        $request = $this->createMock(Request::class);
        $request->header = ['content-length' => '2048']; // Over 1KB limit
        
        $result = $this->middleware->validateRequest($request, '192.168.1.1');
        
        $this->assertNotNull($result);
        $this->assertEquals(413, $result['status']);
        $this->assertEquals('Request entity too large', $result['error']);
        $this->assertEquals(1024, $result['details']['max_size']);
        $this->assertEquals(2048, $result['details']['request_size']);
    }

    public function testRequestWithoutContentLengthPasses(): void
    {
        $request = $this->createMock(Request::class);
        $request->header = []; // No content-length header
        
        $result = $this->middleware->validateRequest($request, '192.168.1.1');
        
        $this->assertNull($result);
    }

    public function testRateLimitingAllowsRequestsUnderLimit(): void
    {
        $request = $this->createMock(Request::class);
        $request->header = ['content-length' => '100'];
        
        // First 3 requests should pass
        for ($i = 0; $i < 3; $i++) {
            $result = $this->middleware->validateRequest($request, '192.168.1.1');
            $this->assertNull($result, "Request $i should pass");
        }
    }

    public function testRateLimitingBlocksRequestsOverLimit(): void
    {
        $request = $this->createMock(Request::class);
        $request->header = ['content-length' => '100'];
        
        // Use up the rate limit
        for ($i = 0; $i < 3; $i++) {
            $this->middleware->validateRequest($request, '192.168.1.1');
        }
        
        // 4th request should be blocked
        $result = $this->middleware->validateRequest($request, '192.168.1.1');
        
        $this->assertNotNull($result);
        $this->assertEquals(429, $result['status']);
        $this->assertEquals('Rate limit exceeded', $result['error']);
        $this->assertEquals(3, $result['details']['limit']);
        $this->assertEquals(60, $result['details']['window']);
        $this->assertArrayHasKey('reset_time', $result['details']);
    }

    public function testRateLimitingPerIpSeparation(): void
    {
        $request = $this->createMock(Request::class);
        $request->header = ['content-length' => '100'];
        
        // Use up rate limit for IP1
        for ($i = 0; $i < 3; $i++) {
            $this->middleware->validateRequest($request, '192.168.1.1');
        }
        
        // IP1 should be blocked
        $result1 = $this->middleware->validateRequest($request, '192.168.1.1');
        $this->assertNotNull($result1);
        $this->assertEquals(429, $result1['status']);
        
        // IP2 should still work
        $result2 = $this->middleware->validateRequest($request, '192.168.1.2');
        $this->assertNull($result2);
    }

    public function testRateLimitingDisabled(): void
    {
        $config = new Config([
            'security.max_request_size' => 1024,
            'security.rate_limit.enabled' => false,
            'security.rate_limit.requests' => 1,
            'security.rate_limit.window' => 60
        ]);
        
        $middleware = new SecurityMiddleware($config);
        $request = $this->createMock(Request::class);
        $request->header = ['content-length' => '100'];
        
        // Should allow many requests when rate limiting is disabled
        for ($i = 0; $i < 10; $i++) {
            $result = $middleware->validateRequest($request, '192.168.1.1');
            $this->assertNull($result, "Request $i should pass when rate limiting disabled");
        }
    }

    public function testSendSecurityErrorForRequestTooLarge(): void
    {
        $response = $this->createMock(Response::class);
        $error = [
            'error' => 'Request entity too large',
            'status' => 413,
            'details' => ['max_size' => 1024, 'request_size' => 2048]
        ];
        
        $response->expects($this->once())
            ->method('header')
            ->with('Content-Type', 'application/json');
            
        $response->expects($this->once())
            ->method('status')
            ->with(413);
            
        $response->expects($this->once())
            ->method('end')
            ->with($this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['success'] === false && 
                       $data['error'] === 'Request entity too large' &&
                       isset($data['timestamp']);
            }));
        
        $this->middleware->sendSecurityError($response, $error);
    }

    public function testSendSecurityErrorForRateLimit(): void
    {
        $response = $this->createMock(Response::class);
        $resetTime = time() + 30;
        $error = [
            'error' => 'Rate limit exceeded',
            'status' => 429,
            'details' => [
                'limit' => 3,
                'window' => 60,
                'reset_time' => $resetTime
            ]
        ];
        
        $expectedHeaders = [
            ['Content-Type', 'application/json'],
            ['Retry-After', (string)($resetTime - time())],
            ['X-RateLimit-Limit', '3'],
            ['X-RateLimit-Window', '60']
        ];
        
        $response->expects($this->exactly(4))
            ->method('header')
            ->willReturnCallback(function ($name, $value) use (&$expectedHeaders) {
                $expected = array_shift($expectedHeaders);
                $this->assertEquals($expected[0], $name);
                $this->assertEquals($expected[1], $value);
                return true; // OpenSwoole Response::header() returns bool
            });
            
        $response->expects($this->once())
            ->method('status')
            ->with(429);
            
        $response->expects($this->once())
            ->method('end')
            ->with($this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['success'] === false && 
                       $data['error'] === 'Rate limit exceeded';
            }));
        
        $this->middleware->sendSecurityError($response, $error);
    }

    public function testCleanupRemovesOldEntries(): void
    {
        $request = $this->createMock(Request::class);
        $request->header = ['content-length' => '100'];
        
        // Add some requests
        $this->middleware->validateRequest($request, '192.168.1.1');
        $this->middleware->validateRequest($request, '192.168.1.2');
        
        // Cleanup should not break anything
        $this->middleware->cleanup();
        
        // Should still be able to add more requests
        $result = $this->middleware->validateRequest($request, '192.168.1.1');
        $this->assertNull($result);
    }

    public function testConfigurationDefaults(): void
    {
        $config = new Config([]); // Empty config to test defaults
        $middleware = new SecurityMiddleware($config);
        $request = $this->createMock(Request::class);
        
        // Test with very large request to see if default 1MB limit applies
        $request->header = ['content-length' => '2097152']; // 2MB
        
        $result = $middleware->validateRequest($request, '192.168.1.1');
        
        $this->assertNotNull($result);
        $this->assertEquals(413, $result['status']);
    }
}