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
}
