<?php
// app/Http/Controllers/Manager/HubController.php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Hub;
use Illuminate\Http\JsonResponse;

class HubController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('gestionnaire');
    }

    /**
     * Lister tous les hubs (accessible aux gestionnaires)
     */
    public function index(): JsonResponse
    {
        try {
            $hubs = Hub::all();

            return response()->json([
                'success' => true,
                'data' => $hubs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des hubs'
            ], 500);
        }
    }
}
