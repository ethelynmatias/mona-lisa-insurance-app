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

        // Log loaded mappings for verification
        \Log::info("NowCerts Field Mapper initialized", [
            'form_id' => $formId,
            'loaded_mappings_count' => count($this->saved),
            'loaded_mappings' => $this->saved,
        ]);

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

    /**
     * Extract multiple drivers from field mappings.
     * Groups Driver entity mappings by common prefixes (e.g., Driver1, Driver2) 
     * and creates separate driver records for each group.
     */
    public function mapDrivers(array $entry): array
    {
        $drivers = [];
        $driverGroups = [];

        // Get all Driver entity mappings
        foreach ($this->saved as $cognitoField => $mapping) {
            if ($mapping['entity'] !== NowCertsEntity::Driver->value) {
                continue;
            }

            if (!array_key_exists($cognitoField, $entry) 
                || $entry[$cognitoField] === null 
                || $entry[$cognitoField] === '') {
                continue;
            }

            // Extract driver group identifier from field name
            // Examples: Driver1.FirstName -> Driver1, DriverInfo2.LastName -> DriverInfo2, FirstName -> default
            $groupKey = $this->extractDriverGroupKey($cognitoField);
            
            if (!isset($driverGroups[$groupKey])) {
                $driverGroups[$groupKey] = [];
            }

            $driverGroups[$groupKey][$mapping['field']] = $entry[$cognitoField];
        }

        // Convert each group to a driver record
        foreach ($driverGroups as $groupKey => $driverData) {
            if (!empty($driverData)) {
                $drivers[] = $driverData;
            }
        }

        return $drivers;
    }

    /**
     * Extract driver group key from a Cognito field name.
     * Groups fields by common prefixes to identify separate drivers.
     */
    private function extractDriverGroupKey(string $cognitoField): string
    {
        // Pattern 1: Driver1.FirstName, Driver2.LastName, etc.
        if (preg_match('/^(Driver\d+)\./', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 2: Name1.First, Name2.Last, etc. (common in forms)
        if (preg_match('/^(Name\d+)\./', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 3: DriverInfo1, DriverData2, etc. (without dots)
        if (preg_match('/^(Driver\w*\d+)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 4: Driver_1_FirstName, Driver_2_LastName, etc.
        if (preg_match('/^(Driver_\d+)_/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 5: FirstDriver_FirstName, SecondDriver_LastName, etc.
        if (preg_match('/(\w*Driver\w*)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 6: NameOfOccupantOperator2, NameOfOccupantOperator3 (form-specific)
        if (preg_match('/^(NameOfOccupantOperator\d*)/', $cognitoField, $matches)) {
            return $matches[1] ?: 'NameOfOccupantOperator';
        }

        // Pattern 7: Occupant2, Operator3, etc.
        if (preg_match('/^((?:Occupant|Operator)\d+)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Default: treat as single driver group
        return 'default';
    }

    public function mapVehicle(array $entry): array
    {
        return $this->mapEntity(NowCertsEntity::Vehicle, $entry);
    }

    /**
     * Extract multiple vehicles from field mappings.
     * Groups Vehicle entity mappings by common prefixes (e.g., Vehicle1, Vehicle2) 
     * and creates separate vehicle records for each group.
     */
    public function mapVehicles(array $entry): array
    {
        $vehicles = [];
        $vehicleGroups = [];

        // Get all Vehicle entity mappings
        foreach ($this->saved as $cognitoField => $mapping) {
            if ($mapping['entity'] !== NowCertsEntity::Vehicle->value) {
                continue;
            }

            if (!array_key_exists($cognitoField, $entry) 
                || $entry[$cognitoField] === null 
                || $entry[$cognitoField] === '') {
                continue;
            }

            // Extract vehicle group identifier from field name
            // Examples: Vehicle1.Year -> Vehicle1, VehicleInfo2.Make -> VehicleInfo2, Year -> default
            $groupKey = $this->extractVehicleGroupKey($cognitoField);
            
            if (!isset($vehicleGroups[$groupKey])) {
                $vehicleGroups[$groupKey] = [];
            }

            $vehicleGroups[$groupKey][$mapping['field']] = $entry[$cognitoField];
        }

        // Convert each group to a vehicle record
        foreach ($vehicleGroups as $groupKey => $vehicleData) {
            if (!empty($vehicleData)) {
                $vehicles[] = $vehicleData;
            }
        }

        return $vehicles;
    }

    /**
     * Extract vehicle group key from a Cognito field name.
     * Groups fields by common prefixes to identify separate vehicles.
     */
    private function extractVehicleGroupKey(string $cognitoField): string
    {
        // Pattern 1: Vehicle1.Year, Vehicle2.Make, etc.
        if (preg_match('/^(Vehicle\d+)\./', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 2: VehicleInfo1, VehicleData2, etc. (without dots)
        if (preg_match('/^(Vehicle\w*\d+)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 3: Vehicle_1_Year, Vehicle_2_Make, etc.
        if (preg_match('/^(Vehicle_\d+)_/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 4: First_Vehicle_Year, Second_Vehicle_Make, etc.
        if (preg_match('/(\w*Vehicle\w*)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Default: treat as single vehicle group
        return 'default';
    }

    public function mapContact(array $entry): array
    {
        return $this->mapEntity(NowCertsEntity::Contact, $entry);
    }

    /**
     * Extract multiple contacts from field mappings.
     * Groups Contact entity mappings by common prefixes (e.g., Contact1, Principal1) 
     * and creates separate contact records for each group.
     */
    public function mapContacts(array $entry): array
    {
        $contacts = [];
        $contactGroups = [];

        // Get all Contact entity mappings
        foreach ($this->saved as $cognitoField => $mapping) {
            if ($mapping['entity'] !== NowCertsEntity::Contact->value) {
                continue;
            }

            if (!array_key_exists($cognitoField, $entry) 
                || $entry[$cognitoField] === null 
                || $entry[$cognitoField] === '') {
                continue;
            }

            // Extract contact group identifier from field name
            // Examples: Contact1.first_name -> Contact1, Principal2.last_name -> Principal2, first_name -> default
            $groupKey = $this->extractContactGroupKey($cognitoField);
            
            if (!isset($contactGroups[$groupKey])) {
                $contactGroups[$groupKey] = [];
            }

            $contactGroups[$groupKey][$mapping['field']] = $entry[$cognitoField];
        }

        // Convert each group to a contact record
        foreach ($contactGroups as $groupKey => $contactData) {
            if (!empty($contactData)) {
                $contacts[] = $contactData;
            }
        }

        // Log contact mapping activity for debugging
        if (!empty($contactGroups)) {
            \Log::info("NowCerts Contacts mapping", [
                'contact_groups' => array_keys($contactGroups),
                'mapped_contacts_count' => count($contacts),
                'mapped_contacts_data' => $contacts,
            ]);
        }

        return $contacts;
    }

    /**
     * Extract contact group key from a Cognito field name.
     * Groups fields by common prefixes to identify separate contacts.
     */
    private function extractContactGroupKey(string $cognitoField): string
    {
        // Pattern 1: Contact1.first_name, Contact2.last_name, etc.
        if (preg_match('/^(Contact\d+)\./', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 2: Principal1.first_name, Principal2.last_name, etc.
        if (preg_match('/^(Principal\d+)\./', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 3: Name1.First, Name2.Last, etc. (when used for contacts)
        if (preg_match('/^(Name\d+)\./', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 4: ContactInfo1, ContactData2, etc. (without dots)
        if (preg_match('/^(Contact\w*\d+)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 5: Contact_1_FirstName, Contact_2_LastName, etc.
        if (preg_match('/^(Contact_\d+)_/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 6: FirstContact_FirstName, SecondContact_LastName, etc.
        if (preg_match('/(\w*Contact\w*)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 7: CoapplicantsName, AdditionalApplicant, etc. (form-specific)
        if (preg_match('/^(CoapplicantsName|AdditionalApplicant\d*|Applicant\d+)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 8: Spouse, Partner, etc. (relationship-based)
        if (preg_match('/^(Spouse|Partner|Beneficiary\d*)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Default: treat as single contact group
        return 'default';
    }

    public function mapGeneralLiabilityNotice(array $entry): array
    {
        return $this->mapEntity(NowCertsEntity::GeneralLiabilityNotice, $entry);
    }

    /**
     * Extract multiple general liability notices from field mappings.
     * Groups GeneralLiabilityNotice entity mappings by common prefixes (e.g., Claim1, Notice1) 
     * and creates separate notice records for each group.
     */
    public function mapGeneralLiabilityNotices(array $entry): array
    {
        $notices = [];
        $noticeGroups = [];

        // Get all GeneralLiabilityNotice entity mappings
        foreach ($this->saved as $cognitoField => $mapping) {
            if ($mapping['entity'] !== NowCertsEntity::GeneralLiabilityNotice->value) {
                continue;
            }

            if (!array_key_exists($cognitoField, $entry) 
                || $entry[$cognitoField] === null 
                || $entry[$cognitoField] === '') {
                continue;
            }

            // Extract notice group identifier from field name
            // Examples: Claim1.description -> Claim1, Notice2.status -> Notice2, description_of_occurrence -> default
            $groupKey = $this->extractGeneralLiabilityNoticeGroupKey($cognitoField);
            
            if (!isset($noticeGroups[$groupKey])) {
                $noticeGroups[$groupKey] = [];
            }

            $noticeGroups[$groupKey][$mapping['field']] = $entry[$cognitoField];
        }

        // Convert each group to a notice record
        foreach ($noticeGroups as $groupKey => $noticeData) {
            if (!empty($noticeData)) {
                $notices[] = $noticeData;
            }
        }

        // Log notice mapping activity for debugging
        if (!empty($noticeGroups)) {
            \Log::info("NowCerts GeneralLiabilityNotices mapping", [
                'notice_groups' => array_keys($noticeGroups),
                'mapped_notices_count' => count($notices),
                'mapped_notices_data' => $notices,
            ]);
        }

        return $notices;
    }

    /**
     * Extract general liability notice group key from a Cognito field name.
     * Groups fields by common prefixes to identify separate notices.
     */
    private function extractGeneralLiabilityNoticeGroupKey(string $cognitoField): string
    {
        // Pattern 1: Claim1.description, Claim2.status, etc.
        if (preg_match('/^(Claim\d+)\./', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 2: Notice1.description, Notice2.claim_number, etc.
        if (preg_match('/^(Notice\d+)\./', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 3: Incident1.description, Incident2.date_of_loss, etc.
        if (preg_match('/^(Incident\d+)\./', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 4: GLNotice1, GLNotice2, etc.
        if (preg_match('/^(GLNotice\d+)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 5: LiabilityNotice1, LiabilityNotice2, etc. (without dots)
        if (preg_match('/^(LiabilityNotice\d+)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 6: Claim_1_Description, Claim_2_Status, etc.
        if (preg_match('/^(Claim_\d+)_/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 7: FirstClaim_Description, SecondClaim_Status, etc.
        if (preg_match('/(\w*Claim\w*|\w*Notice\w*|\w*Incident\w*)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 8: Loss1.description, Loss2.date, etc. (loss-specific)
        if (preg_match('/^(Loss\d+)\./', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 9: Occurrence1, Occurrence2, etc.
        if (preg_match('/^(Occurrence\d+)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Default: treat as single notice group
        return 'default';
    }

    public function mapPolicyCoverage(array $entry): array
    {
        return $this->mapEntity(NowCertsEntity::PolicyCoverage, $entry);
    }

    /**
     * Extract multiple policy coverages from field mappings.
     * Groups PolicyCoverage entity mappings by common prefixes (e.g., Coverage1, Policy1) 
     * and creates separate coverage records for each group.
     */
    public function mapPolicyCoverages(array $entry): array
    {
        $coverages = [];
        $coverageGroups = [];

        // Get all PolicyCoverage entity mappings
        foreach ($this->saved as $cognitoField => $mapping) {
            if ($mapping['entity'] !== NowCertsEntity::PolicyCoverage->value) {
                continue;
            }

            if (!array_key_exists($cognitoField, $entry) 
                || $entry[$cognitoField] === null 
                || $entry[$cognitoField] === '') {
                continue;
            }

            // Extract coverage group identifier from field name
            // Examples: Coverage1.cargo_deductible -> Coverage1, Policy2.generalLiability_occur -> Policy2, cargo_deductible -> default
            $groupKey = $this->extractPolicyCoverageGroupKey($cognitoField);
            
            if (!isset($coverageGroups[$groupKey])) {
                $coverageGroups[$groupKey] = [];
            }

            $coverageGroups[$groupKey][$mapping['field']] = $entry[$cognitoField];
        }

        // Convert each group to a coverage record
        foreach ($coverageGroups as $groupKey => $coverageData) {
            if (!empty($coverageData)) {
                $coverages[] = $coverageData;
            }
        }

        // Log coverage mapping activity for debugging
        if (!empty($coverageGroups)) {
            \Log::info("NowCerts PolicyCoverages mapping", [
                'coverage_groups' => array_keys($coverageGroups),
                'mapped_coverages_count' => count($coverages),
                'mapped_coverages_data' => $coverages,
            ]);
        }

        return $coverages;
    }

    /**
     * Extract policy coverage group key from a Cognito field name.
     * Groups fields by common prefixes to identify separate coverage records.
     */
    private function extractPolicyCoverageGroupKey(string $cognitoField): string
    {
        // Pattern 1: Coverage1.cargo_deductible, Coverage2.generalLiability_occur, etc.
        if (preg_match('/^(Coverage\d+)\./', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 2: Policy1.cargo_limit, Policy2.autoMobileLiability_anyAuto, etc.
        if (preg_match('/^(Policy\d+)\./', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 3: Liability1.generalLiability_occur, Liability2.autoMobileLiability_limitCombinedSingle, etc.
        if (preg_match('/^(Liability\d+)\./', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 4: Cargo1, Physical1, General1, etc. (without dots)
        if (preg_match('/^((?:Cargo|Physical|General|Auto|Flood|Worker|Home|Other)\d+)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 5: Coverage_1_cargo_deductible, Coverage_2_generalLiability_occur, etc.
        if (preg_match('/^(Coverage_\d+)_/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 6: FirstCoverage_cargo_limit, SecondPolicy_generalLiability_occur, etc.
        if (preg_match('/(\w*(?:Coverage|Policy|Liability)\w*)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 7: Commercial1, Personal1, Business1, etc. (coverage type based)
        if (preg_match('/^((?:Commercial|Personal|Business|Residential)\d+)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Default: treat as single coverage group
        return 'default';
    }

    /**
     * Map property fields from the entry using unified mapping approach.
     * Covers Property entity mappings from the main saved mappings.
     */
    public function mapProperty(array $entry): array
    {
        $result           = [];
        $propertyMappings = [];
        $propertyEntities = [
            NowCertsEntity::Property->value,
        ];

        foreach ($this->saved as $cognitoField => $mapping) {
            if (! in_array($mapping['entity'], $propertyEntities, true)) {
                continue;
            }

            $propertyMappings[$cognitoField] = $mapping;

            if (! array_key_exists($cognitoField, $entry)
                || $entry[$cognitoField] === null
                || $entry[$cognitoField] === '') {
                continue;
            }

            $result[$mapping['field']] = $entry[$cognitoField];
        }

        // Log property mapping activity for debugging
        if (!empty($propertyMappings)) {
            \Log::info("NowCerts Property mapping", [
                'configured_property_mappings' => $propertyMappings,
                'mapped_property_data' => $result,
                'available_entry_keys' => array_keys($entry),
            ]);
        }

        return $result;
    }

    /**
     * Extract multiple properties from field mappings.
     * Groups Property entity mappings by common prefixes (e.g., Property1, Property2) 
     * and creates separate property records for each group.
     */
    public function mapProperties(array $entry): array
    {
        $properties = [];
        $propertyGroups = [];

        // Get all Property entity mappings
        foreach ($this->saved as $cognitoField => $mapping) {
            if ($mapping['entity'] !== NowCertsEntity::Property->value) {
                continue;
            }

            if (!array_key_exists($cognitoField, $entry) 
                || $entry[$cognitoField] === null 
                || $entry[$cognitoField] === '') {
                continue;
            }

            // Extract property group identifier from field name
            // Examples: Property1.Address -> Property1, PropertyInfo2.City -> PropertyInfo2, Address -> default
            $groupKey = $this->extractPropertyGroupKey($cognitoField);
            
            if (!isset($propertyGroups[$groupKey])) {
                $propertyGroups[$groupKey] = [];
            }

            $propertyGroups[$groupKey][$mapping['field']] = $entry[$cognitoField];
        }

        // Convert each group to a property record
        foreach ($propertyGroups as $groupKey => $propertyData) {
            if (!empty($propertyData)) {
                $properties[] = $propertyData;
            }
        }

        // Log property mapping activity for debugging
        if (!empty($propertyGroups)) {
            \Log::info("NowCerts Properties mapping", [
                'property_groups' => array_keys($propertyGroups),
                'mapped_properties_count' => count($properties),
                'mapped_properties_data' => $properties,
            ]);
        }

        return $properties;
    }

    /**
     * Extract property group key from a Cognito field name.
     * Groups fields by common prefixes to identify separate properties.
     */
    private function extractPropertyGroupKey(string $cognitoField): string
    {
        // Pattern 1: Property1.Address, Property2.City, etc.
        if (preg_match('/^(Property\d+)\./', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 2: Location1.Street, Location2.City, etc. (common in property forms)
        if (preg_match('/^(Location\d+)\./', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 3: RealEstate1, RealEstate2, etc. (form 16 specific)
        if (preg_match('/^(RealEstate\d+)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 4: PropertyInfo1, PropertyData2, etc. (without dots)
        if (preg_match('/^(Property\w*\d+)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 5: Property_1_Address, Property_2_City, etc.
        if (preg_match('/^(Property_\d+)_/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 6: FirstProperty_Address, SecondProperty_City, etc.
        if (preg_match('/(\w*Property\w*)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 7: Address1, Address2 (when used for different properties)
        if (preg_match('/^(Address\d+)/', $cognitoField, $matches)) {
            return $matches[1];
        }

        // Pattern 8: PropertyAddress, PropertyAddress2, etc.
        if (preg_match('/^(PropertyAddress\d*)/', $cognitoField, $matches)) {
            return $matches[1] ?: 'PropertyAddress';
        }

        // Default: treat as single property group
        return 'default';
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
        $entityMappings = [];

        foreach ($this->saved as $cognitoField => $mapping) {
            if ($mapping['entity'] !== $entity->value) {
                continue;
            }

            $entityMappings[$cognitoField] = $mapping;

            if (! array_key_exists($cognitoField, $entry)
                || $entry[$cognitoField] === null
                || $entry[$cognitoField] === '') {
                continue;
            }

            $result[$mapping['field']] = $entry[$cognitoField];
        }

        // Log mapping activity for debugging
        if (!empty($entityMappings)) {
            \Log::info("NowCerts mapping for {$entity->value}", [
                'configured_mappings' => $entityMappings,
                'mapped_data' => $result,
                'available_entry_keys' => array_keys($entry),
            ]);
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
     * Extract file upload fields from a raw (non-flattened) Cognito entry.
     * File upload fields are list arrays whose items each contain a 'File' URL.
     *
     * Returns: [ ['field' => 'CurrentPolicyDeclarationPage', 'files' => [...items]] ]
     */
    public static function extractFileUploads(array $rawEntry): array
    {
        $uploads = [];

        foreach ($rawEntry as $key => $value) {
            if (! is_array($value) || ! array_is_list($value)) {
                continue;
            }

            $files = array_values(array_filter(
                $value,
                fn ($item) => is_array($item)
                    && ! empty($item['File'])
                    && filter_var($item['File'], FILTER_VALIDATE_URL),
            ));

            if (! empty($files)) {
                $uploads[] = ['field' => $key, 'files' => $files];
            }
        }

        return $uploads;
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
