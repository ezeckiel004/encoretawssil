<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Livreur;
use App\Models\Gestionnaire;
use App\Models\LivreurAssignation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LivreurAssignationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    /**
     * Liste toutes les assignations
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = LivreurAssignation::with(['livreur.user', 'gestionnaire.user', 'createur']);

            // Filtres
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('livreur_id')) {
                $query->where('livreur_id', $request->livreur_id);
            }

            if ($request->has('gestionnaire_id')) {
                $query->where('gestionnaire_id', $request->gestionnaire_id);
            }

            if ($request->has('wilaya_cible')) {
                $query->where('wilaya_cible', $request->wilaya_cible);
            }

            $assignations = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $assignations
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur index assignations: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des assignations'
            ], 500);
        }
    }

    /**
     * Créer une assignation
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'livreur_id' => 'required|exists:livreurs,id',
            'gestionnaire_id' => 'required|exists:gestionnaires,id',
            'date_debut' => 'required|date',
            'date_fin' => 'nullable|date|after:date_debut',
            'motif' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $livreur = Livreur::find($request->livreur_id);
            $gestionnaire = Gestionnaire::find($request->gestionnaire_id);

            if (!$livreur || !$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livreur ou gestionnaire introuvable'
                ], 404);
            }

            // Vérifier si le livreur est déjà assigné à ce gestionnaire
            $existing = LivreurAssignation::where('livreur_id', $request->livreur_id)
                                          ->where('gestionnaire_id', $request->gestionnaire_id)
                                          ->where('status', 'active')
                                          ->where(function($q) {
                                              $q->whereNull('date_fin')
                                                ->orWhere('date_fin', '>=', now());
                                          })
                                          ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce livreur est déjà assigné à ce gestionnaire'
                ], 400);
            }

            // Créer l'assignation
            $assignation = LivreurAssignation::create([
                'livreur_id' => $request->livreur_id,
                'gestionnaire_id' => $request->gestionnaire_id,
                'wilaya_cible' => $gestionnaire->wilaya_id,
                'date_debut' => $request->date_debut,
                'date_fin' => $request->date_fin,
                'status' => 'active',
                'motif' => $request->motif,
                'created_by' => auth()->id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Livreur assigné avec succès',
                'data' => $assignation->load(['livreur.user', 'gestionnaire.user'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création assignation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'assignation'
            ], 500);
        }
    }

    /**
     * Voir une assignation
     */
    public function show($id): JsonResponse
    {
        try {
            $assignation = LivreurAssignation::with(['livreur.user', 'gestionnaire.user', 'createur'])
                                             ->find($id);

            if (!$assignation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assignation non trouvée'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $assignation
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur show assignation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération'
            ], 500);
        }
    }

    /**
     * Mettre à jour une assignation
     */
    public function update(Request $request, $id): JsonResponse
    {
        $assignation = LivreurAssignation::find($id);

        if (!$assignation) {
            return response()->json([
                'success' => false,
                'message' => 'Assignation non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'date_fin' => 'nullable|date|after:date_debut',
            'status' => 'sometimes|in:active,terminee,annulee',
            'motif' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $assignation->update($request->only(['date_fin', 'status', 'motif']));

            return response()->json([
                'success' => true,
                'message' => 'Assignation mise à jour',
                'data' => $assignation->load(['livreur.user', 'gestionnaire.user'])
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur update assignation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * Supprimer une assignation
     */
    public function destroy($id): JsonResponse
    {
        try {
            $assignation = LivreurAssignation::find($id);

            if (!$assignation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assignation non trouvée'
                ], 404);
            }

            $assignation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Assignation supprimée'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur suppression assignation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * Terminer une assignation (sans supprimer)
     */
    public function terminer($id): JsonResponse
    {
        try {
            $assignation = LivreurAssignation::find($id);

            if (!$assignation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assignation non trouvée'
                ], 404);
            }

            $assignation->update([
                'status' => 'terminee',
                'date_fin' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Assignation terminée avec succès',
                'data' => $assignation
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur terminer assignation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la fin de l\'assignation'
            ], 500);
        }
    }

    /**
     * Récupérer les livreurs disponibles pour assignation à un gestionnaire
     */
    public function getLivreursDisponibles(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'gestionnaire_id' => 'required|exists:gestionnaires,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $gestionnaire = Gestionnaire::find($request->gestionnaire_id);

            // Livreurs natifs de la wilaya du gestionnaire
            $livreursNatifs = Livreur::with('user')
                                     ->where('wilaya_id', $gestionnaire->wilaya_id)
                                     ->where('desactiver', false)
                                     ->get();

            // Livreurs déjà assignés à ce gestionnaire
            $livreursDejaAssignes = LivreurAssignation::where('gestionnaire_id', $gestionnaire->id)
                                                      ->where('status', 'active')
                                                      ->pluck('livreur_id')
                                                      ->toArray();

            // Livreurs d'autres wilayas non encore assignés
            $livreursAutres = Livreur::with('user')
                                     ->where('wilaya_id', '!=', $gestionnaire->wilaya_id)
                                     ->where('desactiver', false)
                                     ->whereNotIn('id', $livreursDejaAssignes)
                                     ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'natifs' => $livreursNatifs,
                    'autres' => $livreursAutres,
                    'deja_assignes' => $livreursDejaAssignes
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur getLivreursDisponibles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération'
            ], 500);
        }
    }
}
