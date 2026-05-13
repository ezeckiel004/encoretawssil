<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_user_can_register()
    {
        $userData = [
            'nom'                   => 'Dupont',
            'prenom'                => 'Jean',
            'email'                 => 'jean.dupont@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'telephone'             => '+33123456789',
            'role'                  => 'client',
            'latitude'              => 48.8566,
            'longitude'             => 2.3522,
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'nom',
                        'prenom',
                        'email',
                        'telephone',
                        'role',
                        'actif',
                    ],
                    'token',
                    'token_type',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email'  => 'jean.dupont@example.com',
            'nom'    => 'Dupont',
            'prenom' => 'Jean',
        ]);
    }

    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'email'    => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginData = [
            'email'    => 'test@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'token',
                    'token_type',
                ],
            ]);
    }

    public function test_user_can_logout()
    {
        $user  = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Déconnexion réussie',
            ]);
    }

    public function test_user_can_get_profile()
    {
        $user  = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'nom',
                    'prenom',
                    'email',
                    'role',
                ],
            ]);
    }

    public function test_user_can_update_profile()
    {
        $user  = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $updateData = [
            'nom'    => 'Nouveau Nom',
            'prenom' => 'Nouveau Prénom',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/auth/profile', $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id'     => $user->id,
            'nom'    => 'Nouveau Nom',
            'prenom' => 'Nouveau Prénom',
        ]);
    }

    public function test_user_can_change_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $passwordData = [
            'current_password'          => 'oldpassword',
            'new_password'              => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/auth/change-password', $passwordData);

        $response->assertStatus(200);
    }

    public function test_invalid_login_credentials()
    {
        $loginData = [
            'email'    => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Identifiants incorrects',
            ]);
    }

    public function test_registration_validation()
    {
        $invalidData = [
            'nom'       => '',
            'email'     => 'invalid-email',
            'password'  => '123', // Too short
            'telephone' => '',
        ];

        $response = $this->postJson('/api/auth/register', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nom', 'email', 'password', 'telephone']);
    }
}