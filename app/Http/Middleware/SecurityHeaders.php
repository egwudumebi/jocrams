<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        if ($this->isSwaggerDocumentationRequest($request)) {
            return implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
                "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
                "img-src 'self' data: https://validator.swagger.io",
                "font-src 'self' data: https://cdn.jsdelivr.net",
                "connect-src 'self'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
            ]);
        }

        return implode('; ', $this->applicationDirectives(app()->environment('local')));
    }

    /**
     * @return list<string>
     */
    private function applicationDirectives(bool $includeViteDevServer): array
    {
        $viteOrigins = $includeViteDevServer ? $this->viteDevOrigins() : [];
        $viteSourceList = $viteOrigins !== [] ? ' '.implode(' ', $viteOrigins) : '';

        $scriptSources = "'self'";
        if ($includeViteDevServer) {
            $scriptSources .= " 'unsafe-inline' 'unsafe-eval'{$viteSourceList}";
        }

        $workerSources = "'self' blob:";

        if ($includeViteDevServer) {
            $workerSources .= $viteSourceList;
        }

        return [
            "default-src 'self'",
            "script-src {$scriptSources}",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net{$viteSourceList}",
            "font-src 'self' data: https://fonts.gstatic.com https://fonts.bunny.net",
            "img-src 'self' data: blob:{$viteSourceList}",
            "connect-src 'self'{$viteSourceList}",
            "worker-src {$workerSources}",
            "child-src {$workerSources}",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];
    }

    /**
     * @return list<string>
     */
    private function viteDevOrigins(): array
    {
        $origins = [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'http://localhost:5174',
            'http://127.0.0.1:5174',
            'http://localhost:5175',
            'http://127.0.0.1:5175',
        ];

        $hotFile = public_path('hot');

        if (is_readable($hotFile)) {
            $viteUrl = rtrim(trim((string) file_get_contents($hotFile)), '/');

            if ($viteUrl !== '') {
                $origins[] = $viteUrl;
                $origins[] = (string) preg_replace('#^http#', 'ws', $viteUrl);
            }
        }

        return array_values(array_unique($origins));
    }

    private function isSwaggerDocumentationRequest(Request $request): bool
    {
        $docsPath = trim((string) config('openapi.docs_path', 'docs'), '/');
        $jsonPath = trim((string) config('openapi.json_path', 'docs/openapi.json'), '/');

        return $request->is($docsPath)
            || $request->is($docsPath.'/*')
            || $request->is($jsonPath);
    }
}
