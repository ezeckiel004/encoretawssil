<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DemandeLivraisonController;
use App\Http\Controllers\LivraisonController;
use App\Http\Controllers\LivreurController;
use App\Http\Controllers\LivreurStatsController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DemandeAdhesionController;
use App\Http\Controllers\AvisController;
use App\Http\Controllers\ResponseAvisController;
use App\Http\Controllers\CommentaireController;
use App\Http\Controllers\BordereauController;
use App\Http\Controllers\LivreurCourseController;
use App\Http\Controllers\NotificationTokenController;
use App\Http\Controllers\NotificationHistoriqueController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WilayaController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\LivraisonController as AdminLivraisonController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\NavetteController;
use App\Http\Controllers\Admin\ComptabiliteController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CommissionController;
use App\Http\Controllers\Admin\ColisController;
use App\Http\Controllers\Admin\GestionnaireController;
use App\Http\Controllers\Admin\TraitementCommissionController;
use App\Http\Controllers\Manager\GainController;
use App\Http\Controllers\Manager\NavetteController as ManagerNavetteController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ==================== ROUTES PUBLIQUES ====================
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthorized.'], 401);
})->name('login');

// Wilayas et communes (publiques)
Route::get('/wilayas', [WilayaController::class, 'index']);
Route::get('/wilayas/{wilayaCode}/communes', [WilayaController::class, 'communes']);

// Tracking public
Route::get('/livraisons/track/{colis_label}', [LivraisonController::class, 'trackByColisLabel']);

// ==================== AUTHENTIFICATION ====================
Route::prefix('auth')->group(function () {
    // Routes publiques
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-reset-token', [AuthController::class, 'verifyResetToken']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Routes protégées
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/verify-token', [AuthController::class, 'verifyToken']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/profile/photo', [AuthController::class, 'updatePhotoProfile']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::get('/auth/check-suspended', [AuthController::class, 'checkSuspended']);

        // ======== NOUVELLES ROUTES POUR LA GESTION MULTI-APPAREILS ========
        Route::get('/devices', [AuthController::class, 'listDevices']);
        Route::delete('/devices/{tokenId}', [AuthController::class, 'revokeDevice']);
    });
});

