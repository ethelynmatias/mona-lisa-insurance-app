<?php

namespace App\Services;

/**
 * Maps Cognito Forms entry field names to NowCerts API field names.
 *
 * Usage:
 *   $mapper  = new NowCertsFieldMapper($mappingConfig);
 *   $insured = $mapper->mapInsured($cognitoEntry);
 *   $policy  = $mapper->mapPolicy($cognitoEntry);
 */
class NowCertsFieldMapper
{
    /**
     * Default field map: Cognito field name (key) → NowCerts field name (value).
     *
     * Override per-form by passing a custom $map array to the constructor.
     *
     * Insured fields
     */
    private array $insuredMap;

    /**
     * Policy fields
     */
    private array $policyMap;

    /**
     * Driver fields
     */
    private array $driverMap;

    /**
     * Vehicle fields
     */
    private array $vehicleMap;

    public function __construct(array $map = [])
    {
        $this->insuredMap = array_merge($this->defaultInsuredMap(), $map['insured'] ?? []);
        $this->policyMap  = array_merge($this->defaultPolicyMap(),  $map['policy']  ?? []);
        $this->driverMap  = array_merge($this->defaultDriverMap(),  $map['driver']  ?? []);
        $this->vehicleMap = array_merge($this->defaultVehicleMap(), $map['vehicle'] ?? []);
    }

    // ──────────────────────────────────────────
    //  Static helpers
    // ──────────────────────────────────────────

    /**
     * All selectable NowCerts fields grouped by entity.
     * Used to populate the mapping dropdowns on the frontend.
     */
    public static function availableFields(): array
    {
        return [
            'Insured' => [
                'FirstName', 'LastName', 'MiddleName', 'CommercialName', 'Dba',
                'EMail', 'EMail2', 'EMail3', 'Phone', 'CellPhone', 'Fax', 'SmsPhone',
                'AddressLine1', 'AddressLine2', 'City', 'State', 'ZipCode',
                'Website', 'FEIN', 'DateOfBirth', 'InsuredId', 'CustomerId',
                'Description', 'TagName', 'ReferralSourceCompanyName',
            ],
            'Policy' => [
                'Number', 'EffectiveDate', 'ExpirationDate', 'BindDate',
                'CarrierName', 'LineOfBusinessName', 'MgaName',
                'Premium', 'AgencyCommissionPercent',
                'Description', 'BillingType', 'BusinessType',
                'InsuredFirstName', 'InsuredLastName', 'InsuredEmail', 'InsuredDatabaseId',
            ],
            'Driver' => [
                'FirstName', 'LastName', 'MiddleName',
                'DateOfBirth', 'LicenseNumber', 'LicenseState',
                'Gender', 'MaritalStatus', 'InsuredDatabaseId',
            ],
            'Vehicle' => [
                'Vin', 'Year', 'Make', 'Model', 'BodyType',
                'GVW', 'StatedAmount', 'PlateNumber', 'PlateState', 'InsuredDatabaseId',
            ],
        ];
    }

    // ──────────────────────────────────────────
    //  Public accessors
    // ──────────────────────────────────────────

    /**
     * Return a flat lookup of all Cognito field names → NowCerts mapping info.
     *
     * Shape: [ 'CognitoFieldName' => ['entity' => 'Insured', 'field' => 'FirstName'], ... ]
     */
    public function getLookup(): array
    {
        $lookup = [];

        foreach ($this->insuredMap as $cognito => $nowcerts) {
            $lookup[$cognito] = ['entity' => 'Insured', 'field' => $nowcerts];
        }

        foreach ($this->policyMap as $cognito => $nowcerts) {
            $lookup[$cognito] ??= ['entity' => 'Policy', 'field' => $nowcerts];
        }

        foreach ($this->driverMap as $cognito => $nowcerts) {
            $lookup[$cognito] ??= ['entity' => 'Driver', 'field' => $nowcerts];
        }

        foreach ($this->vehicleMap as $cognito => $nowcerts) {
            $lookup[$cognito] ??= ['entity' => 'Vehicle', 'field' => $nowcerts];
        }

        return $lookup;
    }

