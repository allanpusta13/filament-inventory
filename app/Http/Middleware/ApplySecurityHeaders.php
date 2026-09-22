<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApplySecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' /_debugbar; style-src 'self' 'unsafe-inline' fonts.googleapis.com /_debugbar; img-src 'self' data: https://ui-avatars.com; font-src 'self' data: https://fonts.gstatic.com https://fonts.googleapis.com; frame-ancestors 'none';"
            // 'unsafe-inline' in script-src: Required by Filament Livewire inline event handlers
            // 'unsafe-eval' in script-src: Required by Filament Vite HMR in development
            // 'unsafe-inline' in style-src: Required by Filament inline style injection
            // fonts.gstatic.com & fonts.googleapis.com: Required for Instrument Sans Google Font
            // /_debugbar: Required for Debugbar assets (JS/CSS)
        );

        return $response;
    }
}
