<?php

namespace App\Services;

use App\Models\ZohoToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZohoService
{
    protected $service;
    protected $clientId;
    protected $clientSecret;
    protected $refreshToken;
    protected $dataCenter;
    protected $orgId;

    public function __construct(string $service = 'crm')
    {
        $this->service = $service;
        $this->dataCenter = config("zoho.{$service}.data_center", 'in');
        $this->orgId = config("zoho.{$service}.org_id");
        $this->clientId = config("zoho.{$service}.client_id");
        $this->clientSecret = config("zoho.{$service}.client_secret");
        $this->refreshToken = config("zoho.{$service}.refresh_token");
    }

    public function getAccountsUrl(): string
    {
        $domain = $this->dataCenter === 'in' ? 'zoho.in' : "zoho.{$this->dataCenter}";
        return "https://accounts.{$domain}";
    }

    public function getCrmApiUrl(): string
    {
        $domain = $this->dataCenter === 'in' ? 'zoho.in' : "zoho.{$this->dataCenter}";
        return "https://www.crm.{$domain}/crm/v2";
    }

    public function getDeskApiUrl(): string
    {
        $domain = $this->dataCenter === 'in' ? 'zoho.in' : "zoho.{$this->dataCenter}";
        return "https://desk.{$domain}/api/v1";
    }

    public function getApiBaseUrl(): string
    {
        return $this->service === 'crm' ? $this->getCrmApiUrl() : $this->getDeskApiUrl();
    }

    public function getAuthorizationUrl(): string
    {
        $scopes = $this->service === 'crm'
            ? 'ZohoCRM.modules.ALL,ZohoCRM.settings.ALL'
            : 'Desk.tickets.ALL,Desk.contacts.ALL,Desk.basic.READ,Desk.settings.ALL';

        $accountsUrl = $this->getAccountsUrl();

        return "{$accountsUrl}/oauth/v2/auth?" . http_build_query([
            'scope' => $scopes,
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => config("zoho.{$this->service}.redirect_uri"),
            'access_type' => 'offline',
            'state' => $this->service,
        ]);
    }

    public function exchangeCodeForTokens(string $code): array
    {
        $response = Http::asForm()->post("{$this->getAccountsUrl()}/oauth/v2/token", [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => config("zoho.{$this->service}.redirect_uri"),
        ]);

        $data = $response->json();

        if (isset($data['access_token'])) {
            $this->saveTokens($data);
            return $data;
        }

        Log::error("Zoho {$this->service} token exchange failed", $data);
        throw new \Exception('Failed to exchange Zoho authorization code: ' . ($data['error'] ?? 'unknown'));
    }

    public function getAccessToken(): string
    {
        $token = ZohoToken::where('service', $this->service)->first();

        if ($token && $token->token_expires_at && $token->token_expires_at->isFuture() && $token->access_token) {
            return $token->access_token;
        }

        return $this->refreshAccessToken();
    }

    public function refreshAccessToken(): string
    {
        $token = ZohoToken::where('service', $this->service)->first();
        $refreshToken = $token?->refresh_token ?? $this->refreshToken;

        if (!$refreshToken) {
            throw new \Exception("No refresh token available for Zoho {$this->service}. Please authorize first.");
        }

        $response = Http::asForm()->post("{$this->getAccountsUrl()}/oauth/v2/token", [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        $data = $response->json();

        if (isset($data['access_token'])) {
            $this->saveTokens($data);
            return $data['access_token'];
        }

        Log::error("Zoho {$this->service} token refresh failed", $data);
        throw new \Exception('Failed to refresh Zoho token: ' . ($data['error'] ?? 'unknown'));
    }

    protected function saveTokens(array $data): void
    {
        $expiresIn = $data['expires_in'] ?? 3600;

        $token = ZohoToken::where('service', $this->service)->first();

        $updateData = [
            'access_token' => $data['access_token'],
            'api_domain' => $data['api_domain'] ?? $token?->api_domain,
            'user_id' => $data['user_id'] ?? $token?->user_id,
            'token_expires_at' => now()->addSeconds($expiresIn - 300),
        ];

        // Only update refresh_token if Zoho actually sent one
        if (!empty($data['refresh_token'])) {
            $updateData['refresh_token'] = $data['refresh_token'];
        }

        if ($token) {
            $token->update($updateData);
        } else {
            $updateData['service'] = $this->service;
            $updateData['refresh_token'] = $data['refresh_token'] ?? null;
            ZohoToken::create($updateData);
        }
    }

    public function isAuthorized(): bool
    {
        return ZohoToken::where('service', $this->service)
            ->whereNotNull('refresh_token')
            ->exists();
    }

    public function makeRequest(string $method, string $endpoint, array $data = [], array $headers = []): array
    {
        $token = $this->getAccessToken();
        $url = $this->getApiBaseUrl() . $endpoint;

        Log::info("Zoho {$this->service} API request", ['method' => $method, 'url' => $url]);

        $defaultHeaders = array_merge([
            'Authorization' => "Zoho-oauthtoken {$token}",
            'Content-Type' => 'application/json',
        ], $headers);

        if ($this->service === 'desk') {
            $defaultHeaders['orgId'] = $this->orgId;
        }

        $response = match (strtoupper($method)) {
            'GET' => Http::withHeaders($defaultHeaders)->get($url, $data),
            'POST' => Http::withHeaders($defaultHeaders)->post($url, $data),
            'PUT' => Http::withHeaders($defaultHeaders)->put($url, $data),
            'PATCH' => Http::withHeaders($defaultHeaders)->patch($url, $data),
            'DELETE' => Http::withHeaders($defaultHeaders)->delete($url),
            default => throw new \Exception("Unsupported HTTP method: {$method}"),
        };

        Log::info("Zoho {$this->service} API response", ['status' => $response->status(), 'url' => $url]);

        $result = $response->json();

        if ($response->status() === 401) {
            Log::warning("Zoho {$this->service} 401, refreshing token...");
            $newToken = $this->refreshAccessToken();
            $defaultHeaders['Authorization'] = "Zoho-oauthtoken {$newToken}";
            $response = match (strtoupper($method)) {
                'GET' => Http::withHeaders($defaultHeaders)->get($url, $data),
                'POST' => Http::withHeaders($defaultHeaders)->post($url, $data),
                'PUT' => Http::withHeaders($defaultHeaders)->put($url, $data),
                'PATCH' => Http::withHeaders($defaultHeaders)->patch($url, $data),
                'DELETE' => Http::withHeaders($defaultHeaders)->delete($url),
                default => throw new \Exception("Unsupported HTTP method: {$method}"),
            };
            $result = $response->json();
        }

        if (!$response->successful()) {
            Log::error("Zoho {$this->service} API error", ['status' => $response->status(), 'url' => $url, 'response' => $result]);
        }

        return [
            'status' => $response->status(),
            'data' => $result,
            'ok' => $response->successful(),
        ];
    }
}
