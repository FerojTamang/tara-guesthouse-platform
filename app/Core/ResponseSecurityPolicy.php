<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * Applies one validated baseline of browser security headers to every response.
 */
final readonly class ResponseSecurityPolicy
{
    private string $contentSecurityPolicy;

    private string $permissionsPolicy;

    private ?string $strictTransportSecurity;

    /** @param array<string, mixed> $configuration */
    public function __construct(array $configuration)
    {
        if (array_keys($configuration) !== [
            'content_security_policy',
            'permissions_policy',
            'strict_transport_security',
        ]) {
            throw new InvalidArgumentException('Response security policy configuration is invalid.');
        }

        $contentSecurityPolicy = $configuration['content_security_policy'];
        $permissionsPolicy = $configuration['permissions_policy'];
        $strictTransportSecurity = $configuration['strict_transport_security'];

        if (
            !is_string($contentSecurityPolicy)
            || trim($contentSecurityPolicy) === ''
            || !is_string($permissionsPolicy)
            || trim($permissionsPolicy) === ''
            || !($strictTransportSecurity === null || is_string($strictTransportSecurity))
        ) {
            throw new InvalidArgumentException('Response security policy configuration is invalid.');
        }

        foreach ([$contentSecurityPolicy, $permissionsPolicy, $strictTransportSecurity] as $value) {
            if (
                is_string($value)
                && preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value) === 1
            ) {
                throw new InvalidArgumentException('Response security policy configuration is invalid.');
            }
        }

        if (
            $strictTransportSecurity !== null
            && preg_match('/^max-age=[1-9][0-9]{0,9}(?:; includeSubDomains)?$/D', $strictTransportSecurity) !== 1
        ) {
            throw new InvalidArgumentException('Response security policy configuration is invalid.');
        }

        $this->contentSecurityPolicy = $contentSecurityPolicy;
        $this->permissionsPolicy = $permissionsPolicy;
        $this->strictTransportSecurity = $strictTransportSecurity;
    }

    public function apply(Request $request, Response $response): Response
    {
        $headers = [
            'Content-Security-Policy' => $this->contentSecurityPolicy,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => $this->permissionsPolicy,
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Origin-Agent-Cluster' => '?1',
            'X-Permitted-Cross-Domain-Policies' => 'none',
        ];

        if ($this->strictTransportSecurity !== null) {
            $headers['Strict-Transport-Security'] = $this->strictTransportSecurity;
        }

        foreach ($headers as $name => $value) {
            if (!$this->hasHeader($response, $name)) {
                $response = $response->withHeader($name, $value);
            }
        }

        if ($this->isAdministratorRequest($request) && !$this->hasHeader($response, 'Cache-Control')) {
            $response = $response->withHeader('Cache-Control', 'no-store');
        }

        if ($this->isAdministratorRequest($request) && !$this->hasHeader($response, 'Pragma')) {
            $response = $response->withHeader('Pragma', 'no-cache');
        }

        return $response;
    }

    private function isAdministratorRequest(Request $request): bool
    {
        $path = $request->path();

        return $path === '/admin'
            || str_starts_with($path, '/admin/')
            || $path === '/api/v1/admin'
            || str_starts_with($path, '/api/v1/admin/');
    }

    private function hasHeader(Response $response, string $expectedName): bool
    {
        foreach (array_keys($response->headers()) as $name) {
            if (strcasecmp($name, $expectedName) === 0) {
                return true;
            }
        }

        return false;
    }
}
