<?php

namespace App\Http\Controllers;

use App\Models\DemandeLivraison;
use \App\Models\User;
use Illuminate\Http\Request;
use App\Models\Colis;
use App\Models\Livraison;
use App\Models\Client;
use App\Enums\NotificationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\NewDeliveryRequestMail;
use App\Mail\DeliveryRequestReceivedMail;

class DemandeLivraisonController extends Controller
{
    /**
     * Historique des paiements et statistiques pour le client
     */
    public function paymentHistory(Request $request): JsonResponse
    {
        $client = $request->user()?->client;

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client non authentifié',
            ], 401);
        }

        \Log::info("--- Payment History Debug ---");
        \Log::info("Client ID: " . $client->id);

        // 1. Récupérer l'historique (Demandes qui n'ont pas de livraison annulée)
        $history = DemandeLivraison::where('client_id', $client->id)
            ->where(function($query) {
                $query->whereDoesntHave('livraison')
                      ->orWhereHas('livraison', function($q) {
                          $q->where('status', '!=', 'annule');
                      });
            })
            ->orderBy('created_at', 'desc')
            ->get();
            
        $history->load(['livraison', 'destinataire.user', 'colis']);
        \Log::info("History Count: " . $history->count());

        // 2. Statistiques basées sur le prix du colis (COD)
        // On part de DemandeLivraison pour inclure celles qui n'ont pas encore de record 'Livraison' (statut par défaut 'pending')
        $stats = DemandeLivraison::where('demande_livraisons.client_id', $client->id)
            ->join('colis', 'demande_livraisons.colis_id', '=', 'colis.id')
            ->leftJoin('livraisons', 'demande_livraisons.id', '=', 'livraisons.demande_livraisons_id')
            ->where(function($q) {
                $q->whereNull('livraisons.id')
                  ->orWhere('livraisons.status', '!=', 'annule');
            })
            ->select(
                DB::raw('COALESCE(livraisons.payment_status, "pending") as payment_status_refined'), 
                DB::raw('count(*) as count'), 
                DB::raw('SUM(colis.colis_prix) as total_amount')
            )
            ->groupBy('payment_status_refined')
            ->get();
            
        \Log::info("Raw Stats Result: " . json_encode($stats));
            
        // Transformer les stats en format plus facile à consommer
        $formattedStats = [
            'pending'    => ['count' => 0, 'total' => 0],
            'available'  => ['count' => 0, 'total' => 0],
            'in_transit' => ['count' => 0, 'total' => 0],
            'paid'       => ['count' => 0, 'total' => 0],
            'global'     => ['count' => 0, 'total' => 0],
        ];

        foreach ($stats as $stat) {
            $rawStatus = strtolower($stat->payment_status_refined ?: 'pending');
            $status = 'pending';
            
            if (str_contains($rawStatus, 'available')) $status = 'available';
            elseif (str_contains($rawStatus, 'transit')) $status = 'in_transit';
            elseif (str_contains($rawStatus, 'paid') || str_contains($rawStatus, 'payé')) $status = 'paid';
            elseif (str_contains($rawStatus, 'pending') || str_contains($rawStatus, 'attente')) $status = 'pending';
            else $status = $rawStatus;

            if (isset($formattedStats[$status])) {
                $formattedStats[$status]['count'] += (int)$stat->count;
                $formattedStats[$status]['total'] += (float)($stat->total_amount ?: 0);
            }
            
            // Toujours ajouter au global
            $formattedStats['global']['count'] += (int)$stat->count;
            $formattedStats['global']['total'] += (float)($stat->total_amount ?: 0);
        }
        
        \Log::info("Final Formatted Stats: " . json_encode($formattedStats));

        return response()->json([
            'success' => true,
            'data'    => [
                'history' => $history,
                'stats'   => $formattedStats,
            ],
        ]);
    }

    /**
     * Afficher toutes les demandes de livraison.
     */
    public function index(): JsonResponse
    {
        $demandes = DemandeLivraison::all();

        return response()->json([
            'success' => true,
            'data' => $demandes,
        ], 200);
    }
    
    /**
 * Récupérer les destinataires précédents d'un client
 */
/**
 * Récupérer les destinataires précédents d'un client
 */
