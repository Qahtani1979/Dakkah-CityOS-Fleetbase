<?php

namespace Fleetbase\CityOS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Fleetbase\CityOS\Support\NodeContext;

class ResolveNodeContext
{
    public function handle(Request $request, Closure $next)
    {
        $nodeContext = NodeContext::fromRequest($request);

        if ($nodeContext->tenant) {
            $tenant = $nodeContext->resolveTenant();
            if ($tenant) {
                $nodeContext->setResolvedTenant($tenant);
            }
        }

        $request->attributes->set('node_context', $nodeContext);

        $response = $next($request);

        if (method_exists($response, 'header') && $nodeContext->tenant) {
            $response->header('X-CityOS-Tenant', $nodeContext->tenant);
            $response->header('X-CityOS-Country', $nodeContext->country);
            $response->header('X-CityOS-Locale', $nodeContext->locale);
        }

        return $response;
    }
}
