<?php

namespace App\Http\Controllers;

use App\Models\Livraison;
use App\Models\GestionnaireGain;
use App\Models\CommissionConfig;
use App\Models\Gestionnaire;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LivreurCourseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Récupère le livreur connecté et son type
     */
    private function getLivreur()
    {
        $user = auth()->user();
        if (!$user || !$user->livreur) {
            abort(403, 'Accès refusé : vous n\'êtes pas un livreur.');
        }
        return $user->livreur;
    }

    /**
     * Liste toutes les courses assignées au livreur selon son type
     */
    public function index(): JsonResponse
    {
        $livreur = $this->getLivreur();

        $query = Livraison::with([
            'demandeLivraison',
            'demandeLivraison.colis',
            'client.user',
            'demandeLivraison.destinataire.user'
        ]);

        // Filtrage selon le type de livreur
        if ($livreur->type === 'distributeur') {
            $query->where('livreur_distributeur_id', $livreur->id);
        } elseif ($livreur->type === 'ramasseur') {
            $query->where('livreur_ramasseur_id', $livreur->id);
        } else {
            // Livreurs polyvalents : toutes les courses assignées
            $query->where('livreur_distributeur_id', $livreur->id)
                ->orWhere('livreur_ramasseur_id', $livreur->id);
        }

        $courses = $query->get();

        return response()->json(['success' => true, 'data' => $courses], 200);
    }

    /**
     * Voir une course spécifique
     */
    public function show(string $id): JsonResponse
    {
        $livreur = $this->getLivreur();

        $query = Livraison::where('id', $id)
            ->with([
                'demandeLivraison',
                'demandeLivraison.colis',
                'client.user',
                'demandeLivraison.destinataire.user'
            ]);

        if ($livreur->type === 'distributeur') {
            $query->where('livreur_distributeur_id', $livreur->id);
        } elseif ($livreur->type === 'ramasseur') {
            $query->where('livreur_ramasseur_id', $livreur->id);
        } else {
            $query->where(function ($q) use ($livreur) {
                $q->where('livreur_distributeur_id', $livreur->id)
                    ->orWhere('livreur_ramasseur_id', $livreur->id);
            });
        }

        $course = $query->first();

        if (!$course) {
            return response()->json(['success' => false, 'message' => 'Course introuvable ou non assignée à ce livreur.'], 404);
        }

        return response()->json(['success' => true, 'data' => $course], 200);
    }

    /**
     * Valider une course
     */
    public function complete(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $livreur = $this->getLivreur();

            if (!$livreur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livreur non trouvé.',
                ], 401);
            }

            $livreurId = $livreur->id;
            \Log::info('Complete action - Livreur ID: ' . $livreurId . ', Course ID: ' . $id);

            // Trouver la course assignée à ce livreur
            $course = Livraison::where('id', $id)
                ->where(function ($q) use ($livreurId) {
                    $q->where('livreur_distributeur_id', $livreurId)
                        ->orWhere('livreur_ramasseur_id', $livreurId);
                })
                ->first();

            if (!$course) {
                $allCourses = Livraison::where(function ($q) use ($livreurId) {
                    $q->where('livreur_distributeur_id', $livreurId)
                        ->orWhere('livreur_ramasseur_id', $livreurId);
                })->get(['id', 'livreur_distributeur_id', 'livreur_ramasseur_id', 'status']);

                \Log::warning('Course not found for ID: ' . $id . ' with Livreur ID: ' . $livreurId);

                return response()->json([
                    'success' => false,
                    'message' => 'Livraison introuvable ou non assignée à ce livreur.',
                    'debug' => [
                        'livreur_id' => $livreurId,
                        'course_id_searched' => $id,
                        'available_courses' => $allCourses,
                    ]
                ], 404);
            }

            $currentStatus = $course->status;
            $nextStatus = null;

            // 🔄 Déterminer le rôle du livreur DANS CETTE LIVRAISON
            $userRoleInThisDelivery = '';

            // ✅ Si c'est la MÊME PERSONNE pour les deux rôles, déterminer le rôle par le statut
            $isSamePerson = (
                $course->livreur_ramasseur_id === $livreurId &&
                $course->livreur_distributeur_id === $livreurId
            );

            // ✅ Cas spécial: Ramasseur qui continue en tant que distributeur
            $isRamasseurContinuingAsDistributeur = (
                $course->livreur_ramasseur_id === $livreurId &&
                ($course->livreur_distributeur_id === null || $course->livreur_distributeur_id === '') &&
                in_array($currentStatus, ['en_transit', 'prise_en_charge_livraison', 'livre'])
            );

            if ($isSamePerson) {
                // Déterminer le rôle basé sur le statut actuel
                $pickupStatuses = ['en_attente', 'prise_en_charge_ramassage', 'ramasse'];
                $userRoleInThisDelivery = in_array($currentStatus, $pickupStatuses) ? 'ramasseur' : 'distributeur';
                \Log::info('🎯 SAME PERSON - Role determined by status: ' . $currentStatus . ' → ' . $userRoleInThisDelivery);
            } elseif ($isRamasseurContinuingAsDistributeur) {
                // Le ramasseur continue comme distributeur
                $userRoleInThisDelivery = 'distributeur';
                \Log::info('🎯 RAMASSEUR CONTINUING AS DISTRIBUTEUR - Status: ' . $currentStatus);
            } elseif ($course->livreur_ramasseur_id === $livreurId) {
                $userRoleInThisDelivery = 'ramasseur';
            } elseif ($course->livreur_distributeur_id === $livreurId) {
                $userRoleInThisDelivery = 'distributeur';
            }

            \Log::info('Role determination - Livreur ID: ' . $livreurId .
                       ', Ramasseur ID: ' . $course->livreur_ramasseur_id .
                       ', Distributeur ID: ' . $course->livreur_distributeur_id .
                       ', Is Same Person: ' . ($isSamePerson ? 'YES' : 'NO') .
                       ', Is Ramasseur Continuing: ' . ($isRamasseurContinuingAsDistributeur ? 'YES' : 'NO') .
                       ', Current Status: ' . $currentStatus .
                       ', Determined Role: ' . $userRoleInThisDelivery);

            // 🔄 Déterminer le prochain statut selon le rôle du livreur DANS CETTE LIVRAISON
            if ($userRoleInThisDelivery === 'ramasseur') {
                $ramasseurTransitions = [
                    'en_attente' => 'prise_en_charge_ramassage',
                    'prise_en_charge_ramassage' => 'ramasse',
                    'ramasse' => 'en_transit',
                ];
                $nextStatus = $ramasseurTransitions[$currentStatus] ?? null;
            } elseif ($userRoleInThisDelivery === 'distributeur') {
                // ✅ Distributeur: 3 étapes
                $distributeurTransitions = [
                    'en_transit' => 'prise_en_charge_livraison',
                    'prise_en_charge_livraison' => 'livre',
                ];
                $nextStatus = $distributeurTransitions[$currentStatus] ?? null;
            }

            if (!$nextStatus) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transition de statut invalide ou impossible',
                    'current_status' => $currentStatus,
                    'user_role_in_delivery' => $userRoleInThisDelivery,
                    'livreur_id' => $livreurId,
                    'debug' => 'Aucune transition valide pour ce statut et ce rôle dans cette livraison'
                ], 422);
            }

            // 📅 Préparer les données de mise à jour avec les dates
            $updateData = ['status' => $nextStatus];

            // ✅ Mettre à jour la date de ramassage quand le ramassage est terminé
            if ($userRoleInThisDelivery === 'ramasseur' && $nextStatus === 'ramasse') {
                $updateData['date_ramassage'] = Carbon::now()->toDateString();
                \Log::info("📅 Date de ramassage mise à jour: " . Carbon::now()->toDateString());
            }

            // ✅ Mettre à jour la date de livraison quand la livraison est terminée
            if ($userRoleInThisDelivery === 'distributeur' && $nextStatus === 'livre') {
                $updateData['date_livraison'] = Carbon::now()->toDateString();
                \Log::info("📅 Date de livraison mise à jour: " . Carbon::now()->toDateString());
            }

            // Mettre à jour le statut et les dates
            $course->update($updateData);

            \Log::info("Course transition: {$currentStatus} → {$nextStatus} (Livreur Role: {$userRoleInThisDelivery})");

            // ✅ CALCULER LES COMMISSIONS SI LA LIVRAISON EST MARQUÉE COMME LIVRÉE
            $resultatCommission = null;
            if ($nextStatus === 'livre' && $currentStatus !== 'livre') {
                $resultatCommission = $this->calculerCommissionsLivraison($course);

                if ($resultatCommission['success']) {
                    \Log::info("✅ Commissions calculées avec succès pour la livraison {$id} par le livreur");
                } else {
                    \Log::warning("⚠️ Échec du calcul des commissions pour la livraison {$id}: " . $resultatCommission['message']);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour de "' . $this->getStatusLabel($currentStatus) . '" à "' . $this->getStatusLabel($nextStatus) . '"',
                'data' => [
                    'id' => $course->id,
                    'previous_status' => $currentStatus,
                    'new_status' => $nextStatus,
                    'user_role_in_delivery' => $userRoleInThisDelivery,
                ],
                'commission' => $resultatCommission
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error completing course: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Nombre total de courses assignées au livreur
     */
    public function count(): JsonResponse
    {
        $livreur = $this->getLivreur();

        if ($livreur->type === 'distributeur') {
            $total = $livreur->livraisonsDistribution()->count();
        } elseif ($livreur->type === 'ramasseur') {
            $total = $livreur->livraisonsRamassage()->count();
        } else {
            $total = $livreur->livraisonsDistribution()->count() + $livreur->livraisonsRamassage()->count();
        }

        return response()->json(['success' => true, 'total_courses' => $total], 200);
    }

    /**
     * Filtrer les courses par statut
     */
    public function byStatus(string $status): JsonResponse
    {
        $livreur = $this->getLivreur();

        $validStatuses = [
            'en_attente',
            'prise_en_charge_ramassage',
            'ramasse',
            'en_transit',
            'prise_en_charge_livraison',
            'livre',
            'annule'
        ];

        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Statut invalide. Utiliser : ' . implode(', ', $validStatuses)
            ], 422);
        }

        $query = Livraison::with([
            'demandeLivraison',
            'demandeLivraison.colis',
            'client.user',
            'demandeLivraison.destinataire.user'
        ])->where('status', $status);

        if ($livreur->type === 'distributeur') {
            $query->where('livreur_distributeur_id', $livreur->id);
        } elseif ($livreur->type === 'ramasseur') {
            $query->where('livreur_ramasseur_id', $livreur->id);
        } else {
            $query->where(function ($q) use ($livreur) {
                $q->where('livreur_distributeur_id', $livreur->id)
                    ->orWhere('livreur_ramasseur_id', $livreur->id);
            });
        }

        $courses = $query->get();

        return response()->json([
            'success' => true,
            'status' => $status,
            'data' => $courses
        ], 200);
    }

    public function colis(): JsonResponse
    {
        $livreur = $this->getLivreur();

        // Récupère toutes les courses du livreur avec leurs colis et informations client/destinataire
        $courses = Livraison::with(['demandeLivraison.colis', 'demandeLivraison.client.user', 'demandeLivraison.destinataire.user'])
            ->where(function ($q) use ($livreur) {
                $q->where('livreur_distributeur_id', $livreur->id)
                    ->orWhere('livreur_ramasseur_id', $livreur->id);
            })
            ->get();

        // On collecte les colis avec les informations d'expédition
        $colis = $courses->map(function ($course) {
            if (!$course->demandeLivraison || !$course->demandeLivraison->colis) {
                return null;
            }

            $demande = $course->demandeLivraison;
            $colisData = $demande->colis->toArray();

            // DEBUG: Log la demande de livraison
            \Log::info('===== COLIS DEBUG =====');
            \Log::info('Demande ID: ' . $demande->id);
            \Log::info('Demande Data:', [
                'wilaya' => $demande->wilaya,
                'commune' => $demande->commune,
            ]);

            // Ajouter les informations de l'expéditeur (client)
            if ($demande->client && $demande->client->user) {
                $colisData['expediteur_nom'] = $demande->client->user->nom . ' ' . $demande->client->user->prenom;
                $colisData['expediteur_telephone'] = $demande->client->user->telephone;
            } else {
                $colisData['expediteur_nom'] = 'Non renseigné';
                $colisData['expediteur_telephone'] = 'Non renseigné';
            }
            $colisData['expediteur_adresse'] = $demande->addresse_depot ?? 'Non renseigné';

            // Ajouter les informations du destinataire
            if ($demande->destinataire && $demande->destinataire->user) {
                $colisData['destinataire_nom'] = $demande->destinataire->user->nom . ' ' . $demande->destinataire->user->prenom;
                $colisData['destinataire_telephone'] = $demande->destinataire->user->telephone;
            } else {
                $colisData['destinataire_nom'] = 'Non renseigné';
                $colisData['destinataire_telephone'] = 'Non renseigné';
            }
            $colisData['destinataire_adresse'] = $demande->addresse_delivery ?? 'Non renseigné';

            // 🔑 AJOUTER WILAYA ET COMMUNE - CRUCIAL!
            $colisData['wilaya'] = $demande->wilaya ?? 'Non renseigné';
            $colisData['commune'] = $demande->commune ?? 'Non renseigné';
            $colisData['wilaya_depot'] = $demande->wilaya_depot ?? 'Non renseigné';
            $colisData['commune_depot'] = $demande->commune_depot ?? 'Non renseigné';

            // Ajouter les coordonnées GPS pour le tracking
            $colisData['lat_depot'] = $demande->lat_depot;
            $colisData['lng_depot'] = $demande->lng_depot;
            $colisData['lat_delivery'] = $demande->lat_delivery;
            $colisData['lng_delivery'] = $demande->lng_delivery;

            // Ajouter le statut de la livraison
            $colisData['status'] = $course->status;
            $colisData['livraison_id'] = $course->id;


            // ✅ AJOUT CRUCIAL - IDs des livreurs assignés à cette livraison
            $colisData['livreur_ramasseur_id'] = $course->livreur_ramasseur_id;
            $colisData['livreur_distributeur_id'] = $course->livreur_distributeur_id;

            // 💰 AJOUTER LES PRIX - Dynamiquement depuis la base de données
            $colisData['prix_colis'] = (float) ($demande->colis->colis_prix ?? 0);
            $colisData['prix_livraison'] = (float) ($demande->prix ?? 0);

             $colisData['date_ramassage'] = $course->date_ramassage;
            $colisData['date_livraison'] = $course->date_livraison;

            // DEBUG: Log les données du colis retournées
            \Log::info('Colis Data Being Returned:', [
                'colis_id' => $colisData['id'],
                'wilaya' => $colisData['wilaya'],
                'commune' => $colisData['commune'],
                'prix_livraison' => $colisData['prix_livraison'] ?? 'NULL',
            ]);

            return $colisData;
        })->filter(); // enlève les null

        \Log::info('===== FINAL RESPONSE =====');
        \Log::info('Total Colis: ' . $colis->count());
        if ($colis->count() > 0) {
            \Log::info('First Colis Sample:', $colis->first());
            $firstColis = $colis->first();
            if (is_array($firstColis)) {
                \Log::info('First colis has prix_livraison: ' . ($firstColis['prix_livraison'] ?? 'MISSING'));
            }
        }

        return response()->json([
            'success' => true,
            'total_colis' => $colis->count(),
            'data' => $colis->values(),
        ], 200);
    }


    /**
     * Les 7 statuts de livraison possibles
     */
    const STATUSES = [
        'en_attente',                    // 1. En attente de ramassage
        'prise_en_charge_ramassage',     // 2. Livreur/Ramasseur prend en charge le ramassage
        'ramasse',                       // 3. Colis a été ramassé
        'en_transit',                    // 4. Colis en transit
        'prise_en_charge_livraison',     // 5. Distributeur prend en charge la livraison
        'livre',                         // 6. Colis livré avec succès
        'annule',                        // 7. Colis annulé
    ];

    /**
     * Met à jour le statut d'une livraison avec validation du type de livreur
     *
     * @param Request $request
     * @param string $id ID de la livraison
     * @return JsonResponse
     */
    public function updateStatusByLivreurType(Request $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $livraison = Livraison::with(['livreurDistributeur', 'livreurRamasseur'])->find($id);

            if (!$livraison) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livraison introuvable',
                ], 404);
            }

            $validated = $request->validate([
                'new_status' => 'required|string|in:' . implode(',', self::STATUSES),
                'comment' => 'nullable|string|max:500',
            ]);

            $currentStatus = $livraison->status;
            $newStatus = $validated['new_status'];
            $livreur = auth()->user()->livreur ?? null;

            // Valider la transition de statut selon le type de livreur
            if (!$this->isValidStatusTransition($currentStatus, $newStatus, $livreur)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transition de statut invalide pour votre type de livreur',
                    'current_status' => $currentStatus,
                    'requested_status' => $newStatus,
                ], 422);
            }

            // Mettre à jour le statut
            $livraison->update([
                'status' => $newStatus,
            ]);

            // ✅ CALCULER LES COMMISSIONS SI LA LIVRAISON EST MARQUÉE COMME LIVRÉE
            $resultatCommission = null;
            if ($newStatus === 'livre' && $currentStatus !== 'livre') {
                $resultatCommission = $this->calculerCommissionsLivraison($livraison);

                if ($resultatCommission['success']) {
                    \Log::info("✅ Commissions calculées avec succès pour la livraison {$id} via updateStatusByLivreurType");
                } else {
                    \Log::warning("⚠️ Échec du calcul des commissions pour la livraison {$id}: " . $resultatCommission['message']);
                }
            }

            // Enregistrer un commentaire si fourni
            if (!empty($validated['comment'])) {
                \Log::info("Transition de statut pour la livraison {$id}: {$currentStatus} → {$newStatus}. Comment: {$validated['comment']}");
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Statut mis à jour à '" . $this->getStatusLabel($newStatus) . "'",
                'data' => $livraison->refresh(),
                'commission' => $resultatCommission
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Valide la transition d'un statut à un autre selon le type de livreur
     *
     * @param string $currentStatus
     * @param string $newStatus
     * @param Livreur|null $livreur
     * @return bool
     */
    private function isValidStatusTransition(string $currentStatus, string $newStatus, ?Livreur $livreur): bool
    {
        // Définir les transitions valides par type de livreur
        $transitionsRamasseur = [
            'en_attente' => ['prise_en_charge_ramassage', 'annule'],
            'prise_en_charge_ramassage' => ['ramasse', 'annule'],
            'ramasse' => ['en_transit', 'annule'],
        ];

        $transitionsDistributeur = [
            'en_transit' => ['prise_en_charge_livraison', 'annule'],
            'prise_en_charge_livraison' => ['livre', 'annule'],
        ];

        $livreurType = $livreur->type ?? null;

        if ($livreurType === 'ramasseur') {
            if (!isset($transitionsRamasseur[$currentStatus])) {
                return false;
            }
            return in_array($newStatus, $transitionsRamasseur[$currentStatus]);
        } elseif ($livreurType === 'distributeur') {
            if (!isset($transitionsDistributeur[$currentStatus])) {
                return false;
            }
            return in_array($newStatus, $transitionsDistributeur[$currentStatus]);
        }

        // Administrateur peut faire n'importe quelle transition valide
        return true;
    }

    /**
     * Récupère le label lisible d'un statut
     *
     * @param string $status
     * @return string
     */
    private function getStatusLabel(string $status): string
    {
        $labels = [
            'en_attente' => 'En attente',
            'prise_en_charge_ramassage' => 'Prise en charge (ramassage)',
            'ramasse' => 'Ramassé',
            'en_transit' => 'En transit',
            'prise_en_charge_livraison' => 'Prise en charge (livraison)',
            'livre' => 'Livré',
            'annule' => 'Annulé',
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Récupère les livraisons selon le statut et le type de livreur
     *
     * @param Request $request
     * @param string $status
     * @return JsonResponse
     */
    public function getByStatusAndLivreurType(Request $request, string $status): JsonResponse
    {
        try {
            $livreur = auth()->user()->livreur;

            if (!$livreur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas un livreur',
                ], 403);
            }

            $query = Livraison::where('status', $status);

            // Filtrer selon le type de livreur
            if ($livreur->type === 'ramasseur') {
                $query->where('livreur_ramasseur_id', $livreur->id);
            } elseif ($livreur->type === 'distributeur') {
                $query->where('livreur_distributeur_id', $livreur->id);
            }

            $livraisons = $query->with([
                'client',
                'demandeLivraison',
                'livreurDistributeur',
                'livreurRamasseur',
            ])->get();

            return response()->json([
                'success' => true,
                'status_label' => $this->getStatusLabel($status),
                'count' => $livraisons->count(),
                'data' => $livraisons,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livraisons',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Récupère les transitions valides pour le statut actuel selon le type de livreur
     *
     * @param Request $request
     * @param string $livraisonId
     * @return JsonResponse
     */
    public function getValidTransitions(Request $request, string $livraisonId): JsonResponse
    {
        try {
            $livraison = Livraison::find($livraisonId);

            if (!$livraison) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livraison introuvable',
                ], 404);
            }

            $livreur = auth()->user()->livreur;
            $currentStatus = $livraison->status;
            $validTransitions = [];

            // Obtenir les transitions valides selon le type de livreur
            $transitionsRamasseur = [
                'en_attente' => ['prise_en_charge_ramassage', 'annule'],
                'prise_en_charge_ramassage' => ['ramasse', 'annule'],
                'ramasse' => ['en_transit', 'annule'],
            ];

            $transitionsDistributeur = [
                'en_transit' => ['prise_en_charge_livraison', 'annule'],
                'prise_en_charge_livraison' => ['livre', 'annule'],
            ];

            if ($livreur && $livreur->type === 'ramasseur') {
                $validTransitions = $transitionsRamasseur[$currentStatus] ?? [];
            } elseif ($livreur && $livreur->type === 'distributeur') {
                $validTransitions = $transitionsDistributeur[$currentStatus] ?? [];
            }

            // Formater la réponse avec les labels
            $formattedTransitions = array_map(function ($status) {
                return [
                    'status' => $status,
                    'label' => $this->getStatusLabel($status),
                ];
            }, $validTransitions);

            return response()->json([
                'success' => true,
                'current_status' => $currentStatus,
                'current_status_label' => $this->getStatusLabel($currentStatus),
                'livreur_type' => $livreur->type ?? 'admin',
                'valid_transitions' => $formattedTransitions,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des transitions valides',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Récupère les statistiques de livraison par statut pour le livreur connecté
     *
     * @return JsonResponse
     */
    public function getStatistiquesByStatus(): JsonResponse
    {
        try {
            $livreur = auth()->user()->livreur;

            if (!$livreur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas un livreur',
                ], 403);
            }

            $query = Livraison::query();

            // Filtrer selon le type de livreur
            if ($livreur->type === 'ramasseur') {
                $query->where('livreur_ramasseur_id', $livreur->id);
            } elseif ($livreur->type === 'distributeur') {
                $query->where('livreur_distributeur_id', $livreur->id);
            }

            $stats = [];
            foreach (self::STATUSES as $status) {
                $count = (clone $query)->where('status', $status)->count();
                $stats[] = [
                    'status' => $status,
                    'label' => $this->getStatusLabel($status),
                    'count' => $count,
                ];
            }

            return response()->json([
                'success' => true,
                'livreur_type' => $livreur->type,
                'statistics' => $stats,
                'total' => $query->count(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ==================== MÉTHODES DE GESTION DES COMMISSIONS ====================

    private function calculerCommissionsLivraison(Livraison $livraison): array
    {
        try {
            $demande = $livraison->demandeLivraison;

            if (!$demande) {
                return [
                    'success' => false,
                    'message' => 'Demande de livraison non trouvée'
                ];
            }

            $prixLivraison = (float) ($demande->prix ?? 0);

            if ($prixLivraison <= 0) {
                return [
                    'success' => false,
                    'message' => 'Le prix de la livraison est invalide ou nul'
                ];
            }

            // Récupérer les pourcentages de commission depuis la configuration
            $pourcentageDepart = CommissionConfig::getValue('commission_depart_default') ?? 25;
            $pourcentageArrivee = CommissionConfig::getValue('commission_arrivee_default') ?? 25;

            // Calculer les montants
            $montantDepart = round($prixLivraison * ($pourcentageDepart / 100), 2);
            $montantArrivee = round($prixLivraison * ($pourcentageArrivee / 100), 2);
            $montantAdmin = $prixLivraison - $montantDepart - $montantArrivee;

            // Récupérer les gestionnaires
            $gestionnaireDepart = $this->getGestionnaireByWilaya($demande->wilaya_depot);
            $gestionnaireArrivee = $this->getGestionnaireByWilaya($demande->wilaya);

            $gainsEnregistres = [];

            // Enregistrer le gain pour la wilaya de départ
            if ($gestionnaireDepart && $montantDepart > 0) {
                $gainDepart = GestionnaireGain::create([
                    'gestionnaire_id' => $gestionnaireDepart->id,
                    'livraison_id' => $livraison->id,
                    'wilaya_type' => 'depart',
                    'montant_commission' => $montantDepart,
                    'pourcentage_applique' => $pourcentageDepart,
                    'date_calcul' => now(),
                    'status' => 'en_attente'
                ]);
                $gainsEnregistres['depart'] = $gainDepart;
            }

            // Enregistrer le gain pour la wilaya d'arrivée
            if ($gestionnaireArrivee && $montantArrivee > 0) {
                $gainArrivee = GestionnaireGain::create([
                    'gestionnaire_id' => $gestionnaireArrivee->id,
                    'livraison_id' => $livraison->id,
                    'wilaya_type' => 'arrivee',
                    'montant_commission' => $montantArrivee,
                    'pourcentage_applique' => $pourcentageArrivee,
                    'date_calcul' => now(),
                    'status' => 'en_attente'
                ]);
                $gainsEnregistres['arrivee'] = $gainArrivee;
            }

            return [
                'success' => true,
                'data' => [
                    'prix_livraison' => $prixLivraison,
                    'pourcentage_depart' => $pourcentageDepart,
                    'montant_depart' => $montantDepart,
                    'gestionnaire_depart' => $gestionnaireDepart?->user?->nom . ' ' . $gestionnaireDepart?->user?->prenom,
                    'pourcentage_arrivee' => $pourcentageArrivee,
                    'montant_arrivee' => $montantArrivee,
                    'gestionnaire_arrivee' => $gestionnaireArrivee?->user?->nom . ' ' . $gestionnaireArrivee?->user?->prenom,
                    'montant_admin' => $montantAdmin,
                    'gains_enregistres' => $gainsEnregistres
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Erreur calcul commissions: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors du calcul des commissions: ' . $e->getMessage()
            ];
        }
    }

    private function getGestionnaireByWilaya($wilayaId)
    {
        if (!$wilayaId) {
            return null;
        }

        // Mapping des noms de wilayas vers leurs codes
        $wilayaMapping = [
            'Adrar' => '01', 'Chlef' => '02', 'Laghouat' => '03',
            'Oum El Bouaghi' => '04', 'Batna' => '05', 'Béjaïa' => '06',
            'Biskra' => '07', 'Béchar' => '08', 'Blida' => '09',
            'Bouira' => '10', 'Tamanrasset' => '11', 'Tébessa' => '12',
            'Tlemcen' => '13', 'Tiaret' => '14', 'Tizi Ouzou' => '15',
            'Alger' => '16', 'Djelfa' => '17', 'Jijel' => '18',
            'Sétif' => '19', 'Saïda' => '20', 'Skikda' => '21',
            'Sidi Bel Abbès' => '22', 'Annaba' => '23', 'Guelma' => '24',
            'Constantine' => '25', 'Médéa' => '26', 'Mostaganem' => '27',
            "M'Sila" => '28', 'Mascara' => '29', 'Ouargla' => '30',
            'Oran' => '31', 'El Bayadh' => '32', 'Illizi' => '33',
            'Bordj Bou Arréridj' => '34', 'Boumerdès' => '35',
            'El Tarf' => '36', 'Tindouf' => '37', 'Tissemsilt' => '38',
            'El Oued' => '39', 'Khenchela' => '40', 'Souk Ahras' => '41',
            'Tipaza' => '42', 'Mila' => '43', 'Aïn Defla' => '44',
            'Naâma' => '45', 'Aïn Témouchent' => '46', 'Ghardaïa' => '47',
            'Relizane' => '48', 'Timimoun' => '49', 'Bordj Badji Mokhtar' => '50',
            'Ouled Djellal' => '51', 'Béni Abbès' => '52', 'In Salah' => '53',
            'In Guezzam' => '54', 'Touggourt' => '55', 'Djanet' => '56',
            "El M'Ghair" => '57', 'El Meniaa' => '58'
        ];

        // Nettoyer la valeur
        $wilayaId = trim($wilayaId);

        // Si c'est un nom de wilaya, le convertir en code
        if (isset($wilayaMapping[$wilayaId])) {
            $wilayaId = $wilayaMapping[$wilayaId];
        }
        // Sinon, si c'est un nombre, le formater sur 2 chiffres
        elseif (is_numeric($wilayaId)) {
            $wilayaId = str_pad($wilayaId, 2, '0', STR_PAD_LEFT);
        }

        \Log::info("Recherche gestionnaire pour wilaya: " . $wilayaId);

        return Gestionnaire::where('wilaya_id', $wilayaId)
            ->where('status', 'active')
            ->with('user')
            ->first();
    }
}