// ==================== ROUTES PROTÉGÉES (AUTH:SANCTUM) ====================
Route::middleware('auth:sanctum')->group(function () {

    // ======== UTILISATEURS ========
    Route::apiResource('users', UserController::class);
    Route::get('/all-users', [UserController::class, 'getAllUsers']);
    Route::get('users/search', [UserController::class, 'search']);
    Route::get('users/stats', [UserController::class, 'stats']);
    Route::get('users/{id}/stats/client', [UserController::class, 'getClientStats']);
    Route::get('users/{id}/stats/livreur', [UserController::class, 'getLivreurStats']);
    Route::patch('users/{id}/toggle-activation', [UserController::class, 'toggleActivation']);
    Route::get('/users/show/{id}', [UserController::class, 'show']);
    Route::delete('/delete/{id}', [UserController::class, 'destroy']);

    // ======== POSITION GPS ========
    Route::patch('/update-position', [UserController::class, 'updatePosition']);
    Route::patch('user/position', [AuthController::class, 'updatePosition']);
    Route::patch('/update/photo', [AuthController::class, 'updatePhoto']);

    // ======== NOTIFICATIONS ========
    Route::post('/users/{userId}/fcm-token', [NotificationTokenController::class, 'store']);
    Route::get('/users/{user}/notifications', [NotificationHistoriqueController::class, 'index']);
    Route::post('/users/{user}/notifications/{notification}/read', [NotificationHistoriqueController::class, 'markAsRead']);

    // ======== DEMANDES DE LIVRAISON ========
    Route::get('/demandes-livraison/payment-history', [DemandeLivraisonController::class, 'paymentHistory']);
    Route::get('/demandes-livraison/previous-destinataires', [DemandeLivraisonController::class, 'getPreviousDestinataires']);
    Route::apiResource('demandes-livraison', DemandeLivraisonController::class);

    // ======== LIVRAISONS ========
    Route::apiResource('livraisons', LivraisonController::class);
    Route::get('livraisons/en-cours', [LivraisonController::class, 'livraisonsEnCours']);
    Route::get('livraisons/toutes-en-cours', [LivraisonController::class, 'toutesLivraisonsEnCours']);
    Route::patch('livraisons/{id}/status', [LivraisonController::class, 'updateStatus']);
    Route::patch('livraisons/{id}/assign-livreur', [LivraisonController::class, 'assignLivreur']);
    Route::patch('livraisons/{id}/destroy_by_client', [LivraisonController::class, 'destroyByClient']);
    Route::get('livraisons/{id}/statistiques', [LivraisonController::class, 'statistiquesClient']);
    Route::get('livraisons/{id}/statistiques/livreur', [LivraisonController::class, 'statistiquesLivreur']);
    Route::get('livraisons/getByClient/{id}', [LivraisonController::class, 'getByClient']);
    Route::get('livraisons/getByLivreur/{id}', [LivraisonController::class, 'getByLivreur']);
    Route::get('livraisons/client/{id}/en-cours', [LivraisonController::class, 'livraisonsClientEnCours']);
    Route::get('livraisons/livreur/{id}/en-cours', [LivraisonController::class, 'livraisonsLivreurEnCours']);

    // ======== ROUTES DYNAMIQUES DE STATUTS (Livreur) ========
    Route::patch('livraisons/{id}/status-by-livreur', [LivreurCourseController::class, 'updateStatusByLivreurType']);
    Route::get('livraisons/{id}/valid-transitions', [LivreurCourseController::class, 'getValidTransitions']);
    Route::get('livraisons/by-status-and-type/{status}', [LivreurCourseController::class, 'getByStatusAndLivreurType']);
    Route::get('livraisons/statistics-by-status', [LivreurCourseController::class, 'getStatistiquesByStatus']);

    // ======== BORDEREAUX ========
    Route::apiResource('bordereaux', BordereauController::class);
    Route::get('livraisons/{id}/bordereau-pdf', [LivraisonController::class, 'generateBordereauPDF']);
    Route::get('livraisons/{id}/print-html', [LivraisonController::class, 'generatePrintHTML']);

    // ======== LIVREURS ========
    Route::apiResource('livreurs', LivreurController::class);
    Route::patch('livreurs/{id}/toggle-activation', [LivreurController::class, 'toggleActivation']);

    // ======== CLIENTS ========
    Route::apiResource('clients', ClientController::class);

    // ======== DEMANDES D'ADHÉSION ========
    Route::apiResource('demandes-adhesion', DemandeAdhesionController::class);
    Route::patch('demandes-adhesion/{id}/status', [DemandeAdhesionController::class, 'updateStatus']);
    Route::get('demandes-adhesion/by-status/{status}', [DemandeAdhesionController::class, 'getByStatus']);

    // ======== AVIS ET COMMENTAIRES ========
    Route::apiResource('avis', AvisController::class);
    Route::apiResource('reponses-avis', ResponseAvisController::class);
    Route::apiResource('commentaires', CommentaireController::class);

    // ======== SUPPRESSION DE COMPTE ========
    Route::post('/auth/delete-account', [AuthController::class, 'deleteAccount']);

    // ==================== ROUTES ADMIN ====================
    Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {

        // ======== DASHBOARD ========
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/charts', [DashboardController::class, 'charts']);

        Route::prefix('livreur-assignations')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\LivreurAssignationController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Admin\LivreurAssignationController::class, 'store']);
            Route::get('/disponibles', [App\Http\Controllers\Admin\LivreurAssignationController::class, 'getLivreursDisponibles']);
            Route::get('/{id}', [App\Http\Controllers\Admin\LivreurAssignationController::class, 'show']);
            Route::put('/{id}', [App\Http\Controllers\Admin\LivreurAssignationController::class, 'update']);
            Route::delete('/{id}', [App\Http\Controllers\Admin\LivreurAssignationController::class, 'destroy']);
            Route::post('/{id}/terminer', [App\Http\Controllers\Admin\LivreurAssignationController::class, 'terminer']);
        });

        Route::prefix('hubs')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\HubController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Admin\HubController::class, 'store']);
            Route::get('/{id}', [App\Http\Controllers\Admin\HubController::class, 'show']);
            Route::put('/{id}', [App\Http\Controllers\Admin\HubController::class, 'update']);
            Route::delete('/{id}', [App\Http\Controllers\Admin\HubController::class, 'destroy']);
        });

        Route::prefix('historique')->group(function () {
            Route::get('/gains', [App\Http\Controllers\Admin\ComptabiliteController::class, 'historiqueGains']);
            Route::delete('/gains/{gainId}', [App\Http\Controllers\Admin\ComptabiliteController::class, 'supprimerGain']);
        });

        // ======== LIVRAISONS ========
        Route::post('livraisons', [AdminLivraisonController::class, 'store']);
        Route::get('livraisons', [AdminLivraisonController::class, 'index']);
        Route::get('livraisons/{id}', [AdminLivraisonController::class, 'show']);
        Route::put('livraisons/{id}', [AdminLivraisonController::class, 'update']);
        Route::delete('livraisons/{id}', [AdminLivraisonController::class, 'destroy']);
        Route::get('livraisons/statistics', [AdminLivraisonController::class, 'statistics']);
        Route::get('livraisons/search', [AdminLivraisonController::class, 'search']);
        Route::patch('livraisons/{id}/assign-livreur', [AdminLivraisonController::class, 'assignLivreur']);
        Route::patch('livraisons/{id}/status', [AdminLivraisonController::class, 'updateStatus']);

        // ⭐ ROUTE POUR MODIFIER LE STATUT DE RETOUR (UNIQUEMENT POUR LIVRAISONS ANNULÉES)
        Route::patch('livraisons/{id}/return-status', [AdminLivraisonController::class, 'updateReturnStatus']);

        Route::get('livraisons/stats/general', [AdminLivraisonController::class, 'statistiquesGenerales']);
        Route::get('livraisons/en-attente', [AdminLivraisonController::class, 'livraisonsEnAttente']);
        Route::get('livraisons/terminees', [AdminLivraisonController::class, 'livraisonsTerminees']);
        Route::get('livraisons/annulees', [AdminLivraisonController::class, 'livraisonsAnnulees']);
        Route::get('livraisons/en-cours', [AdminLivraisonController::class, 'livraisonsEnCours']);
        Route::get('livraisons/getByClient/{id}', [AdminLivraisonController::class, 'getByClient']);
        Route::get('livraisons/getByLivreur/{id}', [AdminLivraisonController::class, 'getByLivreur']);
        Route::patch('livraisons/{id}/payment-status', [AdminLivraisonController::class, 'updatePaymentStatus']);

        // AJOUTER CES ROUTES
        Route::get('livraisons/{id}/edit', [AdminLivraisonController::class, 'edit']);
        Route::put('livraisons/{id}', [AdminLivraisonController::class, 'update']);
        Route::get('livraisons/{id}/history', [AdminLivraisonController::class, 'getHistory']);

        // AJOUTER CES ROUTES POUR LA CRÉATION
        Route::post('livraisons', [AdminLivraisonController::class, 'store']);
        Route::get('livraisons/generate-pin', [AdminLivraisonController::class, 'generatePin']);

        // Routes pour les selects
        Route::get('clients/select', [AdminLivraisonController::class, 'getClientsForSelect']);
        Route::get('livreurs/select', [AdminLivraisonController::class, 'getLivreursForSelect']);

        // ⭐⭐⭐ NOUVELLES ROUTES POUR L'IMPRESSION MULTIPLE ⭐⭐⭐
        Route::post('livraisons/bordereaux-multiple', [AdminLivraisonController::class, 'generateMultipleBordereauxPDF']);
        Route::post('livraisons/print-multiple-html', [AdminLivraisonController::class, 'generateMultiplePrintHTML']);

        // ======== COMMISSIONS ========
        Route::prefix('commissions')->group(function () {
            Route::get('/config', [CommissionController::class, 'getConfig']);
            Route::put('/config', [CommissionController::class, 'updateConfig']);
            Route::post('/simuler', [CommissionController::class, 'simulerConfig']);
            Route::get('/historique', [CommissionController::class, 'historiqueConfig']);
            Route::get('/statistiques', [CommissionController::class, 'statistiquesGlobales']);
            Route::get('/gestionnaires/{gestionnaireId}/gains', [CommissionController::class, 'getGainsGestionnaire']);
            Route::get('/export', [CommissionController::class, 'exportStatistiques']);
        });

        // ======== GESTIONNAIRES ========
        Route::prefix('gestionnaires')->group(function () {
            Route::get('/', [GestionnaireController::class, 'index']);
            Route::get('/{id}', [GestionnaireController::class, 'show']);
            Route::post('/', [GestionnaireController::class, 'store']);
            Route::put('/{id}', [GestionnaireController::class, 'update']);
            Route::delete('/{id}', [GestionnaireController::class, 'destroy']);
            Route::patch('/{id}/toggle-activation', [GestionnaireController::class, 'toggleActivation']);
        });

        // ======== TRAITEMENT DES COMMISSIONS ========
        Route::prefix('traitement-commissions')->group(function () {
            Route::get('/', [TraitementCommissionController::class, 'index']);
            Route::post('/payer/{gainId}', [TraitementCommissionController::class, 'marquerPaye']);
            Route::post('/payer-multiple', [TraitementCommissionController::class, 'marquerPayeMultiple']);
            Route::post('/annuler/{gainId}', [TraitementCommissionController::class, 'marquerAnnule']);
            Route::get('/statistiques', [TraitementCommissionController::class, 'statistiques']);
        });

        // ======== BORDEREAUX PDF ========
        Route::get('livraisons/{id}/bordereau-pdf', [AdminLivraisonController::class, 'generateBordereauPDF']);
        Route::get('livraisons/{id}/print-html', [AdminLivraisonController::class, 'generatePrintHTML']);
        Route::get('livraisons/{id}/print-etiquette', [LivraisonController::class, 'generatePrintBordereau']);

        // ======== EXPORTS ========
        Route::get('users/export/excel', [AdminUserController::class, 'exportExcel']);
        Route::post('users/export', [AdminUserController::class, 'exportUsers']);
        Route::get('users/export/download/{token}', [AdminUserController::class, 'downloadExport']);
        Route::get('livraisons/export/excel', [AdminLivraisonController::class, 'exportExcel']);
        Route::post('livraisons/export', [AdminLivraisonController::class, 'exportLivraisons']);
        Route::get('livraisons/export/download/{token}', [AdminLivraisonController::class, 'downloadExport']);

        // ======== UTILISATEURS (ADMIN) ========
        Route::get('/users', [AdminUserController::class, 'getAllUsers']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::get('/users/{id}', [AdminUserController::class, 'show']);
        Route::put('/users/{id}', [AdminUserController::class, 'update']);
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
        Route::delete('/users/{id}/force-delete', [AdminUserController::class, 'deleteUser']);
        Route::patch('/users/{id}/toggle-activation', [AdminUserController::class, 'toggleActivation']);
        Route::get('/users/stats', [AdminUserController::class, 'stats']);
        Route::get('/users/search', [AdminUserController::class, 'search']);
        Route::get('/users/{id}/stats/client', [AdminUserController::class, 'getClientStats']);
        Route::get('/users/{id}/stats/livreur', [AdminUserController::class, 'getLivreurStats']);

        // ======== ROUTES DE SUSPENSION DES UTILISATEURS ========
        Route::post('/users/{id}/suspendre', [AdminUserController::class, 'suspendre']);
        Route::get('/users/suspended', [AdminUserController::class, 'getSuspendedUsers']);
        Route::get('/users/{id}/suspended-check', [AdminUserController::class, 'checkSuspended']);

        // ======== NAVETTES (ADMIN) ========
        Route::prefix('navettes')->group(function () {
            Route::get('/', [NavetteController::class, 'index']);
            Route::post('/', [NavetteController::class, 'store']);
            Route::get('/suggestions', [NavetteController::class, 'suggestions']);
            Route::post('/creer-optimisee', [NavetteController::class, 'creerOptimisee']);
            Route::get('/statistiques', [NavetteController::class, 'statistiques']);
            Route::get('/export-pdf', [NavetteController::class, 'exportPDF']);
            Route::get('/{id}', [NavetteController::class, 'show']);
            Route::put('/{id}', [NavetteController::class, 'update']);
            Route::delete('/{id}', [NavetteController::class, 'destroy']);
            Route::post('/{id}/demarrer', [NavetteController::class, 'demarrer']);
            Route::post('/{id}/terminer', [NavetteController::class, 'terminer']);
            Route::post('/{id}/annuler', [NavetteController::class, 'annuler']);
            Route::post('/{id}/livraisons', [NavetteController::class, 'ajouterLivraisons']);
            Route::delete('/{id}/livraisons', [NavetteController::class, 'retirerLivraisons']);
            Route::get('/{id}/livraisons', [NavetteController::class, 'getLivraisons']);
        });

        // ======== COLIS (ADMIN) ========
        Route::prefix('colis')->group(function () {
            Route::get('/', [ColisController::class, 'index']);
            Route::get('/disponibles', [ColisController::class, 'disponibles']);
            Route::get('/{id}', [ColisController::class, 'show']);
            Route::post('/', [ColisController::class, 'store']);
            Route::put('/{id}', [ColisController::class, 'update']);
            Route::delete('/{id}', [ColisController::class, 'destroy']);
        });

        // ======== COMPTABILITÉ - BILANS ========
        Route::prefix('comptabilite')->group(function () {
            Route::get('/bilan-global', [ComptabiliteController::class, 'bilanGlobal']);
            Route::get('/bilan-global/export', [ComptabiliteController::class, 'exportBilanGlobal']);
            Route::get('/bilan-gestionnaire', [ComptabiliteController::class, 'bilanGestionnaire']);
            Route::get('/bilan-gestionnaire/export', [ComptabiliteController::class, 'exportBilanGestionnaire']);
            Route::get('/bilan-wilaya/{wilayaId}', [ComptabiliteController::class, 'bilanGestionnaire']);
            Route::get('/bilan-wilaya/{wilayaId}/export', [ComptabiliteController::class, 'exportBilanGestionnaire']);
            Route::get('/navettes-terminees', [ComptabiliteController::class, 'getNavettesTerminees']);
            Route::get('/navette/{navetteId}/gains', [ComptabiliteController::class, 'getGainsNavette']);
            Route::get('/rapport', [ComptabiliteController::class, 'rapport']);
            Route::get('/rapport/export', [ComptabiliteController::class, 'exportRapport']);
            Route::get('/rapport-gestionnaires', [ComptabiliteController::class, 'rapportGestionnaires']);
            Route::get('/rapport-gestionnaires/export', [ComptabiliteController::class, 'exportRapportGestionnaires']);
            Route::get('/gains', [ComptabiliteController::class, 'getGainsDetails']);
            Route::get('/gestionnaire/{gestionnaireId}/gains', [ComptabiliteController::class, 'getGainsGestionnaire']);
            Route::get('/statistiques-mensuelles', [ComptabiliteController::class, 'statistiquesMensuelles']);
            Route::get('/evolution-mensuelle', [ComptabiliteController::class, 'evolutionMensuelle']);
            Route::get('/impayes', [ComptabiliteController::class, 'impayes']);
        });
    });

    // ==================== ROUTES LIVREUR (MOBILE) ====================
    Route::prefix('livreur')->group(function () {
        Route::get('courses', [LivreurCourseController::class, 'index']);
        Route::get('courses/count', [LivreurCourseController::class, 'count']);
        Route::get('courses/status/{status}', [LivreurCourseController::class, 'byStatus']);
        Route::get('courses/colis', [LivreurCourseController::class, 'colis']);
        Route::get('courses/{id}', [LivreurCourseController::class, 'show']);
        Route::post('courses/{id}/complete', [LivreurCourseController::class, 'complete']);
        Route::get('stats/dashboard', [LivreurStatsController::class, 'dashboard']);
        Route::get('stats/detailed', [LivreurStatsController::class, 'detailedStats']);
    });

    // ==================== ROUTES GESTIONNAIRE (MANAGER) ====================
    // Utiliser le middleware 'gestionnaire' qui existe déjà
    Route::prefix('manager')->middleware(['auth:sanctum', 'role:gestionnaire'])->group(function () {

        // Dashboard
        Route::get('dashboard', [App\Http\Controllers\Manager\DashboardController::class, 'index']);

        // Profil
        Route::prefix('profile')->group(function () {
            Route::get('/', [App\Http\Controllers\Manager\ProfileController::class, 'show']);
            Route::put('/', [App\Http\Controllers\Manager\ProfileController::class, 'update']);
            Route::post('change-password', [App\Http\Controllers\Manager\ProfileController::class, 'changePassword']);
        });

        // Gains du gestionnaire
        Route::prefix('gains')->group(function () {
            Route::get('/', [GainController::class, 'index']);
            Route::get('/en-attente', [GainController::class, 'gainsEnAttente']);
            Route::post('/demander/{gainId}', [GainController::class, 'demanderPaiement']);
            Route::post('/demander-multiple', [GainController::class, 'demanderPaiementMultiple']);
        });

        // ⭐ LIVRAISONS - AVEC LES NOUVELLES ROUTES D'ASSIGNATION ⭐
        Route::prefix('livraisons')->group(function () {
            Route::get('/', [App\Http\Controllers\Manager\LivraisonController::class, 'index']);
            Route::get('search', [App\Http\Controllers\Manager\LivraisonController::class, 'search']);
            Route::get('status/{status}', [App\Http\Controllers\Manager\LivraisonController::class, 'byStatus']);
            Route::get('{id}', [App\Http\Controllers\Manager\LivraisonController::class, 'show']);
            Route::patch('{id}/status', [App\Http\Controllers\Manager\LivraisonController::class, 'updateStatus']);

            // ⭐ NOUVELLES ROUTES POUR L'ASSIGNATION DES LIVREURS PAR MANAGER
            Route::patch('{id}/assign-livreur', [App\Http\Controllers\Manager\LivraisonController::class, 'assignLivreur']);
            Route::get('disponibles/livreurs', [App\Http\Controllers\Manager\LivraisonController::class, 'getLivreursDisponibles']);
            Route::patch('{id}/payment-status', [App\Http\Controllers\Manager\LivraisonController::class, 'updatePaymentStatus']);

            Route::get('{id}/edit', [App\Http\Controllers\Manager\LivraisonController::class, 'edit']);
            Route::put('{id}', [App\Http\Controllers\Manager\LivraisonController::class, 'update']);
            Route::post('/', [App\Http\Controllers\Manager\LivraisonController::class, 'store']);
            Route::get('clients/liste', [App\Http\Controllers\Manager\LivraisonController::class, 'getClients']);
        });

        // Livreurs
        Route::prefix('livreurs')->group(function () {
            Route::get('/', [App\Http\Controllers\Manager\LivreurController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Manager\LivreurController::class, 'store']);
            Route::get('/search', [App\Http\Controllers\Manager\LivreurController::class, 'search']);
            Route::get('/natifs', [App\Http\Controllers\Manager\LivreurController::class, 'getNatifs']);
            Route::get('/assignes', [App\Http\Controllers\Manager\LivreurController::class, 'getAssignes']);
            Route::get('/type/{type}', [App\Http\Controllers\Manager\LivreurController::class, 'byType']);

            // Statistiques de suspension
            Route::get('/suspension-stats', [App\Http\Controllers\Manager\LivreurController::class, 'getSuspensionStats']);

            // Detail, modification et activation/suspension
            Route::get('/{id}', [App\Http\Controllers\Manager\LivreurController::class, 'show']);
            Route::put('/{id}', [App\Http\Controllers\Manager\LivreurController::class, 'update']);
            Route::put('/{id}/toggle-activation', [App\Http\Controllers\Manager\LivreurController::class, 'toggleActivation']);
            // CHANGER LE MOT DE PASSE - NOUVELLE ROUTE
            Route::put('/{id}/change-password', [App\Http\Controllers\Manager\LivreurController::class, 'changePassword']);
        });

        // Codes promo
        Route::apiResource('codes-promo', App\Http\Controllers\Manager\CodePromoController::class);
        Route::prefix('codes-promo/{id}')->group(function () {
            Route::post('add-livreurs', [App\Http\Controllers\Manager\CodePromoController::class, 'addLivreurs']);
            Route::delete('remove-livreurs', [App\Http\Controllers\Manager\CodePromoController::class, 'removeLivreurs']);
        });

        // Comptabilité
        Route::prefix('comptabilite')->group(function () {
            Route::get('/', [App\Http\Controllers\Manager\ComptabiliteController::class, 'index']);
            Route::get('/export', [App\Http\Controllers\Manager\ComptabiliteController::class, 'export']);
            Route::get('/statistiques-mensuelles', [App\Http\Controllers\Manager\ComptabiliteController::class, 'statistiquesMensuelles']);
        });

        Route::get('hubs', [App\Http\Controllers\Manager\HubController::class, 'index']);

        // ======== NAVETTES (GESTIONNAIRE) ========
        Route::prefix('navettes')->group(function () {
            Route::get('/', [ManagerNavetteController::class, 'index']);
            Route::post('/', [ManagerNavetteController::class, 'store']);
            Route::get('/disponibles', [ManagerNavetteController::class, 'getLivraisonsDisponibles']);
            Route::get('/{id}', [ManagerNavetteController::class, 'show']);
            Route::put('/{id}', [ManagerNavetteController::class, 'update']);
            Route::delete('/{id}', [ManagerNavetteController::class, 'destroy']);
            Route::post('/{id}/demarrer', [ManagerNavetteController::class, 'demarrer']);
            Route::post('/{id}/terminer', [ManagerNavetteController::class, 'terminer']);
            Route::post('/{id}/annuler', [ManagerNavetteController::class, 'annuler']);
            Route::get('/{id}/livraisons-disponibles', [ManagerNavetteController::class, 'getLivraisonsDisponiblesForNavette']);
        });

        // ==================== GAINS NAVETTES (GESTIONNAIRE) ====================
        Route::prefix('gains-navette')->group(function () {
            Route::get('/', [App\Http\Controllers\Manager\GainsNavetteController::class, 'index']);
            Route::get('/en-attente', [App\Http\Controllers\Manager\GainsNavetteController::class, 'gainsEnAttente']);
            Route::post('/demander/{gainId}', [App\Http\Controllers\Manager\GainsNavetteController::class, 'demanderPaiement']);
            Route::post('/demander-multiple', [App\Http\Controllers\Manager\GainsNavetteController::class, 'demanderPaiementMultiple']);
            Route::get('/statistiques', [App\Http\Controllers\Manager\GainsNavetteController::class, 'statistiques']);
        });

        // ==================== CASH ON DELIVERY (COD) ====================
        Route::prefix('cash-delivery')->group(function () {
            // Gestionnaires disponibles
            Route::get('/gestionnaires', [App\Http\Controllers\Manager\CashDeliveryController::class, 'getGestionnairesDisponibles']);

            // Envoyer une demande
            Route::post('/envoyer', [App\Http\Controllers\Manager\CashDeliveryController::class, 'envoyer']);

            // Actions sur les demandes
            Route::post('/{id}/accepter', [App\Http\Controllers\Manager\CashDeliveryController::class, 'accepter']);
            Route::post('/{id}/refuser', [App\Http\Controllers\Manager\CashDeliveryController::class, 'refuser']);
            Route::post('/{id}/annuler', [App\Http\Controllers\Manager\CashDeliveryController::class, 'annuler']);

            // Listes
            Route::get('/envoyees', [App\Http\Controllers\Manager\CashDeliveryController::class, 'demandesEnvoyees']);
            Route::get('/recues', [App\Http\Controllers\Manager\CashDeliveryController::class, 'demandesRecues']);

            // Statistiques
            Route::get('/statistiques', [App\Http\Controllers\Manager\CashDeliveryController::class, 'statistiques']);
        });
    });
});

// ==================== ROUTES PUBLIQUES SUPPLÉMENTAIRES ====================
Route::get('livraisons/{id}/bordereau-pdf', [LivraisonController::class, 'generateBordereauPDF']);
Route::get('livraisons/{id}/print-html', [LivraisonController::class, 'generatePrintHTML']);
