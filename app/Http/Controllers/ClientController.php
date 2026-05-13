<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClientController extends Controller
{
    /**
     * Afficher tous les clients.
     */
    public function index(): JsonResponse
    {
        $clients = Client::with('user')->get();

        $datas = [];
        foreach ($clients as $client) {
            $datas[] = [
                'client' => $client->user,
                'status'=> $client->status,
            ];

        }

        return response()->json([
            'success' => true,
            'total'=> count(value: $clients),
            'data' => $datas,
        ], 200);
    }


    /**
     * Afficher un client spécifique.
     */
    public function show($id): JsonResponse
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client introuvable',
            ], 404);
        }


	/*
        return response()->json([
            'success' => true,
            'data' => User::find($client->user_id),
        ], 200);
        */
        return response()->json($client->load([
                'user'
            ]), 200);
      
    }

   
    /**
     * Supprimer un client.
     */
    public function destroy($id): JsonResponse
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client introuvable',
            ], 404);
        }

        $client->delete();

        return response()->json([
            'success' => true,
            'message' => 'Client supprimé avec succès',
        ], 200);
    }
}
