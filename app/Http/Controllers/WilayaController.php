<?php

namespace App\Http\Controllers;

use App\Data\AlgeriaWilayasData;
use Illuminate\Http\JsonResponse;

class WilayaController extends Controller
{
    /**
     * Retourner toutes les wilayas
     */
    public function index(): JsonResponse
    {
        $wilayas = AlgeriaWilayasData::getWilayas();
        
        $formattedWilayas = [];
        foreach ($wilayas as $code => $wilaya) {
            $formattedWilayas[] = [
                'code' => $code,
                'name' => $wilaya['name'],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $formattedWilayas,
        ], 200);
    }

    /**
     * Retourner les communes d'une wilaya spécifique
     */
    public function communes($wilayaCode): JsonResponse
    {
        $communes = AlgeriaWilayasData::getCommunesByWilaya($wilayaCode);

        if (empty($communes)) {
            return response()->json([
                'success' => false,
                'message' => 'Wilaya non trouvée',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $communes,
        ], 200);
    }
}