    // ──────────────────────────────────────────
    //  Public mappers
    // ──────────────────────────────────────────

    /**
     * Map a Cognito entry to a NowCerts Insured payload.
     */
    public function mapInsured(array $entry): array
    {
        return $this->applyMap($entry, $this->insuredMap);
    }

    /**
     * Map a Cognito entry to a NowCerts Policy payload.
     */
    public function mapPolicy(array $entry): array
    {
        return $this->applyMap($entry, $this->policyMap);
    }

    /**
     * Map a Cognito entry to a NowCerts Driver payload.
     */
    public function mapDriver(array $entry): array
    {
        return $this->applyMap($entry, $this->driverMap);
    }

    /**
     * Map a Cognito entry to a NowCerts Vehicle payload.
     */
    public function mapVehicle(array $entry): array
    {
        return $this->applyMap($entry, $this->vehicleMap);
    }

    // ──────────────────────────────────────────
    //  Default maps
    // ──────────────────────────────────────────

    private function defaultInsuredMap(): array
    {
        return [
            // Cognito field name          => NowCerts field name
            'FirstName'                    => 'FirstName',
            'First_Name'                   => 'FirstName',
            'first_name'                   => 'FirstName',

            'LastName'                     => 'LastName',
            'Last_Name'                    => 'LastName',
            'last_name'                    => 'LastName',

            'MiddleName'                   => 'MiddleName',
            'Middle_Name'                  => 'MiddleName',

            'CommercialName'               => 'CommercialName',
            'Business_Name'                => 'CommercialName',
            'Company_Name'                 => 'CommercialName',

            'Dba'                          => 'Dba',
            'DBA'                          => 'Dba',

            'Email'                        => 'EMail',
            'EmailAddress'                 => 'EMail',
            'Email_Address'                => 'EMail',

            'Email2'                       => 'EMail2',
            'Email3'                       => 'EMail3',

            'Phone'                        => 'Phone',
            'PhoneNumber'                  => 'Phone',
            'Phone_Number'                 => 'Phone',

            'CellPhone'                    => 'CellPhone',
            'Cell_Phone'                   => 'CellPhone',
            'Mobile'                       => 'CellPhone',

            'Fax'                          => 'Fax',
            'FaxNumber'                    => 'Fax',

            'AddressLine1'                 => 'AddressLine1',
            'Address_Line_1'               => 'AddressLine1',
            'Address'                      => 'AddressLine1',
            'StreetAddress'                => 'AddressLine1',

            'AddressLine2'                 => 'AddressLine2',
            'Address_Line_2'               => 'AddressLine2',

            'City'                         => 'City',
            'State'                        => 'State',
            'ZipCode'                      => 'ZipCode',
            'Zip'                          => 'ZipCode',
            'Zip_Code'                     => 'ZipCode',
            'PostalCode'                   => 'ZipCode',

            'Website'                      => 'Website',
            'FEIN'                         => 'FEIN',
            'TaxId'                        => 'FEIN',
            'Tax_Id'                       => 'FEIN',

            'DateOfBirth'                  => 'DateOfBirth',
            'DOB'                          => 'DateOfBirth',
            'Date_Of_Birth'                => 'DateOfBirth',

            'InsuredId'                    => 'InsuredId',
            'CustomerId'                   => 'CustomerId',

            'Description'                  => 'Description',
            'Notes'                        => 'Description',

            'ReferralSource'               => 'ReferralSourceCompanyName',
            'Referral_Source'              => 'ReferralSourceCompanyName',

            'Tag'                          => 'TagName',
            'TagName'                      => 'TagName',
        ];
    }

