<?php

namespace App\Repositories;

use App\Models\FormFieldMapping;
use App\Repositories\Contracts\FormFieldMappingRepositoryInterface;

class FormFieldMappingRepository implements FormFieldMappingRepositoryInterface
{
    public function getMappingsForForm(string $formId): array
    {
        return FormFieldMapping::where('form_id', $formId)
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
    }

    public function upsertMapping(string $formId, string $cognitoField, ?string $entity, ?string $field): void
    {
        FormFieldMapping::updateOrCreate(
            [
                'form_id'       => $formId,
                'cognito_field' => $cognitoField,
            ],
            [
                'nowcerts_entity' => $entity,
                'nowcerts_field'  => $field,
            ]
        );
    }

    public function upsertMappings(string $formId, array $mappings): void
    {
        foreach ($mappings as $mapping) {
            $this->upsertMapping(
                $formId,
                $mapping['cognito_field'],
                $mapping['nowcerts_entity'] ?? null,
                $mapping['nowcerts_field']  ?? null,
            );
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
