<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\CodePromo;
use App\Models\Livreur;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CodePromoController extends Controller
{
    /**
     * Middleware pour vérifier la wilaya
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = Auth::user();
            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            $request->merge([
                'gestionnaire_id' => $gestionnaire->id,
                'gestionnaire_wilaya' => $gestionnaire->wilaya_id
            ]);

            return $next($request);
        });
    }

    /**
     * Lister les codes promo du gestionnaire
     */
    public function index(Request $request): JsonResponse
    {
        $gestionnaireId = $request->get('gestionnaire_id');

        $codesPromo = CodePromo::with('livreurs.user') // Charger la relation user des livreurs
            ->where('gestionnaire_id', $gestionnaireId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $codesPromo
        ], 200);
    }

    /**
     * Créer un nouveau code promo
     */
    public function store(Request $request): JsonResponse
    {
        $gestionnaireId = $request->get('gestionnaire_id');

        $validator = Validator::make($request->all(), [
            'code' => 'nullable|string|max:50|unique:codes_promo,code',
            'description' => 'nullable|string',
            'type' => 'required|in:percentage,fixed',
            'valeur' => 'required|numeric|min:0',
            'min_commande' => 'nullable|numeric|min:0',
            'max_utilisations' => 'nullable|integer|min:1',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
            'livreurs' => 'nullable|array',
            'livreurs.*' => 'exists:livreurs,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Créer le code promo
        $codePromo = CodePromo::create([
            'code' => $request->code ?? $this->generateCode(),
            'description' => $request->description,
            'type' => $request->type,
            'valeur' => $request->valeur,
            'min_commande' => $request->min_commande,
            'max_utilisations' => $request->max_utilisations,
            'date_debut' => $request->date_debut,
            'date_fin' => $request->date_fin,
            'gestionnaire_id' => $gestionnaireId,
            'status' => 'actif',
        ]);

        // Associer les livreurs sélectionnés
        if ($request->has('livreurs')) {
            $codePromo->livreurs()->attach($request->livreurs);
        }

        return response()->json([
            'success' => true,
            'message' => 'Code promo créé avec succès',
            'data' => $codePromo->load('livreurs.user') // Charger la relation user
        ], 201);
    }

    /**
     * Voir un code promo spécifique
     */
    public function show(Request $request, $id): JsonResponse
    {
        $gestionnaireId = $request->get('gestionnaire_id');

        $codePromo = CodePromo::with(['livreurs.user']) // ← CORRECTION ICI : charger la relation user des livreurs
            ->where('gestionnaire_id', $gestionnaireId)
            ->find($id);

        if (!$codePromo) {
            return response()->json([
                'success' => false,
                'message' => 'Code promo introuvable'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $codePromo
        ], 200);
    }

    /**
     * Mettre à jour un code promo
     */
    public function update(Request $request, $id): JsonResponse
    {
        $gestionnaireId = $request->get('gestionnaire_id');

        $codePromo = CodePromo::where('gestionnaire_id', $gestionnaireId)
            ->find($id);

        if (!$codePromo) {
            return response()->json([
                'success' => false,
                'message' => 'Code promo introuvable'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'nullable|string|max:50|unique:codes_promo,code,' . $id,
            'description' => 'nullable|string',
            'type' => 'sometimes|in:percentage,fixed',
            'valeur' => 'sometimes|numeric|min:0',
            'min_commande' => 'nullable|numeric|min:0',
            'max_utilisations' => 'nullable|integer|min:1',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
            'status' => 'sometimes|in:actif,inactif,expire',
            'livreurs' => 'nullable|array',
            'livreurs.*' => 'exists:livreurs,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $codePromo->update($request->except('livreurs'));

        // Mettre à jour les livreurs associés
        if ($request->has('livreurs')) {
            $codePromo->livreurs()->sync($request->livreurs);
        }

        return response()->json([
            'success' => true,
            'message' => 'Code promo mis à jour',
            'data' => $codePromo->fresh('livreurs.user') // Charger la relation user
        ], 200);
    }

    /**
     * Supprimer un code promo
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $gestionnaireId = $request->get('gestionnaire_id');

        $codePromo = CodePromo::where('gestionnaire_id', $gestionnaireId)
            ->find($id);

        if (!$codePromo) {
            return response()->json([
                'success' => false,
                'message' => 'Code promo introuvable'
            ], 404);
        }

        $codePromo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Code promo supprimé'
        ], 200);
    }

    /**
     * Ajouter des livreurs à un code promo
     */
    public function addLivreurs(Request $request, $id): JsonResponse
    {
        $gestionnaireId = $request->get('gestionnaire_id');

        $codePromo = CodePromo::where('gestionnaire_id', $gestionnaireId)
            ->find($id);

        if (!$codePromo) {
            return response()->json([
                'success' => false,
                'message' => 'Code promo introuvable'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'livreurs' => 'required|array',
            'livreurs.*' => 'exists:livreurs,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $codePromo->livreurs()->syncWithoutDetaching($request->livreurs);

        return response()->json([
            'success' => true,
            'message' => 'Livreurs ajoutés au code promo',
            'data' => $codePromo->load('livreurs.user') // Charger la relation user
        ], 200);
    }

    /**
     * Retirer des livreurs d'un code promo
     */
    public function removeLivreurs(Request $request, $id): JsonResponse
    {
        $gestionnaireId = $request->get('gestionnaire_id');

        $codePromo = CodePromo::where('gestionnaire_id', $gestionnaireId)
            ->find($id);

        if (!$codePromo) {
            return response()->json([
                'success' => false,
                'message' => 'Code promo introuvable'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'livreurs' => 'required|array',
            'livreurs.*' => 'exists:livreurs,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $codePromo->livreurs()->detach($request->livreurs);

        return response()->json([
            'success' => true,
            'message' => 'Livreurs retirés du code promo',
            'data' => $codePromo->load('livreurs.user') // Charger la relation user
        ], 200);
    }

    /**
     * Générer un code promo unique
     */
    private function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (CodePromo::where('code', $code)->exists());

        return $code;
    }
}