    private function defaultPolicyMap(): array
    {
        return [
            'PolicyNumber'                 => 'Number',
            'Policy_Number'                => 'Number',
            'Number'                       => 'Number',

            'EffectiveDate'                => 'EffectiveDate',
            'Effective_Date'               => 'EffectiveDate',
            'StartDate'                    => 'EffectiveDate',

            'ExpirationDate'               => 'ExpirationDate',
            'Expiration_Date'              => 'ExpirationDate',
            'ExpDate'                      => 'ExpirationDate',
            'EndDate'                      => 'ExpirationDate',

            'BindDate'                     => 'BindDate',
            'Bind_Date'                    => 'BindDate',

            'Carrier'                      => 'CarrierName',
            'CarrierName'                  => 'CarrierName',
            'Carrier_Name'                 => 'CarrierName',

            'LineOfBusiness'               => 'LineOfBusinessName',
            'Line_Of_Business'             => 'LineOfBusinessName',
            'LOB'                          => 'LineOfBusinessName',

            'Premium'                      => 'Premium',
            'PremiumAmount'                => 'Premium',
            'Annual_Premium'               => 'Premium',

            'AgencyCommissionPercent'      => 'AgencyCommissionPercent',
            'Commission_Percent'           => 'AgencyCommissionPercent',

            'Description'                  => 'Description',
            'Notes'                        => 'Description',

            'BillingType'                  => 'BillingType',
            'Billing_Type'                 => 'BillingType',

            'InsuredFirstName'             => 'InsuredFirstName',
            'InsuredLastName'              => 'InsuredLastName',
            'InsuredEmail'                 => 'InsuredEmail',
            'InsuredDatabaseId'            => 'InsuredDatabaseId',
        ];
    }

    private function defaultDriverMap(): array
    {
        return [
            'FirstName'                    => 'FirstName',
            'First_Name'                   => 'FirstName',

            'LastName'                     => 'LastName',
            'Last_Name'                    => 'LastName',

            'MiddleName'                   => 'MiddleName',

            'DateOfBirth'                  => 'DateOfBirth',
            'DOB'                          => 'DateOfBirth',
            'Date_Of_Birth'                => 'DateOfBirth',

            'LicenseNumber'                => 'LicenseNumber',
            'License_Number'               => 'LicenseNumber',
            'DLNumber'                     => 'LicenseNumber',

            'LicenseState'                 => 'LicenseState',
            'License_State'                => 'LicenseState',
            'DLState'                      => 'LicenseState',

            'Gender'                       => 'Gender',
            'MaritalStatus'                => 'MaritalStatus',
            'Marital_Status'               => 'MaritalStatus',

            'InsuredDatabaseId'            => 'InsuredDatabaseId',
        ];
    }

    private function defaultVehicleMap(): array
    {
        return [
            'VIN'                          => 'Vin',
            'Vin'                          => 'Vin',

            'Year'                         => 'Year',
            'Make'                         => 'Make',
            'Model'                        => 'Model',
            'BodyType'                     => 'BodyType',
            'Body_Type'                    => 'BodyType',

            'GVW'                          => 'GVW',
            'GrossVehicleWeight'           => 'GVW',

            'StatedAmount'                 => 'StatedAmount',
            'Stated_Amount'                => 'StatedAmount',

            'PlateNumber'                  => 'PlateNumber',
            'Plate_Number'                 => 'PlateNumber',
            'LicensePlate'                 => 'PlateNumber',

            'PlateState'                   => 'PlateState',
            'Plate_State'                  => 'PlateState',

            'InsuredDatabaseId'            => 'InsuredDatabaseId',
        ];
    }

    // ──────────────────────────────────────────
    //  Internal
    // ──────────────────────────────────────────

    /**
     * Walk a flat Cognito entry array through a field map.
     * Only fields present in the map (and non-null in the entry) are included.
     */
    private function applyMap(array $entry, array $fieldMap): array
    {
        $result = [];

        foreach ($fieldMap as $cognitoKey => $nowCertsKey) {
            if (array_key_exists($cognitoKey, $entry) && $entry[$cognitoKey] !== null && $entry[$cognitoKey] !== '') {
                $result[$nowCertsKey] = $entry[$cognitoKey];
            }
        }

        return $result;
    }
}
