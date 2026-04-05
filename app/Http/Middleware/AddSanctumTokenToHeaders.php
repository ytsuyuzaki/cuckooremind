<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSanctumTokenToHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('token') && ! $request->headers->has('Authorization')) {
            $request->headers->set('Authorization', 'Bearer '.$request->token);
        }

        return $next($request);
    }
}
