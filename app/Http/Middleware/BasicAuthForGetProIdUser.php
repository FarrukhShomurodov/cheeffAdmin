<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BasicAuthForGetProIdUser
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @return JsonResponse|RedirectResponse|Response
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse|RedirectResponse
    {
        if ($request->getUser() !== 'cheeff' || $request->getPassword() !== 'proConnect3003') {
            // Unauthorized, return a 401 response
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
