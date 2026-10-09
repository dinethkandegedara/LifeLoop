<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanonicalHost
{
    /**
     * Ensure requests consistently use the canonical application host (e.g. 127.0.0.1)
     * so that authentication and CSRF session cookies are never fragmented across localhost and 127.0.0.1.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local')) {
            $currentHost = $request->getHost();
            $targetHost = parse_url(config('app.url'), PHP_URL_HOST);

            if ($targetHost && $currentHost !== $targetHost && in_array($currentHost, ['localhost', '127.0.0.1'])) {
                $targetUrl = str_replace(
                    $request->getSchemeAndHttpHost(),
                    config('app.url'),
                    $request->fullUrl()
                );

                return redirect()->to($targetUrl);
            }
        }

        return $next($request);
    }
}
