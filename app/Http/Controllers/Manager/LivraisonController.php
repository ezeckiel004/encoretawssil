<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Colis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Livraison;
use App\Models\DemandeLivraison;
use App\Models\Livreur;
use App\Models\LivreurAssignation;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class LivraisonController extends Controller
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

            // Injecter la wilaya du gestionnaire dans la requête
            $request->merge(['gestionnaire_wilaya' => $gestionnaire->wilaya_id]);

            return $next($request);
        });
    }

    /**
     * Table de correspondance code wilaya -> nom
     */
    private function getWilayaNameFromCode($code): ?string
    {
        $wilayas = [
            '01' => 'Adrar',
            '02' => 'Chlef',
            '03' => 'Laghouat',
            '04' => 'Oum El Bouaghi',
            '05' => 'Batna',
            '06' => 'Béjaïa',
            '07' => 'Biskra',
            '08' => 'Béchar',
            '09' => 'Blida',
            '10' => 'Bouira',
            '11' => 'Tamanrasset',
            '12' => 'Tébessa',
            '13' => 'Tlemcen',
            '14' => 'Tiaret',
            '15' => 'Tizi Ouzou',
            '16' => 'Alger',
            '17' => 'Djelfa',
            '18' => 'Jijel',
            '19' => 'Sétif',
            '20' => 'Saïda',
            '21' => 'Skikda',
            '22' => 'Sidi Bel Abbès',
            '23' => 'Annaba',
            '24' => 'Guelma',
            '25' => 'Constantine',
            '26' => 'Médéa',
            '27' => 'Mostaganem',
            '28' => 'M\'Sila',
            '29' => 'Mascara',
            '30' => 'Ouargla',
            '31' => 'Oran',
            '32' => 'El Bayadh',
            '33' => 'Illizi',
            '34' => 'Bordj Bou Arréridj',
            '35' => 'Boumerdès',
            '36' => 'El Tarf',
            '37' => 'Tindouf',
            '38' => 'Tissemsilt',
            '39' => 'El Oued',
            '40' => 'Khenchela',
            '41' => 'Souk Ahras',
            '42' => 'Tipaza',
            '43' => 'Mila',
            '44' => 'Aïn Defla',
            '45' => 'Naâma',
            '46' => 'Aïn Témouchent',
            '47' => 'Ghardaïa',
            '48' => 'Relizane',
            '49' => 'Timimoun',
            '50' => 'Bordj Badji Mokhtar',
            '51' => 'Ouled Djellal',
            '52' => 'Béni Abbès',
            '53' => 'In Salah',
            '54' => 'In Guezzam',
            '55' => 'Touggourt',
            '56' => 'Djanet',
            '57' => 'El M\'Ghair',
            '58' => 'El Meniaa'
        ];

        return $wilayas[$code] ?? null;
    }

    /**
     * Lister les livraisons de la wilaya
     */
    public function index(Request $request): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);

        // Construire la requête
        $query = Livraison::with([
            'demandeLivraison.client.user',
            'demandeLivraison.destinataire.user',
            'demandeLivraison.colis',
            'livreurDistributeur.user',
            'livreurRamasseur.user',
            'commentaires'
        ]);

        // Filtrer par wilaya (soit par code, soit par nom)
        $query->whereHas('demandeLivraison', function ($q) use ($wilayaCode, $wilayaNom) {
            $q->where(function ($subQuery) use ($wilayaCode, $wilayaNom) {
                // Filtrer par code (si le champ contient un code)
                $subQuery->where('wilaya', $wilayaCode);

                // Filtrer par nom (si le champ contient un nom)
                if ($wilayaNom) {
                    $subQuery->orWhere('wilaya', 'like', '%' . $wilayaNom . '%');
                }

                // Filtrer par wilaya_depot aussi (au cas où)
                $subQuery->orWhere('wilaya_depot', $wilayaCode);
                if ($wilayaNom) {
                    $subQuery->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%');
                }
            });
        });

        // Paginer les résultats
        $livraisons = $query->orderBy('created_at', 'desc')->paginate(20);

        // Formater les données avec payment_status
        $formattedData = $livraisons->map(function ($livraison) {
            return [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'created_at' => $livraison->created_at,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'navette_id' => $livraison->navette_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'payment_status' => $livraison->payment_status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison ? [
                    'id' => $livraison->demandeLivraison->id,
                    'client_id' => $livraison->demandeLivraison->client_id,
                    'destinataire_id' => $livraison->demandeLivraison->destinataire_id,
                    'colis_id' => $livraison->demandeLivraison->colis_id,
                    'addresse_depot' => $livraison->demandeLivraison->addresse_depot,
                    'addresse_delivery' => $livraison->demandeLivraison->addresse_delivery,
                    'info_additionnel' => $livraison->demandeLivraison->info_additionnel,
                    'prix' => $livraison->demandeLivraison->prix,
                    'wilaya' => $livraison->demandeLivraison->wilaya,
                    'commune' => $livraison->demandeLivraison->commune,
                    'wilaya_depot' => $livraison->demandeLivraison->wilaya_depot,
                    'commune_depot' => $livraison->demandeLivraison->commune_depot,
                    'depose_au_depot' => $livraison->demandeLivraison->depose_au_depot,
                    'type_livraison' => $livraison->demandeLivraison->type_livraison,
                    'prestation' => $livraison->demandeLivraison->prestation,
                    'colis' => $livraison->demandeLivraison->colis,
                ] : null,
                'destinataire' => $livraison->demandeLivraison?->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedData,
            'pagination' => [
                'current_page' => $livraisons->currentPage(),
                'last_page' => $livraisons->lastPage(),
                'per_page' => $livraisons->perPage(),
                'total' => $livraisons->total(),
            ]
        ], 200);
    }
    /**
     * Voir une livraison spécifique
     */
    public function show(Request $request, $id): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);

        $livraison = Livraison::with([
            'demandeLivraison.client.user',
            'demandeLivraison.destinataire.user',
            'demandeLivraison.colis',
            'livreurDistributeur.user',
            'livreurRamasseur.user',
            'commentaires'
        ])
            ->whereHas('demandeLivraison', function ($q) use ($wilayaCode, $wilayaNom) {
                $q->where(function ($subQuery) use ($wilayaCode, $wilayaNom) {
                    $subQuery->where('wilaya', $wilayaCode)
                        ->orWhere('wilaya', 'like', '%' . $wilayaNom . '%')
                        ->orWhere('wilaya_depot', $wilayaCode)
                        ->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%');
                });
            })
            ->find($id);

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable dans votre wilaya'
            ], 404);
        }

        $demande = $livraison->demandeLivraison;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'created_at' => $livraison->created_at,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'navette_id' => $livraison->navette_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'payment_status' => $livraison->payment_status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $demande ? [
                    'id' => $demande->id,
                    'client_id' => $demande->client_id,
                    'destinataire_id' => $demande->destinataire_id,
                    'colis_id' => $demande->colis_id,
                    'addresse_depot' => $demande->addresse_depot,
                    'addresse_delivery' => $demande->addresse_delivery,
                    'info_additionnel' => $demande->info_additionnel,
                    'prix' => $demande->prix,
                    'wilaya_depot' => $demande->wilaya_depot,
                    'commune_depot' => $demande->commune_depot,
                    'wilaya' => $demande->wilaya,
                    'commune' => $demande->commune,
                    'depose_au_depot' => $demande->depose_au_depot,
                    'type_livraison' => $demande->type_livraison,
                    'prestation' => $demande->prestation,
                    'colis' => $demande->colis,
                ] : null,
                'destinataire' => $livraison->demandeLivraison?->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
            ]
        ], 200);
    }
    /**
     * Rechercher des livraisons
     */
    public function search(Request $request): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);
        $term = $request->query('q', '');

        $query = Livraison::with([
            'demandeLivraison.client.user',
            'demandeLivraison.destinataire.user',
            'demandeLivraison.colis',
            'livreurDistributeur.user',
            'livreurRamasseur.user',
            'commentaires'
        ]);

        // Filtrer par wilaya
        $query->whereHas('demandeLivraison', function ($q) use ($wilayaCode, $wilayaNom) {
            $q->where(function ($subQuery) use ($wilayaCode, $wilayaNom) {
                $subQuery->where('wilaya', $wilayaCode)
                    ->orWhere('wilaya', 'like', '%' . $wilayaNom . '%')
                    ->orWhere('wilaya_depot', $wilayaCode)
                    ->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%');
            });
        });

        // Filtrer par terme de recherche
        if (!empty($term)) {
            $query->where(function ($q) use ($term) {
                $q->where('code_pin', 'like', "%{$term}%")
                    ->orWhereHas('demandeLivraison.client.user', function ($u) use ($term) {
                        $u->where('nom', 'like', "%{$term}%")
                            ->orWhere('prenom', 'like', "%{$term}%")
                            ->orWhere('telephone', 'like', "%{$term}%");
                    })
                    ->orWhereHas('demandeLivraison.destinataire.user', function ($u) use ($term) {
                        $u->where('nom', 'like', "%{$term}%")
                            ->orWhere('prenom', 'like', "%{$term}%")
                            ->orWhere('telephone', 'like', "%{$term}%");
                    })
                    ->orWhereHas('demandeLivraison.colis', function ($c) use ($term) {
                        $c->where('colis_label', 'like', "%{$term}%");
                    });
            });
        }

        $livraisons = $query->orderBy('created_at', 'desc')->paginate(20);

        $formattedData = $livraisons->map(function ($livraison) {
            return [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'created_at' => $livraison->created_at,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'client' => $livraison->client?->user,
                'destinataire' => $livraison->demandeLivraison?->destinataire?->user,
                'colis' => $livraison->demandeLivraison?->colis,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedData,
            'pagination' => [
                'current_page' => $livraisons->currentPage(),
                'last_page' => $livraisons->lastPage(),
                'per_page' => $livraisons->perPage(),
                'total' => $livraisons->total(),
            ]
        ], 200);
    }

    /**
     * Filtrer les livraisons par statut
     */
    public function byStatus(Request $request, $status): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);

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
                'message' => 'Statut invalide'
            ], 422);
        }

        $query = Livraison::with([
            'demandeLivraison.client.user',
            'demandeLivraison.destinataire.user',
            'demandeLivraison.colis',
            'livreurDistributeur.user',
            'livreurRamasseur.user'
        ])
            ->whereHas('demandeLivraison', function ($q) use ($wilayaCode, $wilayaNom) {
                $q->where(function ($subQuery) use ($wilayaCode, $wilayaNom) {
                    $subQuery->where('wilaya', $wilayaCode)
                        ->orWhere('wilaya', 'like', '%' . $wilayaNom . '%')
                        ->orWhere('wilaya_depot', $wilayaCode)
                        ->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%');
                });
            })
            ->where('status', $status);

        $livraisons = $query->orderBy('created_at', 'desc')->paginate(20);

        $formattedData = $livraisons->map(function ($livraison) {
            return [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'created_at' => $livraison->created_at,
                'status' => $livraison->status,
                'code_pin' => $livraison->code_pin,
                'client' => $livraison->client?->user,
                'destinataire' => $livraison->demandeLivraison?->destinataire?->user,
                'colis' => $livraison->demandeLivraison?->colis,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedData,
            'pagination' => [
                'current_page' => $livraisons->currentPage(),
                'last_page' => $livraisons->lastPage(),
                'per_page' => $livraisons->perPage(),
                'total' => $livraisons->total(),
            ]
        ], 200);
    }

    /**
     * Mettre à jour le statut d'une livraison
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:prise_en_charge_ramassage,ramasse,en_transit,prise_en_charge_livraison,livre,annule'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $livraison = Livraison::whereHas('demandeLivraison', function ($q) use ($wilayaCode, $wilayaNom) {
            $q->where(function ($subQuery) use ($wilayaCode, $wilayaNom) {
                $subQuery->where('wilaya', $wilayaCode)
                    ->orWhere('wilaya', 'like', '%' . $wilayaNom . '%')
                    ->orWhere('wilaya_depot', $wilayaCode)
                    ->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%');
            });
        })->find($id);

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable dans votre wilaya'
            ], 404);
        }

        $livraison->update([
            'status' => $request->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour avec succès',
            'data' => [
                'id' => $livraison->id,
                'status' => $livraison->status
            ]
        ], 200);
    }

    /**
     * ⭐ NOUVELLE MÉTHODE : Attribuer un livreur à une livraison (pour gestionnaire)
     */
    public function assignLivreur(Request $request, $id): JsonResponse
    {
        Log::info("Manager - Début assignation livreur pour livraison ID: " . $id);

        // Validation des données
        $validator = Validator::make($request->all(), [
            'livreur_id' => 'required|string|exists:livreurs,id',
            'type' => 'required|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();
        $type = $validatedData['type'];
        $livreurId = $validatedData['livreur_id'];

        // Récupérer le gestionnaire connecté et sa wilaya
        $user = Auth::user();
        $gestionnaire = $user->gestionnaire;

        if (!$gestionnaire) {
            return response()->json([
                'success' => false,
                'message' => 'Profil gestionnaire introuvable',
            ], 403);
        }

        $wilayaGestionnaire = $gestionnaire->wilaya_id;
        Log::info("Manager wilaya: " . $wilayaGestionnaire);

        // Récupérer la livraison avec sa demande
        $livraison = Livraison::with(['demandeLivraison'])->find($id);

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }

        $demande = $livraison->demandeLivraison;

        // Récupérer les wilayas de départ et d'arrivée
        $wilayaDepartRaw = $demande->wilaya_depot;
        $wilayaArriveeRaw = $demande->wilaya;

        // Convertir en codes pour comparaison
        $wilayaDepartCode = $this->getWilayaCodeFromName($wilayaDepartRaw);
        $wilayaArriveeCode = $this->getWilayaCodeFromName($wilayaArriveeRaw);

        Log::info("Normalisation wilayas", [
            'depart_raw' => $wilayaDepartRaw,
            'depart_code' => $wilayaDepartCode,
            'arrivee_raw' => $wilayaArriveeRaw,
            'arrivee_code' => $wilayaArriveeCode,
            'gestionnaire_code' => $wilayaGestionnaire
        ]);

        // Vérifier si la livraison concerne la wilaya du gestionnaire (comparer les codes)
        $concerneWilaya = ($wilayaDepartCode == $wilayaGestionnaire) || ($wilayaArriveeCode == $wilayaGestionnaire);

        if (!$concerneWilaya) {
            Log::warning("Manager tentant d'assigner un livreur à une livraison hors de sa wilaya", [
                'gestionnaire_wilaya' => $wilayaGestionnaire,
                'wilaya_depart_code' => $wilayaDepartCode,
                'wilaya_arrivee_code' => $wilayaArriveeCode,
                'wilaya_depart_raw' => $wilayaDepartRaw,
                'wilaya_arrivee_raw' => $wilayaArriveeRaw
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas assigner de livreur à cette livraison car elle ne concerne pas votre wilaya.',
            ], 403);
        }

        // VÉRIFICATION : Le livreur doit être disponible pour ce gestionnaire
        $livreur = Livreur::with('user')->find($livreurId);

        if (!$livreur) {
            return response()->json([
                'success' => false,
                'message' => 'Livreur introuvable',
            ], 404);
        }

        // Vérifier si le livreur est disponible pour ce gestionnaire
        $estDisponible = $this->verifierDisponibiliteLivreur($livreur, $gestionnaire);

        if (!$estDisponible) {
            return response()->json([
                'success' => false,
                'message' => 'Ce livreur n\'est pas disponible dans votre wilaya. Seuls les livreurs de votre wilaya ou ceux qui vous sont assignés peuvent être sélectionnés.',
            ], 403);
        }

        // Vérifier les conditions selon le type de livreur
        try {
            DB::beginTransaction();

            if ($type == 2) {
                // Type 2 : Distributeur
                if ($livraison->status !== 'en_transit') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le distributeur ne peut être assigné que lorsque la livraison est en transit',
                    ], 400);
                }

                $livraison->update([
                    'livreur_distributeur_id' => $livreurId,
                ]);

                Log::info("Manager - Distributeur assigné", [
                    'livraison_id' => $id,
                    'livreur_id' => $livreurId,
                    'gestionnaire_id' => $gestionnaire->id,
                ]);
            } elseif ($type == 1) {
                // Type 1 : Ramasseur
                if ($livraison->status === 'ramasse') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le colis a déjà été ramassé, vous ne pouvez plus attribuer un autre ramasseur',
                    ], 400);
                }

                $livraison->update([
                    'livreur_ramasseur_id' => $livreurId,
                    'status' => 'prise_en_charge_ramassage'
                ]);

                Log::info("Manager - Ramasseur assigné", [
                    'livraison_id' => $id,
                    'livreur_id' => $livreurId,
                    'gestionnaire_id' => $gestionnaire->id,
                    'nouveau_statut' => 'prise_en_charge_ramassage',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Type de livreur invalide',
                ], 400);
            }

            DB::commit();

            // Recharger la livraison avec les relations
            $livraison->load([
                'livreurRamasseur.user',
                'livreurDistributeur.user',
                'demandeLivraison',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Livreur attribué avec succès',
                'data' => $livraison,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur lors de l'assignation du livreur par manager: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'attribution du livreur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ⭐ NOUVELLE MÉTHODE : Vérifier si un livreur est disponible pour un gestionnaire
     *
     * @param Livreur $livreur
     * @param Gestionnaire $gestionnaire
     * @return bool
     */
    private function verifierDisponibiliteLivreur($livreur, $gestionnaire): bool
    {
        // Cas 1 : Le livreur est natif de la wilaya du gestionnaire
        if ($livreur->wilaya_id == $gestionnaire->wilaya_id) {
            Log::info("Livreur natif de la wilaya", [
                'livreur_id' => $livreur->id,
                'wilaya_livreur' => $livreur->wilaya_id,
                'wilaya_gestionnaire' => $gestionnaire->wilaya_id,
            ]);
            return true;
        }

        // Cas 2 : Le livreur a été assigné à ce gestionnaire par l'admin
        $assignationActive = LivreurAssignation::where('livreur_id', $livreur->id)
            ->where('gestionnaire_id', $gestionnaire->id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('date_fin')
                    ->orWhere('date_fin', '>=', now());
            })
            ->exists();

        if ($assignationActive) {
            Log::info("Livreur assigné à ce gestionnaire", [
                'livreur_id' => $livreur->id,
                'gestionnaire_id' => $gestionnaire->id,
            ]);
            return true;
        }

        Log::warning("Livreur non disponible pour ce gestionnaire", [
            'livreur_id' => $livreur->id,
            'wilaya_livreur' => $livreur->wilaya_id,
            'gestionnaire_wilaya' => $gestionnaire->wilaya_id,
        ]);

        return false;
    }

    /**
     * ⭐ NOUVELLE MÉTHODE : Récupérer les livreurs disponibles pour le gestionnaire
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getLivreursDisponibles(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable',
                ], 403);
            }

            $wilayaGestionnaire = $gestionnaire->wilaya_id;

            // 1. Livreurs natifs de la wilaya (TOUS les livreurs, quel que soit leur type)
            $livreursNatifs = Livreur::with('user')
                ->where('wilaya_id', $wilayaGestionnaire)
                ->where('desactiver', false)
                ->get();

            // 2. Livreurs assignés à ce gestionnaire par l'admin (TOUS les livreurs)
            $livreursAssignes = Livreur::with('user')
                ->whereHas('assignations', function ($query) use ($gestionnaire) {
                    $query->where('gestionnaire_id', $gestionnaire->id)
                        ->where('status', 'active')
                        ->where(function ($q) {
                            $q->whereNull('date_fin')
                                ->orWhere('date_fin', '>=', now());
                        });
                })
                ->where('desactiver', false)
                ->get();

            // Fusionner et dédupliquer
            $tousLivreurs = $livreursNatifs->merge($livreursAssignes)->unique('id');

            // ⚠️ PLUS DE FILTRE PAR TYPE - TOUS LES LIVREURS SONT AFFICHÉS

            // Formater pour le select
            $formattedLivreurs = $tousLivreurs->map(function ($livreur) use ($wilayaGestionnaire) {
                return [
                    'value' => $livreur->id,
                    'label' => trim(($livreur->user->prenom ?? '') . ' ' . ($livreur->user->nom ?? '')),
                    'telephone' => $livreur->user->telephone ?? '',
                    'type' => $livreur->type,
                    'wilaya_id' => $livreur->wilaya_id,
                    'origine' => $livreur->wilaya_id == $wilayaGestionnaire ? 'natif' : 'assigne',
                ];
            })->values();

            Log::info("Livreurs disponibles pour gestionnaire (sans filtre type)", [
                'gestionnaire_id' => $gestionnaire->id,
                'wilaya' => $wilayaGestionnaire,
                'total' => $formattedLivreurs->count(),
                'natifs' => $livreursNatifs->count(),
                'assignes' => $livreursAssignes->count(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $formattedLivreurs,
                'meta' => [
                    'wilaya_gestionnaire' => $wilayaGestionnaire,
                    'total' => $formattedLivreurs->count(),
                    'natifs' => $livreursNatifs->count(),
                    'assignes' => $livreursAssignes->count(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur getLivreursDisponibles manager: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livreurs disponibles',
            ], 500);
        }
    }

    /**
     * Convertir un nom de wilaya en code
     */
    private function getWilayaCodeFromName($wilayaName): ?string
    {
        $wilayaMapping = [
            'Adrar' => '01',
            'Chlef' => '02',
            'Laghouat' => '03',
            'Oum El Bouaghi' => '04',
            'Batna' => '05',
            'Béjaïa' => '06',
            'Biskra' => '07',
            'Béchar' => '08',
            'Blida' => '09',
            'Bouira' => '10',
            'Tamanrasset' => '11',
            'Tébessa' => '12',
            'Tlemcen' => '13',
            'Tiaret' => '14',
            'Tizi Ouzou' => '15',
            'Alger' => '16',
            'Djelfa' => '17',
            'Jijel' => '18',
            'Sétif' => '19',
            'Saïda' => '20',
            'Skikda' => '21',
            'Sidi Bel Abbès' => '22',
            'Annaba' => '23',
            'Guelma' => '24',
            'Constantine' => '25',
            'Médéa' => '26',
            'Mostaganem' => '27',
            "M'Sila" => '28',
            'Mascara' => '29',
            'Ouargla' => '30',
            'Oran' => '31',
            'El Bayadh' => '32',
            'Illizi' => '33',
            'Bordj Bou Arréridj' => '34',
            'Boumerdès' => '35',
            'El Tarf' => '36',
            'Tindouf' => '37',
            'Tissemsilt' => '38',
            'El Oued' => '39',
            'Khenchela' => '40',
            'Souk Ahras' => '41',
            'Tipaza' => '42',
            'Mila' => '43',
            'Aïn Defla' => '44',
            'Naâma' => '45',
            'Aïn Témouchent' => '46',
            'Ghardaïa' => '47',
            'Relizane' => '48',
            'Timimoun' => '49',
            'Bordj Badji Mokhtar' => '50',
            'Ouled Djellal' => '51',
            'Béni Abbès' => '52',
            'In Salah' => '53',
            'In Guezzam' => '54',
            'Touggourt' => '55',
            'Djanet' => '56',
            "El M'Ghair" => '57',
            'El Meniaa' => '58'
        ];

        // Nettoyer la valeur
        $wilayaName = trim($wilayaName);

        // Si c'est déjà un code (ex: "16"), le retourner
        if (isset($wilayaMapping[$wilayaName])) {
            return $wilayaMapping[$wilayaName];
        }

        // Chercher par nom
        foreach ($wilayaMapping as $nom => $code) {
            if (strcasecmp($nom, $wilayaName) === 0) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Mettre à jour le statut de paiement d'une livraison
     */
    public function updatePaymentStatus(Request $request, $id): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);

        $validator = Validator::make($request->all(), [
            'payment_status' => 'required|string|in:pending,available,in_transit,paid'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier que la livraison appartient à la wilaya du gestionnaire
        $livraison = Livraison::whereHas('demandeLivraison', function ($q) use ($wilayaCode, $wilayaNom) {
            $q->where(function ($subQuery) use ($wilayaCode, $wilayaNom) {
                $subQuery->where('wilaya', $wilayaCode)
                    ->orWhere('wilaya', 'like', '%' . $wilayaNom . '%')
                    ->orWhere('wilaya_depot', $wilayaCode)
                    ->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%');
            });
        })->find($id);

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable dans votre wilaya'
            ], 404);
        }

        $livraison->update([
            'payment_status' => $request->payment_status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Statut de paiement mis à jour avec succès',
            'data' => [
                'id' => $livraison->id,
                'payment_status' => $livraison->payment_status
            ]
        ], 200);
    }

    /**
     * Récupérer les données pour l'édition d'une livraison (Manager)
     */
    public function edit(Request $request, $id): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);

        $livraison = Livraison::with([
            'demandeLivraison.client.user',
            'demandeLivraison.destinataire.user',
            'demandeLivraison.colis',
            'livreurDistributeur.user',
            'livreurRamasseur.user',
            'commentaires'
        ])
            ->whereHas('demandeLivraison', function ($q) use ($wilayaCode, $wilayaNom) {
                $q->where(function ($subQuery) use ($wilayaCode, $wilayaNom) {
                    $subQuery->where('wilaya', $wilayaCode)
                        ->orWhere('wilaya', 'like', '%' . $wilayaNom . '%')
                        ->orWhere('wilaya_depot', $wilayaCode)
                        ->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%');
                });
            })
            ->find($id);

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable dans votre wilaya'
            ], 404);
        }

        $demande = $livraison->demandeLivraison;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'navette_id' => $livraison->navette_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'payment_status' => $livraison->payment_status,
                'created_at' => $livraison->created_at,
                'updated_at' => $livraison->updated_at,
                'demande_livraison' => $demande ? [
                    'id' => $demande->id,
                    'addresse_depot' => $demande->addresse_depot,
                    'addresse_delivery' => $demande->addresse_delivery,
                    'info_additionnel' => $demande->info_additionnel,
                    'prix' => $demande->prix,
                    'wilaya_depot' => $demande->wilaya_depot,
                    'commune_depot' => $demande->commune_depot,
                    'wilaya' => $demande->wilaya,
                    'commune' => $demande->commune,
                    'depose_au_depot' => $demande->depose_au_depot,
                    'type_livraison' => $demande->type_livraison,
                    'prestation' => $demande->prestation,
                ] : null,
                'colis' => $demande && $demande->colis ? [
                    'id' => $demande->colis->id,
                    'colis_label' => $demande->colis->colis_label,
                    'poids' => $demande->colis->poids,
                    'colis_type' => $demande->colis->colis_type,
                    'colis_prix' => $demande->colis->colis_prix,
                ] : null,
                'client' => $livraison->client ? [
                    'id' => $livraison->client->id,
                    'nom' => $livraison->client->user?->nom,
                    'prenom' => $livraison->client->user?->prenom,
                    'telephone' => $livraison->client->user?->telephone,
                    'email' => $livraison->client->user?->email,
                ] : null,
                'destinataire' => $demande && $demande->destinataire ? [
                    'id' => $demande->destinataire->id,
                    'nom' => $demande->destinataire->user?->nom,
                    'prenom' => $demande->destinataire->user?->prenom,
                    'telephone' => $demande->destinataire->user?->telephone,
                    'email' => $demande->destinataire->user?->email,
                ] : null,
                'livreur_ramasseur' => $livraison->livreurRamasseur ? [
                    'id' => $livraison->livreurRamasseur->id,
                    'nom' => $livraison->livreurRamasseur->user?->nom,
                    'prenom' => $livraison->livreurRamasseur->user?->prenom,
                    'telephone' => $livraison->livreurRamasseur->user?->telephone,
                ] : null,
                'livreur_distributeur' => $livraison->livreurDistributeur ? [
                    'id' => $livraison->livreurDistributeur->id,
                    'nom' => $livraison->livreurDistributeur->user?->nom,
                    'prenom' => $livraison->livreurDistributeur->user?->prenom,
                    'telephone' => $livraison->livreurDistributeur->user?->telephone,
                ] : null,
            ]
        ], 200);
    }

    /**
     * Mettre à jour une livraison (Manager)
     */
    public function update(Request $request, $id): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);

        Log::info("Manager update - ID: " . $id);
        Log::info("Manager update - Données reçues: " . json_encode($request->all()));

        $validator = Validator::make($request->all(), [
            // Livraison fields
            'livreur_distributeur_id' => 'nullable|string|exists:livreurs,id',
            'livreur_ramasseur_id' => 'nullable|string|exists:livreurs,id',
            'bordereau_id' => 'nullable|string|exists:bordereaux,id',
            'navette_id' => 'nullable|string|exists:navettes,id',
            'code_pin' => 'nullable|string|size:5',
            'date_ramassage' => 'nullable|date',
            'date_livraison' => 'nullable|date',
            'status' => 'sometimes|string|in:en_attente,prise_en_charge_ramassage,ramasse,en_transit,prise_en_charge_livraison,livre,annule',
            'payment_status' => 'sometimes|string|in:pending,available,in_transit,paid',

            // Demande livraison fields
            'addresse_depot' => 'nullable|string|max:500',
            'addresse_delivery' => 'nullable|string|max:500',
            'info_additionnel' => 'nullable|string',
            'prix' => 'nullable|numeric|min:0',
            'wilaya_depot' => 'nullable|string|max:255',
            'commune_depot' => 'nullable|string|max:255',
            'wilaya' => 'nullable|string|max:255',
            'commune' => 'nullable|string|max:255',
            'type_livraison' => 'nullable|string|in:Livraison,Échange,Pick-up',
            'prestation' => 'nullable|string|in:A domicile,Stop Desk',
            'depose_au_depot' => 'nullable|boolean',

            // Colis fields
            'colis_poids' => 'nullable|numeric|min:0',
            'colis_type' => 'nullable|string|max:255',
            'colis_prix' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            Log::error("Validation échouée: " . json_encode($validator->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier que la livraison appartient à la wilaya du gestionnaire
        $livraison = Livraison::with(['demandeLivraison', 'demandeLivraison.colis'])
            ->whereHas('demandeLivraison', function ($q) use ($wilayaCode, $wilayaNom) {
                $q->where(function ($subQuery) use ($wilayaCode, $wilayaNom) {
                    $subQuery->where('wilaya', $wilayaCode)
                        ->orWhere('wilaya', 'like', '%' . $wilayaNom . '%')
                        ->orWhere('wilaya_depot', $wilayaCode)
                        ->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%');
                });
            })
            ->find($id);

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable dans votre wilaya'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $validatedData = $validator->validated();

            // 1. Mettre à jour la livraison
            $livraisonFields = array_intersect_key($validatedData, array_flip([
                'livreur_distributeur_id',
                'livreur_ramasseur_id',
                'bordereau_id',
                'navette_id',
                'code_pin',
                'date_ramassage',
                'date_livraison',
                'status',
                'payment_status'
            ]));

            if (!empty($livraisonFields)) {
                $livraison->update($livraisonFields);
                Log::info("Livraison mise à jour: " . json_encode($livraisonFields));
            }

            // 2. Mettre à jour la demande de livraison
            $demande = $livraison->demandeLivraison;
            if ($demande) {
                $demandeFields = array_intersect_key($validatedData, array_flip([
                    'addresse_depot',
                    'addresse_delivery',
                    'info_additionnel',
                    'prix',
                    'wilaya_depot',
                    'commune_depot',
                    'wilaya',
                    'commune',
                    'type_livraison',
                    'prestation',
                    'depose_au_depot'
                ]));

                if (!empty($demandeFields)) {
                    $demande->update($demandeFields);
                    Log::info("Demande livraison mise à jour: " . json_encode($demandeFields));
                }
            }

            // 3. Mettre à jour le colis
            $colis = $demande ? $demande->colis : null;
            if ($colis) {
                $colisFields = [];
                if (isset($validatedData['colis_poids'])) $colisFields['poids'] = $validatedData['colis_poids'];
                if (isset($validatedData['colis_type'])) $colisFields['colis_type'] = $validatedData['colis_type'];
                if (isset($validatedData['colis_prix'])) $colisFields['colis_prix'] = $validatedData['colis_prix'];

                if (!empty($colisFields)) {
                    $colis->update($colisFields);
                    Log::info("Colis mis à jour: " . json_encode($colisFields));
                }
            }

            DB::commit();

            // Recharger la livraison avec les relations
            $livraison->load([
                'demandeLivraison.client.user',
                'demandeLivraison.destinataire.user',
                'demandeLivraison.colis',
                'livreurDistributeur.user',
                'livreurRamasseur.user'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Livraison mise à jour avec succès',
                'data' => $livraison
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur update livraison manager: " . $e->getMessage());
            Log::error("Trace: " . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une nouvelle livraison (gestionnaire)
     */
    public function store(Request $request): JsonResponse
    {
        Log::info("Manager - Début création d'une nouvelle livraison");

        $validator = Validator::make($request->all(), [
            // Client
            'client_id' => 'required|string|exists:clients,id',

            // Destinataire
            'destinataire_nom' => 'required|string|max:255',
            'destinataire_email' => 'nullable|email|max:255',
            'destinataire_telephone' => 'required|string|max:20',

            // Adresses
            'addresse_depot' => 'nullable|string|max:500',
            'addresse_delivery' => 'required|string|max:500',
            'wilaya_depot' => 'nullable|string|max:255',
            'commune_depot' => 'nullable|string|max:255',
            'wilaya' => 'required|string|max:255',
            'commune' => 'required|string|max:255',

            // Colis
            'colis_label' => 'nullable|string|max:255',
            'colis_poids' => 'required|numeric|min:0.1',
            'colis_type' => 'nullable|string|max:255',
            'colis_prix' => 'nullable|numeric|min:0',

            // Prix
            'prix' => 'required|numeric|min:0',

            // Statut paiement
            'payment_status' => 'required|string|in:pending,available,in_transit,paid',

            // Mode dépôt
            'depose_au_depot' => 'boolean',

            // Livreurs (optionnels)
            'livreur_ramasseur_id' => 'nullable|string|exists:livreurs,id',
            'livreur_distributeur_id' => 'nullable|string|exists:livreurs,id',

            // Instructions
            'info_additionnel' => 'nullable|string',

            // NOUVEAUX CHAMPS
            'type_livraison' => 'nullable|string|in:Livraison,Échange,Pick-up',
            'prestation' => 'nullable|string|in:A domicile,Stop Desk',
        ]);

        if ($validator->fails()) {
            Log::warning("Validation échouée création livraison: " . json_encode($validator->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();

        try {
            DB::beginTransaction();

            // 1. Créer ou récupérer le destinataire
            $destinataire = $this->createOrGetDestinataire($validatedData);

            // 2. Créer le colis
            $colis = $this->createColis($validatedData);

            // 3. Créer la demande de livraison
            $demande = $this->createDemandeLivraison($validatedData, $destinataire->id, $colis->id);

            // 4. Déterminer le statut initial
            $isDepotClient = $validatedData['depose_au_depot'] ?? false;
            $initialStatus = 'en_attente';

            // 5. Générer un code PIN unique
            $codePin = $this->generateUniquePin();

            // 6. Créer la livraison
            $livraison = Livraison::create([
                'id' => (string) Str::uuid(),
                'client_id' => $validatedData['client_id'],
                'demande_livraisons_id' => $demande->id,
                'livreur_distributeur_id' => $validatedData['livreur_distributeur_id'] ?? null,
                'livreur_ramasseur_id' => $validatedData['livreur_ramasseur_id'] ?? null,
                'code_pin' => $codePin,
                'date_ramassage' => null,
                'date_livraison' => null,
                'status' => $initialStatus,
                'payment_status' => $validatedData['payment_status'] ?? 'pending',
            ]);

            DB::commit();

            Log::info("Manager - Livraison créée avec succès: " . $livraison->id);

            // Charger les relations pour la réponse
            $livraison->load([
                'client.user',
                'demandeLivraison.client.user',
                'demandeLivraison.destinataire.user',
                'demandeLivraison.colis',
                'livreurRamasseur.user',
                'livreurDistributeur.user'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Livraison créée avec succès',
                'data' => $livraison
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur création livraison par manager: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la livraison',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Créer ou récupérer un destinataire
     */
    private function createOrGetDestinataire($data)
    {
        $user = null;

        if (!empty($data['destinataire_email'])) {
            $user = User::where('email', $data['destinataire_email'])->first();
        }

        if (!$user && !empty($data['destinataire_telephone'])) {
            $user = User::where('telephone', $data['destinataire_telephone'])->first();
        }

        if (!$user) {
            $nameParts = explode(' ', $data['destinataire_nom'], 2);
            $prenom = $nameParts[0] ?? '';
            $nom = $nameParts[1] ?? '';

            $user = User::create([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $data['destinataire_email'] ?? null,
                'telephone' => $data['destinataire_telephone'],
                'password' => bcrypt(Str::random(10)),
                'role' => 'client_destinataire',
                'actif' => true,
            ]);
        }

        return Client::firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 'active']
        );
    }

    /**
     * Créer un colis
     */
    private function createColis($data)
    {
        $colisLabel = $data['colis_label'] ?? 'COLIS-' . strtoupper(uniqid());

        return Colis::create([
            'poids' => $data['colis_poids'],
            'colis_type' => $data['colis_type'] ?? 'Standard',
            'colis_label' => $colisLabel,
            'colis_prix' => $data['colis_prix'] ?? 0,
        ]);
    }

    /**
     * Créer une demande de livraison
     */
    private function createDemandeLivraison($data, $destinataireId, $colisId)
    {
        $isDepotClient = $data['depose_au_depot'] ?? false;

        return DemandeLivraison::create([
            'client_id' => $data['client_id'],
            'destinataire_id' => $destinataireId,
            'colis_id' => $colisId,
            'depose_au_depot' => $isDepotClient,
            'addresse_depot' => $data['addresse_depot'] ?? null,
            'addresse_delivery' => $data['addresse_delivery'],
            'info_additionnel' => $data['info_additionnel'] ?? null,
            'prix' => $data['prix'],
            'wilaya_depot' => $data['wilaya_depot'] ?? null,
            'commune_depot' => $data['commune_depot'] ?? null,
            'wilaya' => $data['wilaya'],
            'commune' => $data['commune'],
            'type_livraison' => $data['type_livraison'] ?? 'Livraison',
            'prestation' => $data['prestation'] ?? 'A domicile',
        ]);
    }

    /**
     * Générer un code PIN unique
     */
    private function generateUniquePin(): string
    {
        do {
            $pin = str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (Livraison::where('code_pin', $pin)->exists());

        return $pin;
    }

    public function getClients(Request $request): JsonResponse
    {
        try {
            $clients = Client::with('user')
                ->whereHas('user', function ($q) {
                    $q->where('actif', true)
                        ->where('role', 'client');
                })
                ->get()
                ->map(function ($client) {
                    return [
                        'id'        => $client->id,
                        'nom'       => $client->user?->nom,
                        'prenom'    => $client->user?->prenom,
                        'telephone' => $client->user?->telephone,
                        'email'     => $client->user?->email,
                    ];
                });

            return response()->json([
                'success' => true,
                'data'    => $clients,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur getClients manager: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des clients',
            ], 500);
        }
    }
}
