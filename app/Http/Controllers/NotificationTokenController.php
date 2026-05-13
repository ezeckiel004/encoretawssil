<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\NotificationToken;


class NotificationTokenController extends Controller{


public function store(Request $request,  $id)
{
    $request->validate(['token' => 'required|string']);

    NotificationToken::updateOrCreate(
        ['user_id' => $id],
        ['token' => $request->token]
    );

    return response()->json(['message' => 'Token enregistré']);
}
}
