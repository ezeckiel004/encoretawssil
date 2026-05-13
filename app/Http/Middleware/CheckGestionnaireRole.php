<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckGestionnaireRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()->role !== 'gestionnaire') {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux gestionnaires'
            ], 403);
        }

        return $next($request);
    }
}