public function getPreviousDestinataires(Request $request): JsonResponse
{
    $client = $request->user()?->client;

    if (!$client) {
        Log::warning('getPreviousDestinataires → Client non trouvé pour user ID: ' . ($request->user()?->id ?? 'null'));
        return response()->json([
            'success' => false,
            'message' => 'Client non authentifié',
        ], 401);
    }

    $destinataires = DemandeLivraison::where('client_id', $client->id)
        ->with('destinataire.user')
        ->select('destinataire_id')
        ->distinct()
        ->get()
        ->map(function ($item) {
            $user = $item->destinataire?->user;
            return [
                'id'        => $item->destinataire_id,
                'nom'       => $user?->nom ?? '',
                'prenom'    => $user?->prenom ?? '',
                'email'     => $user?->email ?? '',
                'telephone' => $user?->telephone ?? '',
                'full_name' => trim(($user?->nom ?? '') . ' ' . ($user?->prenom ?? '')),
            ];
        })
        ->filter(fn($d) => !empty($d['full_name']) || !empty($d['telephone']));

    Log::info('getPreviousDestinataires → ' . $destinataires->count() . ' destinataires trouvés pour client ' . $client->id);

    return response()->json([
        'success' => true,
        'data'    => $destinataires,
    ]);
}

    /**
     * Créer une nouvelle demande de livraison.
     */
    /**
     * Créer une nouvelle demande de livraison.
     */public function store(Request $request): JsonResponse
{
    Log::info('=== DEBUT DEMANDE LIVRAISON ===');
    Log::info('depose_au_depot reçu : ' . json_encode($request->input('depose_au_depot')));
    Log::info('All input keys: ' . json_encode(array_keys($request->all())));

    // RÈGLES DE VALIDATION
    $rules = [
        'client_id'              => 'required|string|exists:clients,id',
        'depose_au_depot'        => 'required|in:true,false,1,0',
        'colis_poids'            => 'required|numeric|min:0.1',
        'colis_prix'             => 'required|numeric',
        'prix'                   => 'required|numeric',
        'colis_type'             => 'nullable|string',
        'destinataire_nom'       => 'required|string|max:255',
        'destinataire_email'     => 'nullable|email|max:255',
        'destinataire_telephone' => 'required|string|max:20',
        'colis_photo'            => 'nullable|file|max:10240',

        // === TOUJOURS VALIDÉS (même en mode dépôt) ===
        'wilaya_depot'           => 'nullable|string|max:255',
        'commune_depot'          => 'nullable|string|max:255',
        'wilaya'                 => 'required|string|max:255',
        'commune'                => 'required|string|max:255',
        'addresse_delivery'      => 'nullable|string|max:255',
        'lat_delivery'           => 'nullable|numeric',
        'lng_delivery'           => 'nullable|numeric',
        'type_livraison'         => 'nullable|string|in:Livraison,Échange,Pick-up',
        'prestation'             => 'nullable|string|in:A domicile,Stop Desk',
    ];

    // Si NON déposé au dépôt → champs de départ obligatoires
    if (!$request->boolean('depose_au_depot')) {
        $rules['wilaya_depot']   = 'required|string|max:255';
        $rules['commune_depot']  = 'required|string|max:255';
        $rules['addresse_depot'] = 'required|string|max:255';
        $rules['lat_depot']      = 'required|numeric';
        $rules['lng_depot']      = 'required|numeric';
    }

    $validated = Validator::make($request->all(), $rules);

    if ($validated->fails()) {
        Log::error('Validation errors:', $validated->errors()->toArray());
        return response()->json([
            'success' => false,
            'message' => 'Erreur de validation',
            'errors'  => $validated->errors(),
        ], 422);
    }

    $validatedData = $validated->validated();

    try {
        DB::beginTransaction();

        // === Destinataire ===
        $user = null;
        if (!empty($validatedData['destinataire_email'])) {
            $user = User::where('email', $validatedData['destinataire_email'])->first();
        }
        if (!$user) {
            $user = User::where('telephone', $validatedData['destinataire_telephone'])->first();
        }
        if (!$user) {
            $parts = explode(' ', $validatedData['destinataire_nom']);
            $user = User::create([
                'nom'      => implode(' ', $parts),
                'prenom'   => array_pop($parts),
                'email'    => $validatedData['destinataire_email'] ?? null,
                'telephone'=> $validatedData['destinataire_telephone'],
                'password' => bcrypt('default_password'),
                'role'     => 'client_destinataire',
                'actif'    => true,
            ]);
        }
        $destinataire = Client::firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 'active']
        );

        // === Colis ===
        $colisLabel = 'COLIS-' . strtoupper(uniqid());
        $photoPath = null;
        if ($request->hasFile('colis_photo')) {
            $utilsController = new UtilsController();
            $photoPath = $utilsController->uploadPhoto($request, 'colis_photo');
        }
        $colis = Colis::create([
            'poids'          => $validatedData['colis_poids'],
            'colis_type'     => $validatedData['colis_type'] ?? null,
            'colis_label'    => $colisLabel,
            'colis_photo'    => $photoPath,
            'colis_photo_url'=> $photoPath ? asset('storage/' . $photoPath) : null,
            'colis_prix'     => $validatedData['colis_prix'],
        ]);

        // === Demande de livraison ===
        $demande = DemandeLivraison::create([
            'client_id'          => $validatedData['client_id'],
            'depose_au_depot'    => $request->boolean('depose_au_depot'),
            // Toujours pris du formulaire (même en mode dépôt)
            'wilaya_depot'       => $validatedData['wilaya_depot'] ?? null,
            'commune_depot'      => $validatedData['commune_depot'] ?? null,
            'addresse_depot'     => $request->boolean('depose_au_depot') ? null : ($validatedData['addresse_depot'] ?? null),
            'lat_depot'          => $request->boolean('depose_au_depot') ? null : ($validatedData['lat_depot'] ?? null),
            'lng_depot'          => $request->boolean('depose_au_depot') ? null : ($validatedData['lng_depot'] ?? null),
            'wilaya'             => $validatedData['wilaya'],
            'commune'            => $validatedData['commune'],
            'addresse_delivery'  => $validatedData['addresse_delivery'] ?? null,
            'info_additionnel'   => $validatedData['info_additionnel'] ?? null,
            'destinataire_id'    => $destinataire->id,
            'colis_id'           => $colis->id,
            'prix'               => $validatedData['prix'],
            'type_livraison'     => $validatedData['type_livraison'] ?? 'Livraison',
            'prestation'         => $validatedData['prestation'] ?? 'A domicile',
            'lat_delivery'       => $validatedData['lat_delivery'] ?? null,
            'lng_delivery'       => $validatedData['lng_delivery'] ?? null,
        ]);

        // === Livraison ===
