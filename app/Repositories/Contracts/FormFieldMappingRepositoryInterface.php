<?php

namespace App\Repositories\Contracts;


interface FormFieldMappingRepositoryInterface
{
    /**
     * Return all saved mappings for a form as a keyed collection.
     * Shape: [ cognitoField => ['entity' => ..., 'field' => ...] ]
     */
    public function getMappingsForForm(string $formId): array;

    /**
     * Upsert multiple mappings at once.
     *
     * @param  array<array{cognito_field: string, nowcerts_entity: ?string, nowcerts_field: ?string}>  $mappings
     */
    public function upsertMappings(string $formId, array $mappings): void;

    /**
     * Return the Cognito field names configured as file-upload fields for this form.
     * These are stored with nowcerts_entity = 'Upload'.
     */
    public function getUploadFieldsForForm(string $formId): array;

    /**
     * Replace all upload-field mappings for this form with the given set of Cognito field names.
     */
    public function saveUploadFields(string $formId, array $cognitoFields): void;

    /**
     * Persist the selected opportunity agent (static assigned_to value) for a form.
     * Pass an empty string to clear the selection.
     */
    public function saveOpportunityAgent(string $formId, string $agent): void;

    /**
     * Return the currently saved opportunity agent name, or an empty string if none.
     */
    public function getOpportunityAgent(string $formId): string;

}
