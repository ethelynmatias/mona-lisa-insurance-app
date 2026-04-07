<?php

namespace App\Services;

use App\Models\FormFieldMapping;

/**
 * Maps Cognito Forms entry fields → NowCerts API fields for a specific form.
 *
 * Priority:
 *   1. DB-saved mappings (explicit user selections via the UI)
 *   2. Auto-suggestions by normalised name matching against live NowCerts API fields
 *
 * Usage:
 *   $mapper = new NowCertsFieldMapper($formId, $nowCertsService);
 *   $lookup = $mapper->getLookup();           // for the frontend
 *   $data   = $mapper->mapInsured($entry);    // for API payloads
 */
class NowCertsFieldMapper
{
    /** DB-saved mappings: [ cognitoField => ['entity' => ..., 'field' => ...] ] */
    private array $saved = [];

    /** Available NowCerts fields from API: [ 'Insured' => [...], 'Policy' => [...], ... ] */
    private array $available = [];

    public function __construct(string $formId, NowCertsService $nowcerts)
    {
        // Load explicit user-saved mappings from DB
        $this->saved = FormFieldMapping::where('form_id', $formId)
            ->whereNotNull('nowcerts_entity')
            ->whereNotNull('nowcerts_field')
            ->get()
            ->mapWithKeys(fn ($r) => [
                $r->cognito_field => [
                    'entity' => $r->nowcerts_entity,
                    'field'  => $r->nowcerts_field,
                ],
            ])
            ->all();

        // Load available NowCerts fields from API (cached)
        $this->available = $nowcerts->getAvailableFields();
    }

    // ──────────────────────────────────────────
    //  Public
    // ──────────────────────────────────────────

    /**
     * Return a flat lookup for the frontend.
     * DB-saved mappings take priority; unset fields are auto-suggested by name match.
     *
     * Shape: [ 'CognitoField' => ['entity' => 'Insured', 'field' => 'FirstName'], ... ]
     */
    public function getLookup(): array
    {
        return $this->saved;
    }

    /**
     * Map a Cognito entry to a NowCerts Insured payload.
     */
    public function mapInsured(array $entry): array
    {
        return $this->mapEntity('Insured', $entry);
    }

    /**
     * Map a Cognito entry to a NowCerts Policy payload.
     */
    public function mapPolicy(array $entry): array
    {
        return $this->mapEntity('Policy', $entry);
    }

    /**
     * Map a Cognito entry to a NowCerts Driver payload.
     */
    public function mapDriver(array $entry): array
    {
        return $this->mapEntity('Driver', $entry);
    }

    /**
     * Map a Cognito entry to a NowCerts Vehicle payload.
     */
    public function mapVehicle(array $entry): array
    {
        return $this->mapEntity('Vehicle', $entry);
    }

    /**
     * Auto-suggest mappings for Cognito fields that have no DB-saved mapping,
     * by normalised name-matching against the live NowCerts API fields.
     *
     * Shape: same as getLookup()
     */
    public function getSuggestions(array $cognitoFields): array
    {
        $suggestions = [];

        foreach ($cognitoFields as $field) {
            $name = $field['InternalName'] ?? $field['internalName'] ?? $field['Name'] ?? $field['name'] ?? null;
            if (! $name || isset($this->saved[$name])) {
                continue;
            }

            $match = $this->autoMatch($name);
            if ($match) {
                $suggestions[$name] = $match;
            }
        }

        return $suggestions;
    }

    // ──────────────────────────────────────────
    //  Internal
    // ──────────────────────────────────────────

    private function mapEntity(string $entity, array $entry): array
    {
        $result = [];

        foreach ($this->saved as $cognitoField => $mapping) {
            if ($mapping['entity'] !== $entity) {
                continue;
            }

            if (array_key_exists($cognitoField, $entry)
                && $entry[$cognitoField] !== null
                && $entry[$cognitoField] !== '') {
                $result[$mapping['field']] = $entry[$cognitoField];
            }
        }

        return $result;
    }

    /**
     * Attempt to find a NowCerts field matching the given Cognito field name
     * by normalising both sides (lowercase, strip underscores/spaces/hyphens).
     */
    private function autoMatch(string $cognitoField): ?array
    {
        $needle = $this->normalise($cognitoField);

        foreach ($this->available as $entity => $fields) {
            foreach ($fields as $nowcertsField) {
                if ($this->normalise($nowcertsField) === $needle) {
                    return ['entity' => $entity, 'field' => $nowcertsField];
                }
            }
        }

        return null;
    }

    private function normalise(string $value): string
    {
        return strtolower(preg_replace('/[\s_\-]+/', '', $value));
    }
}
