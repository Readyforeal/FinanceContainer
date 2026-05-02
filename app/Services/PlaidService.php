<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class PlaidService
{
    private string $baseUrl;
    private string $clientId;
    private string $secret;

    public function __construct()
    {
        $env = config('services.plaid.env', 'sandbox');
        $this->baseUrl = match ($env) {
            'production' => 'https://production.plaid.com',
            'development' => 'https://development.plaid.com',
            default => 'https://sandbox.plaid.com',
        };
        $this->clientId = config('services.plaid.client_id');
        $this->secret = config('services.plaid.secret');
    }

    public function createLinkToken(string $userId): array
    {
        $response = Http::post("{$this->baseUrl}/link/token/create", [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'user' => ['client_user_id' => $userId],
            'client_name' => config('app.name'),
            'products' => ['transactions'],
            'country_codes' => ['US'],
            'language' => 'en',
        ]);
        return $response->json();
    }

    public function exchangePublicToken(string $publicToken): array
    {
        $response = Http::post("{$this->baseUrl}/item/public_token/exchange", [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'public_token' => $publicToken,
        ]);
        return $response->json();
    }

    public function syncTransactions(string $accessToken, ?string $cursor): array
    {
        $body = [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'access_token' => $accessToken,
        ];
        if ($cursor) {
            $body['cursor'] = $cursor;
        }
        $response = Http::post("{$this->baseUrl}/transactions/sync", $body);
        return $response->json();
    }

    public function getAccounts(string $accessToken): array
    {
        $response = Http::post("{$this->baseUrl}/accounts/get", [
            'client_id' => $this->clientId,
            'secret' => $this->secret,
            'access_token' => $accessToken,
        ]);
        return $response->json()['accounts'];
    }
}
