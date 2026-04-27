<?php

namespace App\Services;

use App\Traits\HandlesHttpResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CognitoFormsService
{
    use HandlesHttpResponse;

    private PendingRequest $client;

    public function __construct()
    {
        $apiKey  = config('cognito.api_key');
        $baseUrl = config('cognito.base_url');
        $timeout = config('cognito.timeout', 30);

        if (empty($apiKey)) {
            throw new RuntimeException('Cognito Forms API key is not configured. Set COGNITO_API_KEY in your .env file.');
        }

        $this->client = Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->retry(3, 500, fn ($e) =>
                $e instanceof \Illuminate\Http\Client\RequestException &&
                in_array($e->response?->status(), [502, 503, 504], true),
                throw: false,
            );
    }

    public function getForms(): array
    {
        return $this->send('GET', 'forms');
    }

    public function getFormFields(string $formId): array
    {
        return $this->send('GET', "forms/{$formId}/fields");
    }

    public function setFormAvailability(string $formId, bool $available): array
    {
        return $this->send('PUT', "forms/{$formId}", ['IsAvailable' => $available]);
    }

    public function getEntry(string $formId, string $entryId): array
    {
        return $this->send('GET', "forms/{$formId}/entries/{$entryId}");
    }

    public function createEntry(string $formId, array $data): array
    {
        return $this->send('POST', "forms/{$formId}/entries", $data);
    }

    public function updateEntry(string $formId, string $entryId, array $data): array
    {
        return $this->send('PUT', "forms/{$formId}/entries/{$entryId}", $data);
    }

    public function deleteEntry(string $formId, string $entryId): array
    {
        return $this->send('DELETE', "forms/{$formId}/entries/{$entryId}");
    }

    public function getDocument(string $formId, string $entryId, string $documentId): array
    {
        return $this->send('GET', "forms/{$formId}/entries/{$entryId}/documents/{$documentId}");
    }

    private function send(string $method, string $endpoint, array $body = [], array $query = []): array
    {
        $request = $this->client;

        if (! empty($query)) {
            $request = $request->withQueryParameters($query);
        }

        $response = $this->dispatchRequest($request, $method, $endpoint, $body);

        $this->throwOnError($response, $endpoint);

        return $response->json() ?? [];
    }

    protected function resolveErrorMessage(int $status, ?array $body, string $endpoint = ''): string
    {
        return match ($status) {
            401     => 'Unauthorized: check your COGNITO_API_KEY.',
            403     => 'Forbidden: the API key does not have the required scope.',
            404     => 'Not found: the form or entry does not exist.',
            429     => 'Rate limit exceeded: too many requests.',
            502, 503, 504 => 'Cognito Forms is temporarily unavailable (HTTP ' . $status . '). Please try again in a moment.',
            default => $body['message'] ?? "Cognito Forms API error (HTTP {$status}).",
        };
    }
}
