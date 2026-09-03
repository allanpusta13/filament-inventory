<?php

declare(strict_types=1);

test('security headers are applied to responses', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-XSS-Protection', '1; mode=block');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Content-Security-Policy');
});

test('csp header contains frame-ancestors none', function () {
    $response = $this->get('/');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("frame-ancestors 'none'");
});

test('csp header restricts default-src to self', function () {
    $response = $this->get('/');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'");
});
