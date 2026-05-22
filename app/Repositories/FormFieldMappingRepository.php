<?php

namespace App\Repositories;

use App\Models\FormFieldMapping;
use App\Repositories\Contracts\FormFieldMappingRepositoryInterface;

class FormFieldMappingRepository implements FormFieldMappingRepositoryInterface
{
    /**
     * Returns all mappings for a form grouped by Cognito field.
     * Shape: [ 'CognitoField' => [ ['entity' => ..., 'field' => ...], ... ] ]
     */
    public function getMappingsForForm(string $formId): array
    {
        return FormFieldMapping::where('form_id', $formId)
            ->whereNotNull('nowcerts_entity')
            ->whereNotNull('nowcerts_field')
            ->get()
            ->groupBy('cognito_field')
            ->map(fn ($rows) => $rows->map(fn ($r) => [
                'entity' => $r->nowcerts_entity,
                'field'  => $r->nowcerts_field,
            ])->values()->all())
            ->all();
    }

    public function upsertMappings(string $formId, array $mappings): void
    {
        // Group incoming rows by cognito_field, collecting only non-null entries.
        $groups = [];
        foreach ($mappings as $m) {
            $key = $m['cognito_field'];
            $groups[$key] ??= [];
            if (! empty($m['nowcerts_entity']) && ! empty($m['nowcerts_field'])) {
                $groups[$key][] = [
                    'entity' => $m['nowcerts_entity'],
                    'field'  => $m['nowcerts_field'],
                ];
            }
        }

        foreach ($groups as $cognitoField => $newMappings) {
            // Delete all existing (non-Upload) rows for this Cognito field.
            FormFieldMapping::where('form_id', $formId)
                ->where('cognito_field', $cognitoField)
                ->where('nowcerts_entity', '!=', 'Upload')
                ->delete();

            // Insert the new set of mappings.
            foreach ($newMappings as $m) {
                FormFieldMapping::create([
                    'form_id'         => $formId,
                    'cognito_field'   => $cognitoField,
                    'nowcerts_entity' => $m['entity'],
                    'nowcerts_field'  => $m['field'],
                ]);
            }
        }
    }

    public function getUploadFieldsForForm(string $formId): array
    {
        return FormFieldMapping::where('form_id', $formId)
            ->where('nowcerts_entity', 'Upload')
            ->pluck('cognito_field')
            ->all();
    }

    public function saveUploadFields(string $formId, array $cognitoFields): void
    {
        // Remove all existing Upload mappings for this form
        FormFieldMapping::where('form_id', $formId)
            ->where('nowcerts_entity', 'Upload')
            ->delete();

        // Insert the new selection
        foreach (array_unique(array_filter($cognitoFields)) as $field) {
            FormFieldMapping::updateOrCreate(
                ['form_id' => $formId, 'cognito_field' => $field],
                ['nowcerts_entity' => 'Upload', 'nowcerts_field' => null],
            );
        }
    }

}
