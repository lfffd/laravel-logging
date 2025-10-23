<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Superlog\Middleware\RequestLifecycleMiddleware as BaseMiddleware;

class SuperlogRequestLifecycleMiddleware extends BaseMiddleware
{
    /**
     * Handle an incoming request with a more flexible return type
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        return parent::handle($request, $next);
    }
}