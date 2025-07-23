<?php

declare(strict_types=1);

namespace App\Tests\Application\Http\Response;

use App\Application\Http\Request\RequestMeta;
use App\Application\Http\Response\SuccessResponseDto;
use PHPUnit\Framework\TestCase;

class SuccessResponseDtoTest extends TestCase
{
    private RequestMeta $requestMeta;
    private SuccessResponseDto $dto;

    protected function setUp(): void
    {
        $this->requestMeta = new RequestMeta(
            'GET',
            '/api/test',
            '192.168.1.1',
            '2025-01-01 12:00:00',
            'success-request-456'
        );
        
        $this->dto = new SuccessResponseDto(
            'Load balancer is working',
            '2025-01-01T12:00:00+00:00',
            'http://localhost:8080',
            $this->requestMeta
        );
    }

    public function testConstructorSetsProperties(): void
    {
        $this->assertEquals('Load balancer is working', $this->dto->message);
        $this->assertEquals('2025-01-01T12:00:00+00:00', $this->dto->timestamp);
        $this->assertEquals('http://localhost:8080', $this->dto->targetServer);
        $this->assertSame($this->requestMeta, $this->dto->requestMeta);
    }

    public function testJsonSerialize(): void
    {
        $expected = [
            'success' => true,
            'message' => 'Load balancer is working',
            'timestamp' => '2025-01-01T12:00:00+00:00',
            'request_id' => 'success-request-456',
            'target_server' => 'http://localhost:8080',
            'data' => [
                'path' => '/api/test',
                'method' => 'GET',
                'client_ip' => '192.168.1.1'
            ]
        ];

        $this->assertEquals($expected, $this->dto->jsonSerialize());
    }

    public function testJsonEncode(): void
    {
        $json = json_encode($this->dto);
        $decoded = json_decode($json, true);

        $this->assertTrue($decoded['success']);
        $this->assertEquals('Load balancer is working', $decoded['message']);
        $this->assertEquals('http://localhost:8080', $decoded['target_server']);
        $this->assertEquals('/api/test', $decoded['data']['path']);
        $this->assertEquals('GET', $decoded['data']['method']);
        $this->assertEquals('192.168.1.1', $decoded['data']['client_ip']);
    }
}