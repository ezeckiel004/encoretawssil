<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckGestionnaireWilaya
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        // Si ce n'est pas un gestionnaire, on laisse passer (admin peut tout voir)
        if ($user->role !== 'gestionnaire') {
            return $next($request);
        }

        $gestionnaire = $user->gestionnaire;
        
        if (!$gestionnaire) {
            return response()->json([
                'success' => false,
                'message' => 'Profil gestionnaire introuvable',
            ], 403);
        }

        // Injecter la wilaya du gestionnaire dans la requête
        $request->merge(['gestionnaire_wilaya' => $gestionnaire->wilaya_id]);
        $request->attributes->set('gestionnaire_wilaya', $gestionnaire->wilaya_id);

        return $next($request);
    }
}