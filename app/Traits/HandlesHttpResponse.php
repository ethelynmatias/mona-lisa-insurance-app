<?php

namespace App\Traits;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use RuntimeException;

trait HandlesHttpResponse
{
    /**
     * Dispatch an HTTP request using the given method and return the raw Response.
     */
    protected function dispatchRequest(
        PendingRequest $request,
        string $method,
        string $endpoint,
        array $body = [],
    ): Response {
        return match (strtoupper($method)) {
            'GET'    => $request->get($endpoint),
            'POST'   => $request->post($endpoint, $body),
            'PUT'    => $request->put($endpoint, $body),
            'PATCH'  => $request->patch($endpoint, $body),
            'DELETE' => $request->delete($endpoint),
            default  => throw new RuntimeException("Unsupported HTTP method: {$method}"),
        };
    }

    /**
     * Throw a RuntimeException with a human-readable message for non-2xx responses.
     * Override resolveErrorMessage() in the consuming class to customise per-status text.
     */
    protected function throwOnError(Response $response, string $endpoint = ''): void
    {
        if ($response->successful()) {
            return;
        }

        $status  = $response->status();
        $body    = $response->json();
        $message = $this->resolveErrorMessage($status, $body, $endpoint);

        throw new RuntimeException($message, $status);
    }

    /**
     * Map an HTTP status code to a human-readable error message.
     * Override in the consuming class to add service-specific messages.
     */
    protected function resolveErrorMessage(int $status, ?array $body, string $endpoint = ''): string
    {
        return match ($status) {
            401     => 'Unauthorized: check your API credentials.',
            403     => 'Forbidden: your account does not have permission for this action.',
            404     => 'Not found: the requested resource does not exist.',
            429     => 'Rate limit exceeded: too many requests.',
            default => $body['Message'] ?? $body['message'] ?? "API error (HTTP {$status}).",
        };
    }
}
