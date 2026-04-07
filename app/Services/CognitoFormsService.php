<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CognitoFormsService
{
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
            ->asJson();
    }

    // ──────────────────────────────────────────
    //  Forms
    // ──────────────────────────────────────────

    /**
     * List all forms in the organisation.
     */
    public function getForms(): array
    {
        return $this->send('GET', 'forms');
    }

    /**
     * Get the field schema for a form.
     */
    public function getFormFields(string $formId): array
    {
        return $this->send('GET', "forms/{$formId}/fields");
    }

    /**
     * Enable or disable a form.
     */
    public function setFormAvailability(string $formId, bool $available): array
    {
        return $this->send('PUT', "forms/{$formId}", [
            'IsAvailable' => $available,
        ]);
    }

    /**
     * Get a single entry.
     */
    public function getEntry(string $formId, string $entryId): array
    {
        return $this->send('GET', "forms/{$formId}/entries/{$entryId}");
    }

    /**
     * Create a new entry.
     */
    public function createEntry(string $formId, array $data): array
    {
        return $this->send('POST', "forms/{$formId}/entries", $data);
    }

    /**
     * Update an existing entry.
     */
    public function updateEntry(string $formId, string $entryId, array $data): array
    {
        return $this->send('PUT', "forms/{$formId}/entries/{$entryId}", $data);
    }

    /**
     * Delete an entry (requires Delete scope on the API key).
     */
    public function deleteEntry(string $formId, string $entryId): array
    {
        return $this->send('DELETE', "forms/{$formId}/entries/{$entryId}");
    }

    /**
     * Get a generated document from an entry.
     */
    public function getDocument(string $formId, string $entryId, string $documentId): array
    {
        return $this->send('GET', "forms/{$formId}/entries/{$entryId}/documents/{$documentId}");
    }

    // ──────────────────────────────────────────
    //  Internal
    // ──────────────────────────────────────────

    private function send(string $method, string $endpoint, array $body = [], array $query = []): array
    {
        $request = $this->client;

        if (! empty($query)) {
            $request = $request->withQueryParameters($query);
        }

        $response = match (strtoupper($method)) {
            'GET'    => $request->get($endpoint),
            'POST'   => $request->post($endpoint, $body),
            'PUT'    => $request->put($endpoint, $body),
            'DELETE' => $request->delete($endpoint),
            default  => throw new RuntimeException("Unsupported HTTP method: {$method}"),
        };

        return $this->handleResponse($response);
    }

    private function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $status = $response->status();
        $body   = $response->json();

        $message = match ($status) {
            401 => 'Unauthorized: check your COGNITO_API_KEY.',
            403 => 'Forbidden: the API key does not have the required scope.',
            404 => 'Not found: the form or entry does not exist.',
            429 => 'Rate limit exceeded: too many requests.',
            default => $body['message'] ?? "Cognito Forms API error (HTTP {$status}).",
        };

        throw new RuntimeException($message, $status);
    }
}
