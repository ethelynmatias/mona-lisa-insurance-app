<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveMappingsRequest;
use App\Repositories\Contracts\FormFieldMappingRepositoryInterface;
use App\Repositories\Contracts\WebhookLogRepositoryInterface;
use App\Services\CognitoFormsService;
use App\Services\NowCertsFieldMapper;
use App\Services\NowCertsService;
use App\Traits\PaginatesArray;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CognitoController extends Controller
{
    use PaginatesArray;

    public function __construct(
        private readonly CognitoFormsService              $cognito,
        private readonly NowCertsService                  $nowcerts,
        private readonly WebhookLogRepositoryInterface    $webhookLogs,
        private readonly FormFieldMappingRepositoryInterface $mappings,
    ) {}

    public function index(Request $request): Response
    {
        $forms = [];
        $error = null;

        try {
            $forms = $this->cognito->getForms();
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        $paginated = $this->paginateArray($forms, $request, sortableFields: ['Name', 'Id']);

        return Inertia::render('Dashboard', [
            'forms'          => $paginated['items'],
            'search'         => $paginated['search'],
            'sort'           => $paginated['sort'],
            'direction'      => $paginated['direction'],
            'pagination'     => $paginated['pagination'],
            'perPageOptions' => $this->perPageOptions,
            'webhooks'       => $this->webhookLogs->latestForDashboard(),
            'error'          => $error,
        ]);
    }

    public function show(Request $request, string $formId): Response
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
            // $fields = $this->cognito->getFormFields($formId);
            // $fields = $this->expandNestedFields($fields);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        $availableFields      = [];
        $availableFieldsError = null;
        $lookup               = [];

        try {
            $availableFields = $this->nowcerts->getAvailableFields();

            // Merge fields discovered from real webhook payloads into the schema field list
            $schemaNames = array_column($fields, 'InternalName');
            $this->addDiscoveredFields($fields, $schemaNames, $this->webhookLogs->getDiscoveredFields($formId));

            $mapper      = new NowCertsFieldMapper($formId, $this->nowcerts, $this->mappings);
            $lookup      = $mapper->getLookup();
            $suggestions = $mapper->getSuggestions($this->flattenFieldList($fields));

            foreach ($suggestions as $cognitoField => $mapping) {
                $lookup[$cognitoField] ??= $mapping;
            }
        } catch (\Throwable $e) {
            $availableFieldsError = $e->getMessage();
        }

        return Inertia::render('Cognito/FormDetails', [
            'form'                 => $form,
            'fields'               => $fields,
            'mappingLookup'        => $lookup,
            'availableFields'      => $availableFields,
            'availableFieldsError' => $availableFieldsError,
            'webhooks'             => $this->webhookLogs->latestForForm($formId),
            'error'                => $error,
        ]);
    }

    public function saveMappings(SaveMappingsRequest $request, string $formId): RedirectResponse
    {
        $this->mappings->upsertMappings($formId, $request->validated()['mappings']);

        return back()->with('success', 'Mappings saved successfully.');
    }

    protected function matchesSearch(mixed $item, string $search): bool
    {
        return str_contains(strtolower($item['Name'] ?? ''), strtolower($search));
    }

    /**
     * Group dot-notation discovered keys under a parent heading entry with Children.
     * e.g. ["PropertyAddress.City", "PropertyAddress.State"] becomes one
     * 'discovered-group' entry for "PropertyAddress" with two child entries.
     * Keys already present in $schemaNames are skipped.
     */
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
                $this->makeDiscoveredField('Others', '__group__others'),
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

    /** Recursively flatten a field list including Children arrays. */
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

    private function expandNestedFields(array $fields): array
    {
        $subFieldMap = [
            'Name'    => ['First', 'Last', 'Middle', 'Prefix', 'Suffix', 'FirstAndLast'],
            'Address' => ['Line1', 'Line2', 'City', 'State', 'PostalCode', 'Country', 'FullAddress'],
        ];

        $result = [];

        foreach ($fields as $field) {
            $type         = $field['Type'] ?? $field['type'] ?? '';
            $internalName = $field['InternalName'] ?? $field['internalName'] ?? $field['Name'] ?? '';

            if (isset($subFieldMap[$type])) {
                foreach ($subFieldMap[$type] as $sub) {
                    $result[] = array_merge($field, [
                        'Name'         => ($field['Name'] ?? $internalName) . ' — ' . $sub,
                        'InternalName' => $internalName . '.' . $sub,
                        'FieldType'    => $type . '.' . $sub,
                    ]);
                }
            } else {
                $children = $field['Children'] ?? $field['Fields'] ?? [];
                if (! empty($children)) {
                    $field['Children'] = $this->expandNestedFields($children);
                }
                $result[] = $field;
            }
        }

        return $result;
    }
}
