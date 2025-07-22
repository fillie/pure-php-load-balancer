<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\JsonResponse;
use OpenSwoole\Http\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class JsonResponseTest extends TestCase
{
    private Response|MockObject $mockResponse;
    private JsonResponse $jsonResponse;

    protected function setUp(): void
    {
        $this->mockResponse = $this->createMock(Response::class);
        $this->jsonResponse = new JsonResponse($this->mockResponse);
    }

    public function testSendBasicResponse(): void
    {
        $data = ['message' => 'test', 'status' => 'ok'];
        
        $this->mockResponse
            ->expects($this->once())
            ->method('status')
            ->with(200);
            
        $this->mockResponse
            ->expects($this->once())
            ->method('header')
            ->with('Content-Type', 'application/json');
            
        $this->mockResponse
            ->expects($this->once())
            ->method('end')
            ->with(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $this->jsonResponse->send($data);
    }

    public function testSendWithCustomStatus(): void
    {
        $data = ['error' => 'not found'];
        $status = 404;
        
        $this->mockResponse
            ->expects($this->once())
            ->method('status')
            ->with($status);
            
        $this->mockResponse
            ->expects($this->once())
            ->method('header')
            ->with('Content-Type', 'application/json');
            
        $this->mockResponse
            ->expects($this->once())
            ->method('end')
            ->with(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $this->jsonResponse->send($data, $status);
    }

    public function testSendWithCustomHeaders(): void
    {
        $data = ['data' => 'test'];
        $headers = ['X-Custom-Header' => 'value', 'Cache-Control' => 'no-cache'];
        
        $this->mockResponse
            ->expects($this->once())
            ->method('status')
            ->with(200);
            
        $this->mockResponse
            ->expects($this->exactly(3))
            ->method('header')
            ->willReturnCallback(function ($name, $value) use ($headers) {
                static $callCount = 0;
                $callCount++;
                
                if ($callCount === 1) {
                    $this->assertEquals('Content-Type', $name);
                    $this->assertEquals('application/json', $value);
                } elseif ($callCount === 2) {
                    $this->assertTrue(isset($headers[$name]));
                    $this->assertEquals($headers[$name], $value);
                } elseif ($callCount === 3) {
                    $this->assertTrue(isset($headers[$name]));
                    $this->assertEquals($headers[$name], $value);
                }
                
                return true;
            });
            
        $this->mockResponse
            ->expects($this->once())
            ->method('end')
            ->with(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $this->jsonResponse->send($data, 200, $headers);
    }

    public function testSendSuccessWithMinimalData(): void
    {
        $message = 'Operation successful';
        
        $this->mockResponse
            ->expects($this->once())
            ->method('status')
            ->with(200);
            
        $this->mockResponse
            ->expects($this->once())
            ->method('header')
            ->with('Content-Type', 'application/json');
            
        $this->mockResponse
            ->expects($this->once())
            ->method('end')
            ->with($this->callback(function ($json) use ($message) {
                $data = json_decode($json, true);
                return $data['success'] === true &&
                       $data['message'] === $message &&
                       isset($data['timestamp']) &&
                       isset($data['data']) &&
                       $data['data'] === [] &&
                       !isset($data['target_server']);
            }));

        $this->jsonResponse->sendSuccess($message);
    }

    public function testSendSuccessWithData(): void
    {
        $message = 'Operation successful';
        $data = ['user_id' => 123, 'name' => 'John'];
        
        $this->mockResponse
            ->expects($this->once())
            ->method('status')
            ->with(200);
            
        $this->mockResponse
            ->expects($this->once())
            ->method('end')
            ->with($this->callback(function ($json) use ($message, $data) {
                $responseData = json_decode($json, true);
                return $responseData['success'] === true &&
                       $responseData['message'] === $message &&
                       $responseData['data'] === $data &&
                       !isset($responseData['target_server']);
            }));

        $this->jsonResponse->sendSuccess($message, $data);
    }

    public function testSendSuccessWithTargetServer(): void
    {
        $message = 'Request routed';
        $data = ['path' => '/api/test'];
        $targetServer = 'http://backend:3000';
        
        $this->mockResponse
            ->expects($this->once())
            ->method('end')
            ->with($this->callback(function ($json) use ($message, $data, $targetServer) {
                $responseData = json_decode($json, true);
                return $responseData['success'] === true &&
                       $responseData['message'] === $message &&
                       $responseData['data'] === $data &&
                       $responseData['target_server'] === $targetServer;
            }));

        $this->jsonResponse->sendSuccess($message, $data, $targetServer);
    }

    public function testSendSuccessTimestampFormat(): void
    {
        $this->mockResponse
            ->expects($this->once())
            ->method('end')
            ->with($this->callback(function ($json) {
                $data = json_decode($json, true);
                // Verify ISO 8601 format (RFC 3339)
                return isset($data['timestamp']) &&
                       preg_match('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}/', $data['timestamp']) === 1;
            }));

        $this->jsonResponse->sendSuccess('Test message');
    }

    public function testSendErrorWithDefaults(): void
    {
        $errorMessage = 'Something went wrong';
        
        $this->mockResponse
            ->expects($this->once())
            ->method('status')
            ->with(500);
            
        $this->mockResponse
            ->expects($this->once())
            ->method('end')
            ->with($this->callback(function ($json) use ($errorMessage) {
                $data = json_decode($json, true);
                return $data['success'] === false &&
                       $data['error'] === $errorMessage &&
                       isset($data['timestamp']) &&
                       !isset($data['context']);
            }));

        $this->jsonResponse->sendError($errorMessage);
    }

    public function testSendErrorWithCustomStatus(): void
    {
        $errorMessage = 'Not found';
        $status = 404;
        
        $this->mockResponse
            ->expects($this->once())
            ->method('status')
            ->with($status);
            
        $this->mockResponse
            ->expects($this->once())
            ->method('end')
            ->with($this->callback(function ($json) use ($errorMessage) {
                $data = json_decode($json, true);
                return $data['success'] === false &&
                       $data['error'] === $errorMessage;
            }));

        $this->jsonResponse->sendError($errorMessage, $status);
    }

    public function testSendErrorWithContext(): void
    {
        $errorMessage = 'Validation failed';
        $context = ['field' => 'email', 'code' => 'invalid_format'];
        
        $this->mockResponse
            ->expects($this->once())
            ->method('end')
            ->with($this->callback(function ($json) use ($errorMessage, $context) {
                $data = json_decode($json, true);
                return $data['success'] === false &&
                       $data['error'] === $errorMessage &&
                       $data['context'] === $context;
            }));

        $this->jsonResponse->sendError($errorMessage, 422, $context);
    }

    public function testSendErrorWithEmptyContext(): void
    {
        $errorMessage = 'Error occurred';
        
        $this->mockResponse
            ->expects($this->once())
            ->method('end')
            ->with($this->callback(function ($json) use ($errorMessage) {
                $data = json_decode($json, true);
                return $data['success'] === false &&
                       $data['error'] === $errorMessage &&
                       !isset($data['context']);
            }));

        $this->jsonResponse->sendError($errorMessage, 500, []);
    }

    public function testSendServiceUnavailableWithDefaultMessage(): void
    {
        $this->mockResponse
            ->expects($this->once())
            ->method('status')
            ->with(503);
            
        $this->mockResponse
            ->expects($this->once())
            ->method('end')
            ->with($this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['success'] === false &&
                       $data['error'] === 'No healthy servers available';
            }));

        $this->jsonResponse->sendServiceUnavailable();
    }

    public function testSendServiceUnavailableWithCustomMessage(): void
    {
        $customMessage = 'All servers are down for maintenance';
        
        $this->mockResponse
            ->expects($this->once())
            ->method('status')
            ->with(503);
            
        $this->mockResponse
            ->expects($this->once())
            ->method('end')
            ->with($this->callback(function ($json) use ($customMessage) {
                $data = json_decode($json, true);
                return $data['success'] === false &&
                       $data['error'] === $customMessage;
            }));

        $this->jsonResponse->sendServiceUnavailable($customMessage);
    }

    public function testCreateStaticMethod(): void
    {
        $response = $this->createMock(Response::class);
        $jsonResponse = JsonResponse::create($response);
        
        $this->assertInstanceOf(JsonResponse::class, $jsonResponse);
    }

    public function testJsonEncodeFlags(): void
    {
        $data = ['url' => 'https://example.com/path', 'data' => ['key' => 'value']];
        
        $this->mockResponse
            ->expects($this->once())
            ->method('end')
            ->with($this->callback(function ($json) {
                // Verify JSON_UNESCAPED_SLASHES is used (forward slashes not escaped)
                return strpos($json, 'https://example.com/path') !== false &&
                       strpos($json, 'https:\/\/example.com\/path') === false;
            }));

        $this->jsonResponse->send($data);
    }

    public function testComplexDataStructures(): void
    {
        $complexData = [
            'array' => [1, 2, 3],
            'nested' => ['deep' => ['value' => 'test']],
            'unicode' => 'Hello 世界',
            'boolean' => true,
            'null' => null,
            'number' => 42.5
        ];
        
        $this->mockResponse
            ->expects($this->once())
            ->method('end')
            ->with($this->callback(function ($json) use ($complexData) {
                $decoded = json_decode($json, true);
                return $decoded === $complexData;
            }));

        $this->jsonResponse->send($complexData);
    }

    public function testNullTargetServerIsHandledCorrectly(): void
    {
        $this->mockResponse
            ->expects($this->once())
            ->method('end')
            ->with($this->callback(function ($json) {
                $data = json_decode($json, true);
                return !array_key_exists('target_server', $data);
            }));

        $this->jsonResponse->sendSuccess('Test', [], null);
    }
}