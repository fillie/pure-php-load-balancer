<?php

declare(strict_types=1);

namespace App\Server;

use App\Exception\NoHealthyServersException;
use App\Http\JsonResponse;
use App\Http\RequestMeta;
use App\Http\ResponseBuilder;
use App\LoadBalancer\LoadBalancerInterface;
use Throwable;

readonly class RequestHandler
{
    public function __construct(
        private LoadBalancerInterface $loadBalancer,
        private ResponseBuilder $responseBuilder,
        private ServerLogger $logger
    ) {
    }
    
    public function handle(RequestMeta $requestMeta, JsonResponse $jsonResponse): void
    {
        try {
            $this->routeRequest($requestMeta, $jsonResponse);
        } catch (NoHealthyServersException $exception) {
            $this->handleNoHealthyServersError($requestMeta, $exception, $jsonResponse);
        } catch (Throwable $exception) {
            $this->handleGenericError($requestMeta, $exception, $jsonResponse);
        }
    }
    
    /**
     * @throws NoHealthyServersException
     */
    private function routeRequest(RequestMeta $requestMeta, JsonResponse $jsonResponse): void
    {
        $targetServer = $this->loadBalancer->getNextServer();
        $this->logger->logRequest($requestMeta, $targetServer);
        
        $response = $this->responseBuilder->buildSuccess($requestMeta, $targetServer);
        $jsonResponse->send($response);
    }
    
    private function handleNoHealthyServersError(
        RequestMeta $requestMeta, 
        NoHealthyServersException $exception, 
        JsonResponse $jsonResponse
    ): void {
        $this->logger->logError($requestMeta, $exception);
        $response = $this->responseBuilder->buildServiceUnavailable($requestMeta);
        $jsonResponse->send($response, 503);
    }
    
    private function handleGenericError(
        RequestMeta $requestMeta, 
        Throwable $exception, 
        JsonResponse $jsonResponse
    ): void {
        $this->logger->logError($requestMeta, $exception);
        $response = $this->responseBuilder->buildInternalError($requestMeta);
        $jsonResponse->send($response, 500);
    }
}