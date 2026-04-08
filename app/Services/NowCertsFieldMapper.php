<?php

namespace App\Services;

use App\Enums\NowCertsEntity;
use App\Repositories\Contracts\FormFieldMappingRepositoryInterface;

class NowCertsFieldMapper
{
    /** DB-saved mappings: [ cognitoField => ['entity' => ..., 'field' => ...] ] */
    private array $saved = [];

    /** Available NowCerts fields: [ 'Insured' => [...], 'Policy' => [...], ... ] */
    private array $available = [];

    public function __construct(
        string $formId,
        NowCertsService $nowcerts,
        FormFieldMappingRepositoryInterface $mappingRepository,
    ) {
        $this->saved = $mappingRepository->getMappingsForForm($formId);

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

            $result[$mapping['field']] = $entry[$cognitoField];
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
            } elseif (str_ends_with($lower, '.firstandlast') || ! str_contains($lower, '.')) {
                $parts = explode(' ', trim($value), 2);
                return array_filter([
                    'FirstName' => $parts[0] ?? null,
                    'LastName'  => $parts[1] ?? null,
                ]);
            }
        }

        return array_filter([
            'FirstName' => $first,
            'LastName'  => $last,
        ]);
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
            // List arrays (repeating sections, file attachments) are skipped here —
            // repeating occupant sections are handled by extractRepeatingEntries().
        }

        return $result;
    }

    /**
     * Extract each item from repeating list sections (e.g. multiple occupants)
     * and return them as individually flattened entries.
     *
     * Only processes list arrays whose items are associative (object-like).
     * File upload arrays (items are scalars) are ignored.
     *
     * Example payload:
     *   "Occupants": [
     *     { "Name": { "First": "John", "Last": "Doe" }, "EMail": "john@example.com" },
     *     { "Name": { "First": "Jane", "Last": "Smith" }, "EMail": "jane@example.com" }
     *   ]
     *
     * Returns:
     *   [
     *     ["Name.First" => "John", "Name.Last" => "Doe", "EMail" => "john@example.com"],
     *     ["Name.First" => "Jane", "Name.Last" => "Smith", "EMail" => "jane@example.com"],
     *   ]
     */
    public static function extractRepeatingEntries(array $rawEntry): array
    {
        $results = [];

        foreach ($rawEntry as $value) {
            if (! is_array($value) || ! array_is_list($value)) {
                continue;
            }

            foreach ($value as $item) {
                if (! is_array($item) || array_is_list($item)) {
                    continue; // scalar list (e.g. file upload URLs) — skip
                }

                $results[] = self::flattenEntry($item);
            }
        }

        return $results;
    }
}
