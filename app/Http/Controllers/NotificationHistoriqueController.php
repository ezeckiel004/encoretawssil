<?php
namespace App\Http\Controllers;

use App\Models\notificationHistorique;
use App\Models\NotificationToken;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;



class NotificationHistoriqueController extends Controller{

    public function store(Request $request)
    {
        // Valider les données reçues
        $validated = $request->validate([
            'user_id' => 'required',
            'title' => 'required|string',
            'body' => 'required|string',
            'type' => 'nullable|string',
        ]);

        // Créer la notification dans la base de données
        $notification = notificationHistorique::create([
            'user_id' => $validated['user_id'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'type' => $validated['type'] ?? null,
            'sent_at' => now(),
        ]);

        // Retourner une réponse JSON
        return response()->json([
            'success' => true,
            'message' => 'Notification créée avec succès',
            'data' => $notification
        ], 201);
    }

    function sendNotificationToUsers(array $userIds, string $title, string $body, string $type = null)
    {
        $tokens = NotificationToken::whereIn('user_id', $userIds)
                    ->pluck('token')
                    ->filter() // supprime les valeurs nulles
                    ->values()
                    ->all();
    
        if (empty($tokens)) {
            \Log::info('Aucun token trouvé pour les utilisateurs : ' . implode(', ', $userIds));
            return false;
        }
    
        $fcmUrl = 'https://fcm.googleapis.com/v1/projects/tawssiligo/messages:send';
    
        // Envoi FCM (multicast)
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokens,

            'Content-Type' => 'application/json',
        ])->post($fcmUrl, 
        
        
        [
            
            "message"=>[
                "token" => $tokens,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                "data" => [
                "click_action" => "TAWSSILGO_NOTIFICATION",
                "type" => $type,
            ]                
        ],

            
        ]);
    
      
    
        return $response->successful();
    }
    public function index(User $user)
    {
        return $user->notifications()->latest()->get();
    }

    public function markAsRead(User $user, $notificationId)
    {
        $notification = $user->notifications()->findOrFail($notificationId);

        $notification->update([
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue'
        ]);
    }

    public function markAllAsRead(User $user)
{
    $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);

    return response()->json([
        'success' => true,
        'message' => 'Toutes les notifications ont été marquées comme lues'
    ]);
}

}
