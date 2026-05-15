<?php

namespace App\Enums;

enum NowCertsEntity: string
{
    case Insured                 = 'Insured';
    case Policy                  = 'Policy';
    case Driver                  = 'Driver';
    case Vehicle                 = 'Vehicle';
    case Property                = 'Property';
    case Contact                 = 'Contact';
    case InsuredLocation         = 'InsuredLocation';
    case GeneralLiabilityNotice  = 'GeneralLiabilityNotice';
    case PolicyCoverage          = 'PolicyCoverage';
    case InsuredPolicies         = 'InsuredPolicies';
    case VehicleCoverage         = 'VehicleCoverage';
    case Claim                   = 'Claim';
    case PropertyLossClaim       = 'PropertyLossClaim';
    case WorkerCompensationClaim = 'WorkerCompensationClaim';
    case Opportunity             = 'Opportunity';

    public function fields(): array
    {
        return match ($this) {
            self::Insured => [
                // Identity
                'first_name', 'last_name', 'middle_name', 'commercial_name', 'dba',
                'type', 'insured_type',
                // Contact
                'email', 'email2', 'email3',
                'phone_number', 'cell_phone', 'sms_phone', 'fax',
                // Address
                'address_line_1', 'address_line_2', 'city', 'state', 'zip_code',
                // Profile
                'date_of_birth', 'description', 'active', 'website', 'fein',
                'greeting_name', 'preferred_language', 'naic',
                'type_of_business', 'sic_code', 'year_business_started',
                'prospect_type',
                // Co-insured
                'co_insured_first_name', 'co_insured_last_name', 'co_insured_middle_name',
                'co_insured_date_of_birth',
                // Agency / CRM
                'customer_id', 'insured_id',
                'tag_name', 'tag_description',
                'referral_source_company_name',
                'primary_agency_office_location_name',
            ],

            self::Policy => [
                'Number', 'EffectiveDate', 'ExpirationDate', 'BindDate',
                'BusinessType', 'Description', 'BillingType',
                'LineOfBusinessName', 'CarrierName', 'MgaName',
                'Premium', 'AgencyCommissionPercent',
                'InsuredDatabaseId', 'InsuredEmail', 'InsuredFirstName', 'InsuredLastName',
            ],

            self::Driver => [
                // Identity
                'first_name', 'last_name', 'middle_name',
                // Personal
                'date_of_birth', 'gender', 'marital_status',
                'email', 'phone',
                // License
                'license_number', 'license_state', 'license_year',
                'driver_license_class', 'driver_license_status_code',
                // Address
                'address_line_1', 'address_line_2', 'city', 'driver_address_state', 'zip_code',
                // Status
                'active', 'excluded',
                // Linkage
                'insured_database_id', 'insured_email',
                'insured_first_name', 'insured_last_name', 'insured_commercial_name',
                'policy_database_id',
            ],

            self::Vehicle => [
                'year', 'make', 'model', 'vin',
                'type', 'type_of_use', 'description', 'value',
                'estimated_annual_distance',
                'deductible_comprehensive', 'deductible_collision',
                'insured_database_id', 'insured_email',
                'insured_first_name', 'insured_last_name', 'insured_commercial_name',
                'policy_database_id',
            ],

            self::Contact => [
                // Identity
                'database_id', 'first_name', 'middle_name', 'last_name', 'description', 'type',
                // Contact Information
                'personal_email', 'business_email',
                'home_phone', 'office_phone', 'cell_phone',
                'personal_fax', 'business_fax',
                // Personal Details
                'ssn', 'birthday', 'marital_status', 'gender',
                // Driver Information
                'is_driver', 'dl_number', 'dl_state',
                // Linkage
                'insured_database_id', 'insured_email',
                'insured_first_name', 'insured_last_name', 'insured_commercial_name',
                // Flags
                'match_record_base_on_name', 'is_primary',
            ],

            self::GeneralLiabilityNotice => [
                // Occurrence Details
                'description_of_occurrence', 'date_of_loss', 'describe_location',
                'description_of_loss', 'additional_comments',
                // Claim Information
                'database_id', 'claim_number', 'status',
                // Location
                'street', 'city', 'state', 'zip', 'county',
                // Reporting
                'police_or_fire', 'report_number',
                // Insured Information
                'insured_database_id', 'insured_email', 'insured_first_name',
                'insured_last_name', 'insured_commercial_name',
                // Policy
                'policy_number',
            ],

            self::PolicyCoverage => [
                // Policy Identifiers
                'policyDatabaseId', 'lineOfBusinessDatabaseId',
                // Cargo Coverage
                'cargo_deductible', 'cargo_limit', 'cargo_commodities',
                // Physical Damage
                'physicalDamage_comprehensiveType', 'physicalDamage_comprehensiveCoverage', 'physicalDamage_collisionLimit', 'physicalDamage_doNotPrefillCertificate',
                // General Liability
                'generalLiability_commercialGeneralLiability', 'generalLiability_claimsMade', 'generalLiability_occur', 'generalLiability_otherCheckbox', 'generalLiability_otherText',
                'generalLiability_policy', 'generalLiability_project', 'generalLiability_loc', 'generalLiability_limitEachOccurrence', 'generalLiability_limitDamageToRentedPremises',
                'generalLiability_limitMedExp', 'generalLiability_limitPersonalAndAdvInjury', 'generalLiability_limitGeneralAggregate', 'generalLiability_limitProductsCompOpAggregate',
                'generalLiability_limitOtherText', 'generalLiability_limitOtherLimit', 'generalLiability_generalAggrLimitApplOther', 'generalLiability_generalAggrLimitApplOtherText',
                'generalLiability_other2Checkbox', 'generalLiability_other2Text',
                // Auto Mobile Liability
                'autoMobileLiability_anyAuto', 'autoMobileLiability_allOwnedAutos', 'autoMobileLiability_scheduledAutos', 'autoMobileLiability_hiredAutos', 'autoMobileLiability_nonOwnedAutos',
                'autoMobileLiability_otherText1', 'autoMobileLiability_otherCheckbox1', 'autoMobileLiability_otherText2', 'autoMobileLiability_otherCheckbox2',
                'autoMobileLiability_limitCombinedSingle', 'autoMobileLiability_limitBodilyInjuryPerPerson', 'autoMobileLiability_limitBodilyInjuryPerAccident',
                'autoMobileLiability_limitPropertyDamage', 'autoMobileLiability_limitOtherText', 'autoMobileLiability_limitOtherLimit',
                // Flood Coverage Primary
                'floodCoveragePrimary_buildingDeductibleAmount', 'floodCoveragePrimary_buildingBasicLimitAmount', 'floodCoveragePrimary_contentsDeductibleAmount', 'floodCoveragePrimary_contentsBasicLimitAmount',
                'floodCoveragePrimary_nfipwyoIndicator', 'floodCoveragePrimary_privateMarketIndicator', 'floodCoveragePrimary_policyBroadLineOfBusinessDwellingIndicator',
                'floodCoveragePrimary_policyBroadLineOfBusinessGeneralPropertyFormIndicator', 'floodCoveragePrimary_policyBroadLineOfBusinessResidentialCondominiumAssociationPolicyIndicator',
                'floodCoveragePrimary_policyTypeStandardIndicator', 'floodCoveragePrimary_policyTypePreferredRiskIndicator', 'floodCoveragePrimary_policyBroadLineOfBusinessOtherIndicator',
                'floodCoveragePrimary_policyBroadLineOfBusinessOtherDescription', 'floodCoveragePrimary_policyTypePreferredRiskEligibilityExtensionIndicator', 'floodCoveragePrimary_policyTypeGroupFloodIndicator',
                'floodCoveragePrimary_policyBroadLineOfBusinessMortgagePortfolioProtectionProgramIndicator',
                // Flood Coverage Excess
                'floodCoverageExcess_buildingDeductibleAmount', 'floodCoverageExcess_buildingBasicLimitAmount', 'floodCoverageExcess_contentsDeductibleAmount', 'floodCoverageExcess_contentsBasicLimitAmount',
                'floodCoverageExcess_nfipwyoIndicator', 'floodCoverageExcess_privateMarketIndicator', 'floodCoverageExcess_businessIncomeIndicator', 'floodCoverageExcess_extraExpenseIndicator',
                'floodCoverageExcess_additionalLivingExpenseIndicator', 'floodCoverageExcess_additionalLivingExpenseLimitAmount', 'floodCoverageExcess_lossSustainedIndicator',
                'floodCoverageExcess_lossSustainedNumberOfMonthsCount', 'floodCoverageExcess_policyTypeExcessFollowingFormIndicator',
                // Worker Compensation
                'workerCompensation_memberExcluded', 'workerCompensation_limitWCStatLimits', 'workerCompensation_limitOtherCheckbox', 'workerCompensation_limitOtherValue',
                'workerCompensation_limitEachAccident', 'workerCompensation_limitEAEmployee', 'workerCompensation_limitPolicy',
                // Other coverages
                'other_description', 'other_limit', 'other2_description', 'other2_limit', 'other3_description', 'other3_limit', 'other4_description', 'other4_limit',
                // Home Owner Coverage
                'homeOwnerCoverage_formType', 'homeOwnerCoverage_dwellingLimit', 'homeOwnerCoverage_dwellingPremiumAmount', 'homeOwnerCoverage_otherStructureLimit',
                'homeOwnerCoverage_otherStructurePremiumAmount', 'homeOwnerCoverage_personalPropertyLimit', 'homeOwnerCoverage_personalPropertyPremiumAmount',
                'homeOwnerCoverage_lossOfUseLimit', 'homeOwnerCoverage_lossOfUsePremiumAmount', 'homeOwnerCoverage_personalLiabilityLimit', 'homeOwnerCoverage_personalLiabilityPremiumAmount',
                'homeOwnerCoverage_medicalPaymentsLimit', 'homeOwnerCoverage_medicalPaymentsPremiumAmount', 'homeOwnerCoverage_hurricaneDeductible', 'homeOwnerCoverage_hurricanePremiumAmount',
                'homeOwnerCoverage_windHailDeductible', 'homeOwnerCoverage_ordinanceOrLaw', 'homeOwnerCoverage_allOtherPerilsDeductible',
                // ACORD 27 fields
                'acord27_priorEvidenceDate', 'acord27_propertyDescription', 'acord27_coverageDescriptionFirst', 'acord27_limitAmountFirst', 'acord27_deductibleAmountFirst',
                'acord27_coverageDescriptionSecond', 'acord27_limitAmountSecond', 'acord27_deductibleAmountSecond', 'acord27_coverageDescriptionThird', 'acord27_limitAmountThird',
                'acord27_deductibleAmountThird', 'acord27_coverageDescriptionFourth', 'acord27_limitAmountFourth', 'acord27_deductibleAmountFourth',
                // Custom Coverages
                'customCoverages_description', 'customCoverages_benefit', 'customCoverages_deductible',
            ],

            self::InsuredPolicies => [
                // Root Insured Fields
                'databaseId', 'commercialName', 'firstName', 'lastName', 'middleName', 'dba',
                'type', 'addressLine1', 'addressLine2', 'state', 'city', 'zipCode',
                'eMail', 'eMail2', 'eMail3', 'fax', 'phone', 'cellPhone', 'smsPhone',
                'description', 'active', 'website', 'fein', 'customerId', 'insuredId',
                'insuredType', 'dateOfBirth', 'greetingName', 'typeOfBusiness',
                'preferredLanguage', 'sicCode', 'yearBusinessStarted', 'naic',
                // Co-insured
                'coInsured_DateOfBirth', 'coInsured_FirstName', 'coInsured_LastName', 'coInsured_MiddleName',
                // Agency/Office
                'primaryAgencyOfficeLocationName', 'primaryAgencyOfficeId', 'referralSourceCompanyName',
                'referralSourceCompanyId', 'prospectStatusId', 'prospectType', 'origin',
                'overRideUserRequestValue', 'tagName', 'tagDescription',
                // Custom Fields & Associations
                'customFieldsSimple', 'customFields', 'agents', 'csRs', 'leadSources', 'xDatesAndLinesOfBusiness',
                // Policy Fields
                'policies', 'policies.insuredDatabaseId', 'policies.insuredEmail', 'policies.insuredFirstName', 'policies.insuredLastName',
                'policies.databaseId', 'policies.number', 'policies.effectiveDate', 'policies.expirationDate',
                'policies.bindDate', 'policies.businessType', 'policies.businessSubType', 'policies.description',
                'policies.billingType', 'policies.insuredName', 'policies.lineOfBusinessName',
                'policies.carrierName', 'policies.mgaName', 'policies.premium', 'policies.agencyCommissionPercent',
                'policies.agencyCommissionValue', 'policies.agencyFee', 'policies.taxes', 'policies.binderId',
                'policies.status', 'policies.statusChangeDate', 'policies.manualStatus', 'policies.cancellationDate',
                'policies.policyFee', 'policies.policyURL', 'policies.productName', 'policies.oldPremium',
                'policies.oldAgencyCommissionValue', 'policies.oldAgencyFee', 'policies.oldTaxes',
                'policies.deleteOtherLob', 'policies.primaryOfficeDatabaseId', 'policies.referralSourceCompanyName',
                'policies.deleteBasePremiumFeesAndTaxes', 'policies.deleteExistingAgents', 'policies.parentPolicyId',
                'policies.isPolicyRenewal', 'policies.checkPolicyOnNumber', 'policies.billingCompanyDatabaseId',
                'policies.lineOfBusinessNames', 'policies.mortgageBilled', 'policies.blanketAI', 'policies.blanketWS',
                'policies.autoRenew', 'policies.premiumSent', 'policies.financeCompanyName', 'policies.leadSources',
                'policies.packageName', 'policies.overRideUserRequestValue', 'policies.stateAbbreviationCode',
                'policies.agents', 'policies.csRs',
                // Quote Fields
                'quotes', 'quotes.insuredDatabaseId', 'quotes.insuredEmail', 'quotes.insuredFirstName', 'quotes.insuredLastName',
                'quotes.databaseId', 'quotes.number', 'quotes.effectiveDate', 'quotes.expirationDate',
                'quotes.bindDate', 'quotes.businessType', 'quotes.businessSubType', 'quotes.description',
                'quotes.billingType', 'quotes.insuredName', 'quotes.lineOfBusinessName', 'quotes.carrierName',
                'quotes.mgaName', 'quotes.premium', 'quotes.agencyCommissionPercent', 'quotes.agencyCommissionValue',
                'quotes.agencyFee', 'quotes.taxes', 'quotes.binderId', 'quotes.status', 'quotes.statusChangeDate',
                'quotes.manualStatus', 'quotes.cancellationDate', 'quotes.policyFee', 'quotes.policyURL',
                'quotes.productName', 'quotes.oldPremium', 'quotes.oldAgencyCommissionValue', 'quotes.oldAgencyFee',
                'quotes.oldTaxes', 'quotes.deleteOtherLob', 'quotes.primaryOfficeDatabaseId',
                'quotes.referralSourceCompanyName', 'quotes.deleteBasePremiumFeesAndTaxes', 'quotes.deleteExistingAgents',
                'quotes.parentPolicyId', 'quotes.isPolicyRenewal', 'quotes.checkPolicyOnNumber',
                'quotes.billingCompanyDatabaseId', 'quotes.lineOfBusinessNames', 'quotes.mortgageBilled',
                'quotes.blanketAI', 'quotes.blanketWS', 'quotes.autoRenew', 'quotes.premiumSent',
                'quotes.financeCompanyName', 'quotes.leadSources', 'quotes.packageName',
                'quotes.overRideUserRequestValue', 'quotes.stateAbbreviationCode', 'quotes.agents', 'quotes.csRs',
            ],

            self::VehicleCoverage => [
                // Insured Information
                'insured_database_id', 'insured_email', 'insured_first_name', 'insured_last_name', 'insured_commercial_name',
                // Vehicle Information
                'vehicle_database_id',
                // Liability Coverage
                'bodily_injury_limit', 'bodily_injury_premium',
                'property_damage_limit', 'property_damage_premium',
                // Uninsured/Underinsured Motorist Coverage
                'umbi_limit', 'umbi_premium',
                'uimbi_limit', 'uimbi_premium',
                'umpd_limit', 'umpd_premium',
                // Medical Coverage
                'medical_payments_limit', 'medical_payments_premium',
                'pip_limit', 'pip_premium',
                'accidental_death_limit', 'accidental_death_premium',
                // Physical Damage Coverage
                'comprehensive_limit', 'comprehensive_premium',
                'collision_limit', 'collision_premium',
                // Additional Coverage
                'rental_limit', 'rental_premium',
                'towing_labor_limit', 'towing_labor_premium',
                'custom_equipment_limit', 'custom_equipment_premium',
                'full_glass_limit', 'full_glass_premium',
                'gap_limit', 'gap_premium',
            ],

            self::Claim => [
                // Claim Details
                'database_id', 'claim_number', 'status',
                'date_amount',
                // Location Information
                'street', 'city', 'state', 'zip', 'county',
                // Loss Details
                'date_of_loss', 'describe_location', 'description_of_loss',
                // Report Information
                'police_or_fire', 'report_number', 'additional_comments',
                // Insured Information
                'insured_database_id', 'insured_email', 'insured_first_name', 'insured_last_name', 'insured_commercial_name',
                // Policy Information
                'policy_number',
            ],

            self::PropertyLossClaim => [
                // Loss Type Flags
                'fire', 'theft', 'lightning', 'hail', 'flood', 'wind', 'other',
                'other_description',
                // Claim Details
                'probable_amount_entire_loss', 'reprorted_by', 'reported_to',
                'database_id', 'claim_number', 'status',
                // Location Information
                'street', 'city', 'state', 'zip', 'county',
                // Loss Details
                'date_of_loss', 'describe_location', 'description_of_loss',
                // Report Information
                'police_or_fire', 'report_number', 'additional_comments',
                // Insured Information
                'insured_database_id', 'insured_email', 'insured_first_name', 'insured_last_name', 'insured_commercial_name',
                // Policy Information
                'policy_number',
            ],

            self::WorkerCompensationClaim => [
                // Employee Information
                'name_of_injured_employee', 'time_employee_began_work',
                // Injury Details
                'date_of_injury_illness', 'time_of_occurrence_cannot_be_determined', 'time_of_occurrence',
                'last_work_date', 'date_employer_notified', 'date_disability_began',
                // Contact Information
                'contact_name', 'contact_phone', 'contact_id',
                // Injury Specifics
                'type_of_injury_illness', 'part_of_body_affected', 'type_of_injury_illness_code', 'part_of_body_affected_code',
                // Incident Details
                'equipment_materials_or_chemicals_employee_was_using', 'activity_employee_was_engaged',
                'work_process_employee_was_engaged', 'how_injury_or_illness_occurred', 'cause_of_injury_code',
                // Recovery/Death Information
                'date_return_to_work', 'date_of_death',
                // Safety Information
                'were_safeguards_or_safety_equipment_provided', 'were_they_used', 'injury_occured_on_employers_premises',
                // Base Claim Fields
                'database_id', 'claim_number', 'status',
                // Location Information
                'street', 'city', 'state', 'zip', 'county',
                // Loss Details
                'date_of_loss', 'describe_location', 'description_of_loss',
                // Report Information
                'police_or_fire', 'report_number', 'additional_comments',
                // Insured Information
                'insured_database_id', 'insured_email', 'insured_first_name', 'insured_last_name', 'insured_commercial_name',
                // Policy Information
                'policy_number',
            ],

            self::Opportunity => [
                // Opportunity Details
                'line_of_business_name', 'needed_by', 'opportunity_stage_name',
                'win_probability', 'agency_commission', 'description',
                'created_from_renewal',
                // Assignment
                'assigned_to',
                // Insured Information
                'insured_first_name', 'insured_last_name', 'insured_email',
                // Policy
                'policy_numbers',
            ],

            default => [],
        };
    }
}
