<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

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
            return redirect(rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/') . '/login?error=invalid_provider');
        }

        try {
            Log::info("Initiating social login redirect for provider: " . $provider);
            
            return Socialite::driver($provider)->redirect();
            
        } catch (\Exception $e) {
            Log::error('Error during social login redirect: ' . $e->getMessage(), [
                'provider' => $provider,
                'trace' => $e->getTraceAsString()
            ]);
            return redirect(rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/') . '/login?error=redirect_failed');
        }
    }

    /**
     * Obtain the user information from the provider.
     *
     * @param string $provider
     * @return RedirectResponse
     */
    public function callback(string $provider): RedirectResponse
    {
        Log::info("Socialite callback started for provider: " . $provider);
        
        if (!in_array($provider, $this->validProviders)) {
            Log::error("Invalid provider: " . $provider);
            return redirect(rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/') . '/login?error=invalid_provider');
        }

        try {
            Log::info("Attempting to get user from Socialite for provider: " . $provider);
            $socialiteUser = Socialite::driver($provider)->user();
            Log::info("Socialite user obtained successfully", [
                'id' => $socialiteUser->getId(),
                'email' => $socialiteUser->getEmail(),
                'name' => $socialiteUser->getName()
            ]);
        } catch (\Exception $e) {
            Log::error('Socialite callback error: ' . $e->getMessage(), [
                'provider' => $provider,
                'trace' => $e->getTraceAsString()
            ]);
            return redirect(rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/') . '/login?error=social_auth_failed');
        }

        // Validate that we have the required user data
        if (!$socialiteUser->getId() || !$socialiteUser->getEmail()) {
            Log::error('Incomplete user data from social provider', [
                'provider' => $provider,
                'id' => $socialiteUser->getId(),
                'email' => $socialiteUser->getEmail()
            ]);
            return redirect(rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/') . '/login?error=incomplete_user_data');
        }

        try {
            $user = $this->findOrCreateUser($socialiteUser, $provider);
        } catch (\Exception $e) {
            Log::error('Error creating/updating user: ' . $e->getMessage(), [
                'provider' => $provider,
                'provider_id' => $socialiteUser->getId(),
                'email' => $socialiteUser->getEmail(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Verificar si es error de cuenta desactivada
            if (str_contains($e->getMessage(), 'desactivada')) {
                return redirect(rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/') . '/login?error=account_deactivated');
            }
            
            return redirect(rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/') . '/login?error=user_creation_failed');
        }

        try {
            Log::info("Generating JWT token for user", ['user_id' => $user->id_usuario]);
            $token = JWTAuth::fromUser($user);
            Log::info("JWT token generated successfully");
        } catch (\Exception $e) {
            Log::error('Error generating JWT token: ' . $e->getMessage(), [
                'user_id' => $user->id_usuario,
                'trace' => $e->getTraceAsString()
            ]);
            return redirect(rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/') . '/login?error=token_generation_failed');
        }

        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
        $redirectUrl = $frontendUrl . '/auth/callback?token=' . $token;
        Log::info("Redirecting to frontend", ['url' => $redirectUrl]);
        
        return redirect($redirectUrl);
    }

    /**
     * Handle social authentication for API requests (SPA)
     * This method allows frontend applications to handle the OAuth flow
     * and receive JSON responses instead of redirects
     *
     * @param Request $request
     * @param string $provider
     * @return JsonResponse
     */
    public function handleSocialAuth(Request $request, string $provider): JsonResponse
    {
        Log::info("API Social auth started for provider: " . $provider);
        
        if (!in_array($provider, $this->validProviders)) {
            Log::error("Invalid provider: " . $provider);
            return response()->json([
                'success' => false,
                'message' => 'Proveedor no válido'
            ], 400);
        }

        // Validar que se proporcione el código de autorización
        $request->validate([
            'code' => 'required|string',
            'state' => 'nullable|string'
        ]);

        try {
            Log::info("Attempting to get user from Socialite for provider: " . $provider);
            
            // Obtener el usuario del proveedor usando el código
            $socialiteUser = Socialite::driver($provider)->user();
            
            Log::info("Socialite user obtained successfully", [
                'id' => $socialiteUser->getId(),
                'email' => $socialiteUser->getEmail(),
                'name' => $socialiteUser->getName()
            ]);
        } catch (\Exception $e) {
            Log::error('Socialite API callback error: ' . $e->getMessage(), [
                'provider' => $provider,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error en la autenticación social'
            ], 500);
        }

        // Validar que tengamos los datos necesarios
        if (!$socialiteUser->getId() || !$socialiteUser->getEmail()) {
            Log::error('Incomplete user data from social provider', [
                'provider' => $provider,
                'id' => $socialiteUser->getId(),
                'email' => $socialiteUser->getEmail()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Datos de usuario incompletos del proveedor social'
            ], 400);
        }

        try {
            $user = $this->findOrCreateUser($socialiteUser, $provider);
        } catch (\Exception $e) {
            Log::error('Error creating/updating user: ' . $e->getMessage(), [
                'provider' => $provider,
                'provider_id' => $socialiteUser->getId(),
                'email' => $socialiteUser->getEmail(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear o actualizar el usuario'
            ], 500);
        }

        try {
            Log::info("Generating JWT token for user", ['user_id' => $user->id_usuario]);
            $token = JWTAuth::fromUser($user);
            $user->load('role'); // Cargar la relación con el rol
            
            Log::info("JWT token generated successfully");
            
            return response()->json([
                'success' => true,
                'message' => 'Autenticación exitosa',
                'data' => [
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60, // Convertir minutos a segundos
                    'user' => [
                        'id' => $user->id_usuario,
                        'nombre' => $user->nombre,
                        'apellidos' => $user->apellidos,
                        'email' => $user->email,
                        'telefono' => $user->telefono,
                        'avatar' => $user->avatar,
                        'provider' => $user->provider,
                        'role' => $user->role ? [
                            'id' => $user->role->id_rol,
                            'nombre' => $user->role->nombre
                        ] : null,
                        'estado' => $user->estado,
                        'email_verified_at' => $user->email_verified_at
                    ]
                ]
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error generating JWT token: ' . $e->getMessage(), [
                'user_id' => $user->id_usuario,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el token de autenticación'
            ], 500);
        }
    }

    /**
     * Find or create user from social provider data
     *
     * @param mixed $socialiteUser
     * @param string $provider
     * @return User
     */
    private function findOrCreateUser($socialiteUser, string $provider): User
    {
        Log::info("Creating or updating user in database");
        
        // Buscar si ya existe un usuario con este email
        $existingUser = User::where('email', $socialiteUser->getEmail())->first();
        
        if ($existingUser) {
            // Verificar si la cuenta está desactivada
            if (!$existingUser->estado || $existingUser->estado === false) {
                Log::warning("Attempted social login with deactivated account", [
                    'user_id' => $existingUser->id_usuario,
                    'email' => $existingUser->email,
                    'provider' => $provider
                ]);
                throw new \Exception('Tu cuenta ha sido desactivada. Por favor, contacta al soporte para reactivarla.');
            }
            
            // Si existe un usuario con este email, actualizar sus datos de social login
            $existingUser->update([
                'provider' => $provider,
                'provider_id' => $socialiteUser->getId(),
                'avatar' => $socialiteUser->getAvatar() ?: $existingUser->avatar,
                'email_verified_at' => $existingUser->email_verified_at ?: now(),
            ]);
            
            Log::info("Existing user updated with social login data", ['user_id' => $existingUser->id_usuario]);
            return $existingUser;
        }

        // Si no existe, crear un nuevo usuario
        $fullName = trim($socialiteUser->getName() ?: $socialiteUser->getEmail());
        $nameParts = explode(' ', $fullName);
        
        // Lógica mejorada para separar nombre y apellidos
        [$nombre, $apellidos] = $this->parseFullName($nameParts);
        
        Log::info("Name parsing for social user", [
            'original_name' => $fullName,
            'parsed_nombre' => $nombre,
            'parsed_apellidos' => $apellidos
        ]);
        
        $user = User::create([
            'provider' => $provider,
            'provider_id' => $socialiteUser->getId(),
            'nombre' => $nombre,
            'apellidos' => $apellidos,
            'email' => $socialiteUser->getEmail(),
            'telefono' => null, // Dejar vacío en lugar de '000000000'
            'password' => bcrypt(Str::random(16)), // Contraseña aleatoria para usuarios sociales
            'roles_id_rol' => 2, // Asumiendo que 2 es el rol de usuario normal
            'avatar' => $socialiteUser->getAvatar(),
            'email_verified_at' => now(),
            'estado' => true
        ]);
        
        Log::info("New user created with social login", ['user_id' => $user->id_usuario]);
        return $user;
    }

    /**
     * Parse full name into first name and last name
     *
     * @param array $nameParts
     * @return array
     */
    private function parseFullName(array $nameParts): array
    {
        if (empty($nameParts)) {
            return ['Usuario', 'Social'];
        }

        if (count($nameParts) == 1) {
            return [$nameParts[0] ?: 'Usuario', 'Social'];
        }

        if (count($nameParts) == 2) {
            return [$nameParts[0], $nameParts[1]];
        }

        if (count($nameParts) == 3) {
            return [$nameParts[0], $nameParts[1] . ' ' . $nameParts[2]];
        }

        // Si hay 4 o más palabras, usar las primeras dos como nombre y el resto como apellidos
        return [
            $nameParts[0] . ' ' . $nameParts[1],
            implode(' ', array_slice($nameParts, 2))
        ];
    }
}
