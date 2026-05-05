<?php

namespace App\Services;

use App\Repositories\Contracts\FormFieldMappingRepositoryInterface;
use App\Repositories\Contracts\WebhookLogRepositoryInterface;
use App\Traits\PaginatesArray;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class CognitoFormService
{
    use PaginatesArray;

    public function __construct(
        private readonly CognitoFormsService                 $cognito,
        private readonly NowCertsService                     $nowcerts,
        private readonly WebhookLogRepositoryInterface       $webhookLogs,
        private readonly FormFieldMappingRepositoryInterface $mappings,
    ) {}

    public function getForms(Request $request): array
    {
        $forms = [];
        $error = null;

        try {
            $forms = $this->cognito->getForms();
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        $paginated = $this->paginateArray($forms, $request, sortableFields: ['Name', 'Id']);

        return [
            'forms'          => $paginated['items'],
            'search'         => $paginated['search'],
            'sort'           => $paginated['sort'],
            'direction'      => $paginated['direction'],
            'pagination'     => $paginated['pagination'],
            'perPageOptions' => $this->perPageOptions,
            'webhooks'       => $this->webhookLogs->latestForDashboard(),
            'error'          => $error,
        ];
    }

    public function getFormDetails(string $formId): array
    {
        $form   = null;
        $fields = [];
        $error  = null;

        try {
            $forms = $this->cognito->getForms();
            $form  = collect($forms)->firstWhere('Id', $formId);

            if (! $form) {
                $error = "Form '{$formId}' not found.";
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        $availableFields      = [];
        $availableFieldsError = null;
        $lookup               = [];
        $uploadFieldOptions   = [];
        $uploadFields         = [];

        try {
            $availableFields = $this->nowcerts->getAvailableFields();

            $allDiscovered      = $this->webhookLogs->getDiscoveredFields($formId);
            $scalarDiscovered   = array_values(array_filter($allDiscovered, fn ($k) => ! str_ends_with($k, '__upload')));
            $uploadFieldOptions = array_values(array_map(
                fn ($k) => substr($k, 0, -strlen('__upload')),
                array_filter($allDiscovered, fn ($k) => str_ends_with($k, '__upload')),
            ));

            $schemaNames = array_column($fields, 'InternalName');
            $this->addDiscoveredFields($fields, $schemaNames, $scalarDiscovered);

            $mapper      = new NowCertsFieldMapper($formId, $this->nowcerts, $this->mappings);
            $lookup      = $mapper->getLookup();
            $suggestions = $mapper->getSuggestions($this->flattenFieldList($fields));

            foreach ($suggestions as $cognitoField => $mapping) {
                $lookup[$cognitoField] ??= $mapping;
            }

            $uploadFields = $this->mappings->getUploadFieldsForForm($formId);
        } catch (Throwable $e) {
            $availableFieldsError = $e->getMessage();
        }

        return [
            'form'                 => $form,
            'fields'               => $fields,
            'mappingLookup'        => $lookup,
            'availableFields'      => $availableFields,
            'availableFieldsError' => $availableFieldsError,
            'uploadFieldOptions'   => $uploadFieldOptions,
            'uploadFields'         => $uploadFields,
            'webhooks'             => $this->webhookLogs->latestForForm($formId),
            'error'                => $error,
        ];
    }

    public function getFormMappings(string $formId): array
    {
        $forms = [];
        try {
            $forms = $this->cognito->getForms();
        } catch (Throwable) {}

        $form = collect($forms)->firstWhere('Id', $formId);

        $rows = collect($this->mappings->getMappingsForForm($formId))
            ->filter(fn ($m) => ! empty($m['entity']) && ! empty($m['field']))
            ->map(fn ($m, $cognitoField) => [
                'cognito_field'   => $cognitoField,
                'nowcerts_entity' => $m['entity'],
                'nowcerts_field'  => $m['field'],
            ])
            ->values()
            ->all();

        return [
            'form'     => $form,
            'formId'   => $formId,
            'mappings' => $rows,
        ];
    }

    public function saveMappings(string $formId, array $mappings, array $uploadFields): void
    {
        $this->mappings->upsertMappings($formId, $mappings);
        $this->mappings->saveUploadFields($formId, $uploadFields);
    }

    protected function matchesSearch(mixed $item, string $search): bool
    {
        return str_contains(strtolower($item['Name'] ?? ''), strtolower($search));
    }

    private function addDiscoveredFields(array &$fields, array &$schemaNames, array $discoveredKeys): void
    {
        $grouped    = [];
        $standalone = [];

        foreach ($discoveredKeys as $key) {
            if (in_array($key, $schemaNames, true)) {
                continue;
            }

            if (str_contains($key, '.')) {
                [$parent] = explode('.', $key, 2);
                $grouped[$parent][] = $key;
            } else {
                $standalone[] = $key;
            }
        }

        if (! empty($standalone)) {
            $children    = array_map(fn (string $k) => $this->makeDiscoveredField($k, $k), $standalone);
            $fields[]    = array_merge(
                $this->makeDiscoveredField('Properties', '__group__others'),
                ['Type' => 'discovered-group', 'Children' => $children],
            );
            $schemaNames = array_merge($schemaNames, $standalone, ['__group__others']);
        }

        foreach ($grouped as $parent => $childKeys) {
            $parentInSchema = in_array($parent, $schemaNames, true);
            $schemaNames    = array_merge($schemaNames, $childKeys, [$parent]);

            if (! $parentInSchema) {
                $children = array_map(function (string $k) {
                    [, $sub] = explode('.', $k, 2);
                    return $this->makeDiscoveredField($sub, $k);
                }, $childKeys);

                $fields[] = array_merge(
                    $this->makeDiscoveredField($parent, $parent),
                    ['Type' => 'discovered-group', 'Children' => $children],
                );
            } else {
                // Parent already in schema — add children as individual discovered rows
                foreach ($childKeys as $k) {
                    $fields[] = $this->makeDiscoveredField($k, $k);
                }
            }
        }
    }

    private function makeDiscoveredField(string $name, string $internalName): array
    {
        return [
            'Name'         => $name,
            'InternalName' => $internalName,
            'Type'         => 'discovered',
            'FieldType'    => 'webhook',
            'PropertyType' => '',
            'Required'     => false,
        ];
    }

    private function flattenFieldList(array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            $result[] = $field;
            $children = $field['Children'] ?? $field['Fields'] ?? [];
            if (! empty($children)) {
                $result = array_merge($result, $this->flattenFieldList($children));
            }
        }
        return $result;
    }
}
