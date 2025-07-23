<?php

declare(strict_types=1);

namespace App\Tests\Application\Http\Request;

use App\Application\Http\Request\RequestMeta;
use App\Infrastructure\Clock\ClockInterface;
use OpenSwoole\Http\Request;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RequestMetaTest extends TestCase
{
    private function createMockClock(string $timestamp = '2023-12-25 10:30:00'): ClockInterface
    {
        $clock = $this->createMock(ClockInterface::class);
        $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $timestamp);
        $clock->method('now')->willReturn($dateTime);
        return $clock;
    }

    public function testConstructor(): void
    {
        $method = 'POST';
        $path = '/api/users';
        $clientIp = '192.168.1.100';
        $timestamp = '2023-12-25 10:30:00';
        $requestId = 'test-request-id-123';
        
        $requestMeta = new RequestMeta($method, $path, $clientIp, $timestamp, $requestId);
        
        $this->assertEquals($method, $requestMeta->method);
        $this->assertEquals($path, $requestMeta->path);
        $this->assertEquals($clientIp, $requestMeta->clientIp);
        $this->assertEquals($timestamp, $requestMeta->timestamp);
        $this->assertEquals($requestId, $requestMeta->requestId);
    }

    public function testFromSwooleRequestWithCompleteData(): void
    {
        $clock = $this->createMockClock('2023-12-25 15:30:45');
        $request = $this->createMock(Request::class);
        $request->server = [
            'request_method' => 'PUT',
            'request_uri' => '/api/products/123',
            'remote_addr' => '10.0.0.50'
        ];
        
        $requestMeta = RequestMeta::fromSwooleRequest($request, $clock);
        
        $this->assertEquals('PUT', $requestMeta->method);
        $this->assertEquals('/api/products/123', $requestMeta->path);
        $this->assertEquals('10.0.0.50', $requestMeta->clientIp);
        $this->assertEquals('2023-12-25 15:30:45', $requestMeta->timestamp);
        $this->assertNotEmpty($requestMeta->requestId);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $requestMeta->requestId);
    }

    public function testFromSwooleRequestWithDefaults(): void
    {
        $clock = $this->createMockClock('2023-11-15 12:00:00');
        $request = $this->createMock(Request::class);
        $request->server = []; // Empty server data
        
        $requestMeta = RequestMeta::fromSwooleRequest($request, $clock);
        
        $this->assertEquals('GET', $requestMeta->method); // Default method
        $this->assertEquals('/', $requestMeta->path); // Default path
        $this->assertEquals('unknown', $requestMeta->clientIp); // Default IP
        $this->assertEquals('2023-11-15 12:00:00', $requestMeta->timestamp);
        $this->assertNotEmpty($requestMeta->requestId);
    }

    public function testFromSwooleRequestWithPartialData(): void
    {
        $clock = $this->createMockClock();
        $request = $this->createMock(Request::class);
        $request->server = [
            'request_method' => 'DELETE',
            // Missing request_uri and remote_addr
        ];
        
        $requestMeta = RequestMeta::fromSwooleRequest($request, $clock);
        
        $this->assertEquals('DELETE', $requestMeta->method);
        $this->assertEquals('/', $requestMeta->path); // Default
        $this->assertEquals('unknown', $requestMeta->clientIp); // Default
    }

    public function testFromSwooleRequestWithMissingServerArray(): void
    {
        $clock = $this->createMockClock();
        $request = $this->createMock(Request::class);
        // server property not set
        
        $requestMeta = RequestMeta::fromSwooleRequest($request, $clock);
        
        $this->assertEquals('GET', $requestMeta->method);
        $this->assertEquals('/', $requestMeta->path);
        $this->assertEquals('unknown', $requestMeta->clientIp);
    }

    public function testToArray(): void
    {
        $method = 'PATCH';
        $path = '/api/orders/456';
        $clientIp = '172.16.0.25';
        $timestamp = '2023-11-15 14:45:30';
        $requestId = 'test-request-456';
        
        $requestMeta = new RequestMeta($method, $path, $clientIp, $timestamp, $requestId);
        $array = $requestMeta->toArray();
        
        $expectedArray = [
            'method' => $method,
            'path' => $path,
            'client_ip' => $clientIp,
            'timestamp' => $timestamp,
            'request_id' => $requestId
        ];
        
        $this->assertEquals($expectedArray, $array);
    }

    public function testToArrayStructure(): void
    {
        $requestMeta = new RequestMeta('GET', '/', 'unknown', '2023-01-01 00:00:00', 'test-id');
        $array = $requestMeta->toArray();
        
        // Verify all required keys are present
        $this->assertArrayHasKey('method', $array);
        $this->assertArrayHasKey('path', $array);
        $this->assertArrayHasKey('client_ip', $array);
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertArrayHasKey('request_id', $array);
        
        // Verify no extra keys
        $this->assertCount(5, $array);
    }

    public function testToString(): void
    {
        $method = 'POST';
        $path = '/auth/login';
        $clientIp = '203.0.113.42';
        $timestamp = '2023-09-20 16:20:15';
        $requestId = 'req-123-abc';
        
        $requestMeta = new RequestMeta($method, $path, $clientIp, $timestamp, $requestId);
        $string = (string)$requestMeta;
        
        $expectedString = sprintf('[%s] %s %s %s %s', $requestId, $timestamp, $clientIp, $method, $path);
        $this->assertEquals($expectedString, $string);
    }

    public function testToStringFormat(): void
    {
        $requestMeta = new RequestMeta('GET', '/health', '127.0.0.1', '2023-12-01 12:00:00', 'req-health-1');
        $string = (string)$requestMeta;
        
        // Should follow format: "[requestId] timestamp clientIp method path"
        $this->assertEquals('[req-health-1] 2023-12-01 12:00:00 127.0.0.1 GET /health', $string);
    }

    public function testReadonlyProperties(): void
    {
        $requestMeta = new RequestMeta('GET', '/', '127.0.0.1', '2023-01-01 00:00:00', 'test-id');
        
        // These should not cause errors since properties are readonly
        $this->expectNotToPerformAssertions();
        
        // Accessing properties should work
        $method = $requestMeta->method;
        $path = $requestMeta->path;
        $clientIp = $requestMeta->clientIp;
        $timestamp = $requestMeta->timestamp;
        $requestId = $requestMeta->requestId;
    }

    public function testFromSwooleRequestWithComplexUri(): void
    {
        $clock = $this->createMockClock();
        $request = $this->createMock(Request::class);
        $request->server = [
            'request_method' => 'GET',
            'request_uri' => '/api/v1/users/search?name=john&active=true&page=2',
            'remote_addr' => '198.51.100.42'
        ];
        
        $requestMeta = RequestMeta::fromSwooleRequest($request, $clock);
        
        // Should preserve the full URI including query parameters
        $this->assertEquals('/api/v1/users/search?name=john&active=true&page=2', $requestMeta->path);
    }

    public function testFromSwooleRequestWithDifferentHttpMethods(): void
    {
        $clock = $this->createMockClock();
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
        
        foreach ($methods as $method) {
            $request = $this->createMock(Request::class);
            $request->server = ['request_method' => $method];
            
            $requestMeta = RequestMeta::fromSwooleRequest($request, $clock);
            
            $this->assertEquals($method, $requestMeta->method, "Failed for method: {$method}");
        }
    }

    public function testFromSwooleRequestWithVariousIpAddresses(): void
    {
        $clock = $this->createMockClock();
        $ipAddresses = [
            '127.0.0.1',           // localhost
            '192.168.1.100',       // private IPv4
            '10.0.0.5',            // private IPv4
            '203.0.113.42',        // public IPv4
            '::1',                 // localhost IPv6
            '2001:db8::1',         // IPv6
            'fe80::1%lo0'          // IPv6 with zone
        ];
        
        foreach ($ipAddresses as $ip) {
            $request = $this->createMock(Request::class);
            $request->server = ['remote_addr' => $ip];
            
            $requestMeta = RequestMeta::fromSwooleRequest($request, $clock);
            
            $this->assertEquals($ip, $requestMeta->clientIp, "Failed for IP: {$ip}");
        }
    }

    public function testFromSwooleRequestWithVariousPaths(): void
    {
        $clock = $this->createMockClock();
        $paths = [
            '/',
            '/api',
            '/api/v1/users',
            '/api/v1/users/123',
            '/complex/path/with/many/segments',
            '/path-with-dashes',
            '/path_with_underscores',
            '/path.with.dots',
            '/path%20with%20encoded%20spaces',
            '/api/users?filter=active&sort=name'
        ];
        
        foreach ($paths as $path) {
            $request = $this->createMock(Request::class);
            $request->server = ['request_uri' => $path];
            
            $requestMeta = RequestMeta::fromSwooleRequest($request, $clock);
            
            $this->assertEquals($path, $requestMeta->path, "Failed for path: {$path}");
        }
    }

    public function testTimestampConsistency(): void
    {  
        $clock1 = $this->createMockClock('2023-12-25 10:30:00');
        $clock2 = $this->createMockClock('2023-12-25 10:30:01');
        
        $request = $this->createMock(Request::class);
        $request->server = [
            'request_method' => 'GET',
            'request_uri' => '/test',
            'remote_addr' => '127.0.0.1'
        ];
        
        $requestMeta1 = RequestMeta::fromSwooleRequest($request, $clock1);
        $requestMeta2 = RequestMeta::fromSwooleRequest($request, $clock2);
        
        // Timestamps should be different (based on different clocks)
        $this->assertNotEquals($requestMeta1->timestamp, $requestMeta2->timestamp);
        
        // Both should be valid timestamp formats
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $requestMeta1->timestamp);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $requestMeta2->timestamp);
        
        // Should use the clock provided timestamps
        $this->assertEquals('2023-12-25 10:30:00', $requestMeta1->timestamp);
        $this->assertEquals('2023-12-25 10:30:01', $requestMeta2->timestamp);
    }
}