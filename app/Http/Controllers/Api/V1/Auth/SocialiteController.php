<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\RedirectResponse;

class SocialiteController extends Controller
{
    /**
     * Valid providers for social authentication
     */
    private $validProviders = ['google', 'facebook'];

    /**
     * Redirect the user to the provider's authentication page.
     *
     * @param string $provider
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirect(string $provider)
    {
        if (!in_array($provider, $this->validProviders)) {
            return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/login?error=invalid_provider');
        }

        return Socialite::driver($provider)->stateless()->redirect();
    }

    /**
     * Obtain the user information from the provider.
     *
     * @param string $provider
     * @return RedirectResponse
     */
    public function callback(string $provider): RedirectResponse
    {
        \Log::info("Socialite callback started for provider: " . $provider);
        
        if (!in_array($provider, $this->validProviders)) {
            \Log::error("Invalid provider: " . $provider);
            return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/login?error=invalid_provider');
        }

        try {
            \Log::info("Attempting to get user from Socialite for provider: " . $provider);
            $socialiteUser = Socialite::driver($provider)->stateless()->user();
            \Log::info("Socialite user obtained successfully", [
                'id' => $socialiteUser->getId(),
                'email' => $socialiteUser->getEmail(),
                'name' => $socialiteUser->getName()
            ]);
        } catch (\Exception $e) {
            \Log::error('Socialite callback error: ' . $e->getMessage(), [
                'provider' => $provider,
                'trace' => $e->getTraceAsString()
            ]);
            return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/login?error=social_auth_failed');
        }

        // Validate that we have the required user data
        if (!$socialiteUser->getId() || !$socialiteUser->getEmail()) {
            \Log::error('Incomplete user data from social provider', [
                'provider' => $provider,
                'id' => $socialiteUser->getId(),
                'email' => $socialiteUser->getEmail()
            ]);
            return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/login?error=incomplete_user_data');
        }

        try {
            \Log::info("Creating or updating user in database");
            
            // Primero, buscar si ya existe un usuario con este email
            $existingUser = User::where('email', $socialiteUser->getEmail())->first();
            
            if ($existingUser) {
                // Si existe un usuario con este email, actualizar sus datos de social login
                $existingUser->update([
                    'provider' => $provider,
                    'provider_id' => $socialiteUser->getId(),
                    'avatar' => $socialiteUser->getAvatar() ?: $existingUser->avatar,
                    'email_verified_at' => now(),
                ]);
                
                // Asignar el usuario existente (ya actualizado) a la variable $user
                $user = $existingUser;
                \Log::info("Existing user updated with social login data", ['user_id' => $user->id_usuario]);
            } else {
                // Si no existe, crear un nuevo usuario
                // Separar el nombre completo en nombre y apellidos
                $fullName = $socialiteUser->getName() ?: $socialiteUser->getEmail();
                $nameParts = explode(' ', $fullName, 2);
                $nombre = $nameParts[0] ?: 'Usuario';
                $apellidos = isset($nameParts[1]) ? $nameParts[1] : 'Social';
                
                $user = User::create([
                    'provider' => $provider,
                    'provider_id' => $socialiteUser->getId(),
                    'nombre' => $nombre,
                    'apellidos' => $apellidos,
                    'email' => $socialiteUser->getEmail(),
                    'telefono' => '000000000', // Valor por defecto para usuarios sociales
                    'password' => bcrypt(Str::random(16)), // Contraseña aleatoria para usuarios sociales
                    'roles_id_rol' => 2, // Asumiendo que 2 es el rol de usuario normal
                    'avatar' => $socialiteUser->getAvatar(),
                    'email_verified_at' => now(),
                ]);
                \Log::info("New user created with social login", ['user_id' => $user->id_usuario]);
            }
        } catch (\Exception $e) {
            \Log::error('Error creating/updating user: ' . $e->getMessage(), [
                'provider' => $provider,
                'provider_id' => $socialiteUser->getId(),
                'email' => $socialiteUser->getEmail(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/login?error=user_creation_failed');
        }

        try {
            \Log::info("Generating JWT token for user", ['user_id' => $user->id_usuario]);
            $token = JWTAuth::fromUser($user);
            \Log::info("JWT token generated successfully");
        } catch (\Exception $e) {
            \Log::error('Error generating JWT token: ' . $e->getMessage(), [
                'user_id' => $user->id_usuario,
                'trace' => $e->getTraceAsString()
            ]);
            return redirect(env('FRONTEND_URL', 'http://localhost:5173') . '/login?error=token_generation_failed');
        }

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $redirectUrl = $frontendUrl . '/auth/callback?token=' . $token;
        \Log::info("Redirecting to frontend", ['url' => $redirectUrl]);
        
        return redirect($redirectUrl);
    }
}
