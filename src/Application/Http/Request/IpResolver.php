<?php

declare(strict_types=1);

namespace App\Application\Http\Request;

use App\Infrastructure\Config\Config;
use OpenSwoole\Http\Request;

final readonly class IpResolver
{
    private array $trustedProxies;
    private string $forwardedHeader;
    private bool $trustForwardedHeaders;

    public function __construct(private Config $config)
    {
        $this->trustedProxies = $this->config->array('security.trusted_proxies', []);
        $this->forwardedHeader = $this->config->string('security.forwarded_header', 'x-forwarded-for');
        $this->trustForwardedHeaders = $this->config->bool('security.trust_forwarded_headers', false);
    }

    /**
     * Resolve the real client IP address considering reverse proxy headers
     */
    public function resolveClientIp(Request $request): string
    {
        $remoteAddr = $request->server['remote_addr'] ?? '127.0.0.1';

        // If not trusting forwarded headers or no trusted proxies configured, return remote_addr
        if (!$this->trustForwardedHeaders || empty($this->trustedProxies)) {
            return $remoteAddr;
        }

        // Check if the request comes from a trusted proxy
        if (!$this->isFromTrustedProxy($remoteAddr)) {
            return $remoteAddr;
        }

        // Get forwarded IP from headers
        $forwardedIp = $this->extractForwardedIp($request);
        
        return $forwardedIp ?? $remoteAddr;
    }

    /**
     * Check if the remote address is from a trusted proxy
     */
    private function isFromTrustedProxy(string $remoteAddr): bool
    {
        return array_any($this->trustedProxies, fn($trustedProxy) => $this->ipMatches($remoteAddr, $trustedProxy));

    }

    /**
     * Extract the real client IP from forwarded headers
     */
    private function extractForwardedIp(Request $request): ?string
    {
        $forwardedFor = $request->header[$this->forwardedHeader] ?? null;
        
        if ($forwardedFor === null) {
            return null;
        }

        // X-Forwarded-For can contain multiple IPs: "client, proxy1, proxy2"
        // The first IP is typically the original client
        $ips = array_map('trim', explode(',', $forwardedFor));
        $clientIp = $ips[0] ?? null;

        // Validate the IP address
        if ($clientIp !== null && filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $clientIp;
        }

        // If the first IP is private/reserved, try other IPs in the chain
        foreach ($ips as $ip) {
            $cleanIp = trim($ip);
            if (filter_var($cleanIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $cleanIp;
            }
        }

        return null;
    }

    /**
     * Check if an IP matches a CIDR range or exact IP
     */
    private function ipMatches(string $ip, string $range): bool
    {
        // If it's an exact IP match
        if ($ip === $range) {
            return true;
        }

        // If it's a CIDR range
        if (str_contains($range, '/')) {
            return $this->ipInCidr($ip, $range);
        }

        return false;
    }

    /**
     * Check if an IP is within a CIDR range
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->ipv4InCidr($ip, $subnet, (int)$mask);
        }
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6InCidr($ip, $subnet, (int)$mask);
        }

        return false;
    }

    /**
     * Check if IPv4 is in CIDR range
     */
    private function ipv4InCidr(string $ip, string $subnet, int $mask): bool
    {
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskLong = -1 << (32 - $mask);
        
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * Check if IPv6 is in CIDR range
     */
    private function ipv6InCidr(string $ip, string $subnet, int $mask): bool
    {
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);
        
        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        $bytesToCheck = intval($mask / 8);
        $bitsToCheck = $mask % 8;

        // Check full bytes
        for ($i = 0; $i < $bytesToCheck; $i++) {
            if ($ipBin[$i] !== $subnetBin[$i]) {
                return false;
            }
        }

        // Check remaining bits
        if ($bitsToCheck > 0 && $bytesToCheck < 16) {
            $maskByte = 0xFF << (8 - $bitsToCheck);
            if ((ord($ipBin[$bytesToCheck]) & $maskByte) !== (ord($subnetBin[$bytesToCheck]) & $maskByte)) {
                return false;
            }
        }

        return true;
    }
}