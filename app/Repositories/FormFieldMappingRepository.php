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

    public function saveOpportunityAgent(string $formId, string $agent): void
    {
        FormFieldMapping::where('form_id', $formId)
            ->where('nowcerts_entity', 'Opportunity')
            ->where('nowcerts_field', 'assigned_to')
            ->where('cognito_field', 'like', '__static:%')
            ->delete();

        if ($agent !== '') {
            FormFieldMapping::create([
                'form_id'         => $formId,
                'cognito_field'   => '__static:' . $agent,
                'nowcerts_entity' => 'Opportunity',
                'nowcerts_field'  => 'assigned_to',
            ]);
        }
    }

    public function getOpportunityAgent(string $formId): string
    {
        $row = FormFieldMapping::where('form_id', $formId)
            ->where('nowcerts_entity', 'Opportunity')
            ->where('nowcerts_field', 'assigned_to')
            ->where('cognito_field', 'like', '__static:%')
            ->first();

        return $row ? substr($row->cognito_field, strlen('__static:')) : '';
    }

    /**
     * Agency location ID pushed as primaryAgencyOfficeId when the primary
     * location toggle is enabled for a form.
     */
    private const PRIMARY_AGENCY_OFFICE_ID = 'd5342454-e8b2-438a-a6a0-b78107456986';

    public function savePrimaryLocation(string $formId, bool $enabled): void
    {
        FormFieldMapping::where('form_id', $formId)
            ->where('nowcerts_entity', 'Insured')
            ->where('nowcerts_field', 'primary_agency_office_id')
            ->where('cognito_field', 'like', '__static:%')
            ->delete();

        if ($enabled) {
            FormFieldMapping::create([
                'form_id'         => $formId,
                'cognito_field'   => '__static:' . self::PRIMARY_AGENCY_OFFICE_ID,
                'nowcerts_entity' => 'Insured',
                'nowcerts_field'  => 'primary_agency_office_id',
            ]);
        }
    }

    public function getPrimaryLocation(string $formId): bool
    {
        return FormFieldMapping::where('form_id', $formId)
            ->where('nowcerts_entity', 'Insured')
            ->where('nowcerts_field', 'primary_agency_office_id')
            ->where('cognito_field', 'like', '__static:%')
            ->exists();
    }

    public function savePolicyType(string $formId, string $policyType): void
    {
        FormFieldMapping::where('form_id', $formId)
            ->where('nowcerts_entity', 'GeneralLiability')
            ->where('nowcerts_field', 'policy_type')
            ->where('cognito_field', 'like', '__static:%')
            ->delete();

        if ($policyType !== '') {
            FormFieldMapping::create([
                'form_id'         => $formId,
                'cognito_field'   => '__static:' . $policyType,
                'nowcerts_entity' => 'GeneralLiability',
                'nowcerts_field'  => 'policy_type',
            ]);
        }
    }

    public function getPolicyType(string $formId): string
    {
        $row = FormFieldMapping::where('form_id', $formId)
            ->where('nowcerts_entity', 'GeneralLiability')
            ->where('nowcerts_field', 'policy_type')
            ->where('cognito_field', 'like', '__static:%')
            ->first();

        return $row ? substr($row->cognito_field, strlen('__static:')) : '';
    }

}
