<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOAuthConfig extends Command
{
    protected $signature = 'oauth:check {provider=google}';
    protected $description = 'Check OAuth configuration for social login providers';

    public function handle()
    {
        $provider = $this->argument('provider');
        
        $this->info("🔍 Checking OAuth configuration for: {$provider}");
        $this->newLine();
        
        // Check environment variables
        $this->checkEnvironmentVariables($provider);
        
        // Check service configuration
        $this->checkServiceConfiguration($provider);
        
        // Check routes
        $this->checkRoutes();
        
        // Check URLs accessibility
        $this->checkUrls();
        
        $this->newLine();
        $this->info("✅ OAuth configuration check completed");
    }
    
    private function checkEnvironmentVariables($provider)
    {
        $this->info("📋 Environment Variables:");
        
        $vars = [
            'APP_URL' => env('APP_URL'),
            'FRONTEND_URL' => env('FRONTEND_URL'),
            'GOOGLE_SUCCESS_REDIRECT' => env('GOOGLE_SUCCESS_REDIRECT'),
            'GOOGLE_ERROR_REDIRECT' => env('GOOGLE_ERROR_REDIRECT'),
            strtoupper($provider) . '_CLIENT_ID' => env(strtoupper($provider) . '_CLIENT_ID'),
            strtoupper($provider) . '_CLIENT_SECRET' => env(strtoupper($provider) . '_CLIENT_SECRET') ? '[HIDDEN]' : null,
            strtoupper($provider) . '_REDIRECT_URI' => env(strtoupper($provider) . '_REDIRECT_URI'),
        ];
        
        foreach ($vars as $key => $value) {
            $status = $value ? '✅' : '❌';
            $this->line("  {$status} {$key}: " . ($value ?: 'NOT SET'));
        }
        
        $this->newLine();
    }
    
    private function checkServiceConfiguration($provider)
    {
        $this->info("⚙️  Service Configuration:");
        
        $config = config("services.{$provider}");
        
        if (!$config) {
            $this->error("  ❌ No configuration found for {$provider}");
            return;
        }
        
        $this->line("  ✅ Client ID: " . ($config['client_id'] ?? 'NOT SET'));
        $this->line("  ✅ Client Secret: " . ($config['client_secret'] ? '[HIDDEN]' : 'NOT SET'));
        $this->line("  ✅ Redirect URI: " . ($config['redirect'] ?? 'NOT SET'));
        
        $this->newLine();
    }
    
    private function checkRoutes()
    {
        $this->info("🛣️  Routes:");
        
        $routes = [
            'socialite.redirect' => 'api/v1/auth/{provider}/redirect',
            'socialite.callback' => 'api/v1/auth/{provider}/callback'
        ];
        
        foreach ($routes as $name => $uri) {
            try {
                $route = route($name, ['provider' => 'google']);
                $this->line("  ✅ {$name}: {$route}");
            } catch (\Exception $e) {
                $this->line("  ❌ {$name}: Route not found");
            }
        }
        
        $this->newLine();
    }
    
    private function checkUrls()
    {
        $this->info("🌐 Important URLs:");
        
        $urls = [
            'Google Redirect URL' => env('APP_URL') . '/api/v1/auth/google/redirect',
            'Google Callback URL' => env('APP_URL') . '/api/v1/auth/google/callback',
            'Frontend Success URL' => env('GOOGLE_SUCCESS_REDIRECT', env('FRONTEND_URL') . '/auth/callback'),
            'Frontend Error URL' => env('GOOGLE_ERROR_REDIRECT', env('FRONTEND_URL') . '/login'),
        ];
        
        foreach ($urls as $name => $url) {
            $this->line("  🔗 {$name}: {$url}");
        }
        
        $this->newLine();
        $this->warn("⚠️  Make sure the Callback URL is configured in Google Console");
    }
}