$livraison = Livraison::create([
    'client_id'            => $validatedData['client_id'],
    'demande_livraisons_id'=> $demande->id,
    'code_pin'             => $this->generateUniquePin(),
    'status'               => $request->boolean('depose_au_depot') 
                                ? 'en_transit' 
                                : 'en_attente',
    'payment_status'       => 'pending',     // ← Valeur par défaut (simple)
]);

        // Emails (inchangé)
        try {
            $admin = User::where('role', 'admin')->where('email', 'ziattzi133@gmail.com')->first();
            if ($admin && $admin->email) {
                Mail::to($admin->email)->send(new NewDeliveryRequestMail($demande));
            }
        } catch (\Exception $e) {
            Log::error('Erreur mail admin: ' . $e->getMessage());
        }

        try {
            if ($demande->client && $demande->client->user && $demande->client->user->email) {
                Mail::to($demande->client->user->email)->send(new DeliveryRequestReceivedMail($demande));
            }
        } catch (\Exception $e) {
            Log::error('Erreur mail client: ' . $e->getMessage());
        }

        DB::commit();
        Log::info('Demande créée avec succès ID: ' . $demande->id);

        return response()->json(
            $demande->load(['client', 'destinataire', 'colis', 'livraison']),
            201
        );
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Erreur création demande: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la création de la demande de livraison',
            'error'   => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Afficher une demande de livraison spécifique.
     */
    public function show($id): JsonResponse
    {
        $demande = DemandeLivraison::with(relations: ['user', 'colis', 'client'])->find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande de livraison introuvable',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $demande,
        ], 200);
    }

    /**
     * Mettre à jour une demande de livraison.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $demande = DemandeLivraison::find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande de livraison introuvable',
            ], 404);
        }

        $validatedData = $request->validate([
            'client_id' => 'sometimes|string|exists:clients,id',
            'addresse_depot' => 'sometimes|string|max:255',
            'addresse_delivery' => 'sometimes|string|max:255',
            'info_additionnel' => 'nullable|string',
            'date_livraison' => 'sometimes|date',
            'statut' => 'sometimes|string|in:en_attente,en_cours,livree,annulee',
        ]);

        $demande->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Demande de livraison mise à jour avec succès',
            'data' => $demande,
        ], 200);
    }

    /**
     * Générer un code PIN unique à 5 chiffres.
     */
    public function generateUniquePin(): string
    {
        do {
            // Générer un code PIN aléatoire à 5 chiffres
            $pin = str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (Livraison::where('code_pin', $pin)->exists()); // Vérifier l'unicité

        return $pin;
    }



    /**
     * Supprimer une demande de livraison.
     */
    public function destroy($id): JsonResponse
    {
        $demande = DemandeLivraison::find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande de livraison introuvable',
            ], 404);
        }

        if ($demande->colis->photo) {
            Storage::disk('public')->delete($demande->colis->photo);
        }

        $demande->delete();

        return response()->json([
            'success' => true,
            'message' => 'Demande de livraison supprimée avec succès',
        ], 200);
    }

    /**
     * Convertir le code d'erreur d'upload en message lisible
     */
    private function getUploadErrorMessage($errorCode): string
    {
        $errors = [
            UPLOAD_ERR_OK => 'No error',
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
        ];

        return $errors[$errorCode] ?? 'Unknown upload error';
    }
}
