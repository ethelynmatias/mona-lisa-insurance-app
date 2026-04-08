<?php

namespace App\Services;

use App\Enums\NowCertsEntity;
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

        // Load available NowCerts fields from API (cached).
        // Only used for getSuggestions() — not needed for mapEntity() sync.
        // Silently falls back to empty array so sync still works when API is unavailable.
        try {
            $this->available = $nowcerts->getAvailableFields();
        } catch (\Throwable) {
            $this->available = [];
        }
    }
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
     * If no FirstName/LastName is resolved from saved mappings,
     * falls back to occupant name fields in the entry.
     */
    public function mapInsured(array $entry): array
    {
        $result = $this->mapEntity(NowCertsEntity::Insured, $entry);

        if (empty($result['FirstName']) && empty($result['LastName'])) {
            $result = array_merge($result, $this->resolveOccupantName($entry));
        }

        return $result;
    }

    public function mapPolicy(array $entry): array
    {
        return $this->mapEntity(NowCertsEntity::Policy, $entry);
    }

    public function mapDriver(array $entry): array
    {
        return $this->mapEntity(NowCertsEntity::Driver, $entry);
    }

    public function mapVehicle(array $entry): array
    {
        return $this->mapEntity(NowCertsEntity::Vehicle, $entry);
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
    private function mapEntity(NowCertsEntity $entity, array $entry): array
    {
        $result = [];

        foreach ($this->saved as $cognitoField => $mapping) {
            if ($mapping['entity'] !== $entity->value) {
                continue;
            }

            if (! array_key_exists($cognitoField, $entry)
                || $entry[$cognitoField] === null
                || $entry[$cognitoField] === '') {
                continue;
            }

            $field = $mapping['field'];
            $value = $entry[$cognitoField];

            if (str_starts_with($field, '__custom__')) {
                // Custom transform — expands one Cognito field to multiple NowCerts fields
                foreach ($this->applyCustomTransform($field, (string) $value) as $k => $v) {
                    $result[$k] = $v;
                }
            } else {
                $result[$field] = $value;
            }
        }

        return $result;
    }

    /**
     * Fallback name resolution when no FirstName/LastName came from saved mappings.
     * Tries NameOfInsured first, then NameOfOccupant if insured name is missing.
     */
    private function resolveOccupantName(array $entry): array
    {
        return $this->extractName($entry, 'insured')
            ?: $this->extractName($entry, 'occupant');
    }

    /**
     * Extract FirstName/LastName from entry keys containing the given keyword.
     * Handles dot-notation sub-fields (.First, .Last, .FirstAndLast) and plain strings.
     */
    private function extractName(array $entry, string $keyword): array
    {
        $first = null;
        $last  = null;

        foreach ($entry as $key => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            $lower = strtolower($key);

            if (! str_contains($lower, $keyword)) {
                continue;
            }

            if (str_ends_with($lower, '.first')) {
                $first = $value;
            } elseif (str_ends_with($lower, '.last')) {
                $last = $value;
            } elseif (str_ends_with($lower, '.firstandlast')) {
                return $this->splitFullName($value);
            } elseif (! str_contains($lower, '.')) {
                // Plain field e.g. "NameOfInsured" or "NameOfOccupant"
                return $this->splitFullName($value);
            }
        }

        return array_filter([
            'FirstName' => $first,
            'LastName'  => $last,
        ]);
    }

    /**
     * Dispatch a custom transform and return a [ nowcertsField => value ] map.
     */
    private function applyCustomTransform(string $customField, string $value): array
    {
        return match ($customField) {
            '__custom__full_name' => $this->splitFullName($value),
            default               => [],
        };
    }

    /**
     * Split "John Doe" → ['FirstName' => 'John', 'LastName' => 'Doe'].
     * Handles single-word names (LastName omitted) and multi-word last names
     * ("John Michael Doe" → FirstName: John, LastName: Michael Doe).
     */
    private function splitFullName(string $fullName): array
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return [];
        }

        $parts  = explode(' ', $fullName, 2);
        $result = ['FirstName' => $parts[0]];

        if (! empty($parts[1])) {
            $result['LastName'] = trim($parts[1]);
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

    /**
     * Flatten a Cognito webhook entry to dot-notation keys (one level deep).
     *
     * Example:
     *   "NameOfInsured": { "First": "John", "Last": "Doe" }
     *   becomes:
     *   "NameOfInsured.First" => "John"
     *   "NameOfInsured.Last"  => "Doe"
     *
     * Scalar top-level values are kept as-is.
     * List arrays (e.g. file uploads) are skipped.
     */
    public static function flattenEntry(array $entry): array
    {
        $result = [];

        foreach ($entry as $key => $value) {
            if (is_array($value) && ! array_is_list($value)) {
                // Associative array — expand one level with dot notation
                foreach ($value as $subKey => $subValue) {
                    if (! is_array($subValue)) {
                        $result["{$key}.{$subKey}"] = $subValue;
                    }
                }
            } elseif (! is_array($value)) {
                $result[$key] = $value;
            }
            // List arrays (file attachments etc.) are skipped
        }

        return $result;
    }
}
