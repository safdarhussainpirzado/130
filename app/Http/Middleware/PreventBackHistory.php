<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventBackHistory
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'Cache-Control'          => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'                 => 'no-cache',
            'Expires'                => '0',
            'X-Frame-Options'        => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Content-Security-Policy'=> "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://unpkg.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; img-src 'self' data: blob: https://server.arcgisonline.com https://*.arcgisonline.com https://*.tile.openstreetmap.fr https://*.tile.openstreetmap.org https://tile.openstreetmap.org https://*.tile.osm.org https://tile.osm.org https://*.basemaps.cartocdn.com https://basemaps.cartocdn.com https://unpkg.com https://cdnjs.cloudflare.com; connect-src 'self' https://server.arcgisonline.com https://*.arcgisonline.com https://*.basemaps.cartocdn.com https://basemaps.cartocdn.com https://*.tile.openstreetmap.org https://tile.openstreetmap.org https://unpkg.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self' https://localhost https://localhost:* https://127.0.0.1 https://127.0.0.1:* http://localhost http://localhost:* http://127.0.0.1 http://127.0.0.1:* https://130.nhmp.gov.pk;",
        ];

        if (method_exists($response, 'withHeaders')) {
            $response->withHeaders($headers);
        } else {
            foreach ($headers as $key => $value) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
    }
}
