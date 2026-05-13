<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return response()->json([
        'success' => false,
        'message' => 'Contrôlez votre accès à l\'API via les routes API',
        'error' => 'Aucune route définie pour l\'accès web',
    ], 404);
});

Route::get('/login', function () {
    return response()->json(['message' => 'Unauthorized.'], 401);
})->name('simplelogin');

