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
                'address_line_1', 'address_line_2', 'city', 'state', 'zip_code', 'country',
                // Profile
                'date_of_birth', 'description', 'active', 'website', 'fein', 'ssn_tax_id',
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
                'number', 'effective_date', 'expiration_date', 'bind_date',
                'business_type', 'description', 'billing_type',
                'line_of_business_name', 'carrier_name', 'mga_name',
                'premium', 'agency_commission_percent',
                'insured_database_id', 'insured_email', 'insured_first_name', 'insured_last_name',
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
                'policy_database_id', 'line_of_business_database_id',
                // Cargo Coverage
                'cargo_deductible', 'cargo_limit', 'cargo_commodities',
                // Physical Damage
                'physical_damage_comprehensive_type', 'physical_damage_comprehensive_coverage',
                'physical_damage_collision_limit', 'physical_damage_do_not_prefill_certificate',
                // General Liability
                'general_liability_commercial_general_liability', 'general_liability_claims_made',
                'general_liability_occur', 'general_liability_other_checkbox', 'general_liability_other_text',
                'general_liability_policy', 'general_liability_project', 'general_liability_loc',
                'general_liability_limit_each_occurrence', 'general_liability_limit_damage_to_rented_premises',
                'general_liability_limit_med_exp', 'general_liability_limit_personal_and_adv_injury',
                'general_liability_limit_general_aggregate', 'general_liability_limit_products_comp_op_aggregate',
                'general_liability_limit_other_text', 'general_liability_limit_other_limit',
                'general_liability_general_aggr_limit_appl_other', 'general_liability_general_aggr_limit_appl_other_text',
                'general_liability_other2_checkbox', 'general_liability_other2_text',
                // Auto Mobile Liability
                'auto_mobile_liability_any_auto', 'auto_mobile_liability_all_owned_autos',
                'auto_mobile_liability_scheduled_autos', 'auto_mobile_liability_hired_autos',
                'auto_mobile_liability_non_owned_autos',
                'auto_mobile_liability_other_text1', 'auto_mobile_liability_other_checkbox1',
                'auto_mobile_liability_other_text2', 'auto_mobile_liability_other_checkbox2',
                'auto_mobile_liability_limit_combined_single', 'auto_mobile_liability_limit_bodily_injury_per_person',
                'auto_mobile_liability_limit_bodily_injury_per_accident', 'auto_mobile_liability_limit_property_damage',
                'auto_mobile_liability_limit_other_text', 'auto_mobile_liability_limit_other_limit',
                // Flood Coverage Primary
                'flood_coverage_primary_building_deductible_amount', 'flood_coverage_primary_building_basic_limit_amount',
                'flood_coverage_primary_contents_deductible_amount', 'flood_coverage_primary_contents_basic_limit_amount',
                'flood_coverage_primary_nfipwyo_indicator', 'flood_coverage_primary_private_market_indicator',
                'flood_coverage_primary_policy_broad_line_of_business_dwelling_indicator',
                'flood_coverage_primary_policy_broad_line_of_business_general_property_form_indicator',
                'flood_coverage_primary_policy_broad_line_of_business_residential_condominium_association_policy_indicator',
                'flood_coverage_primary_policy_type_standard_indicator',
                'flood_coverage_primary_policy_type_preferred_risk_indicator',
                'flood_coverage_primary_policy_broad_line_of_business_other_indicator',
                'flood_coverage_primary_policy_broad_line_of_business_other_description',
                'flood_coverage_primary_policy_type_preferred_risk_eligibility_extension_indicator',
                'flood_coverage_primary_policy_type_group_flood_indicator',
                'flood_coverage_primary_policy_broad_line_of_business_mortgage_portfolio_protection_program_indicator',
                // Flood Coverage Excess
                'flood_coverage_excess_building_deductible_amount', 'flood_coverage_excess_building_basic_limit_amount',
                'flood_coverage_excess_contents_deductible_amount', 'flood_coverage_excess_contents_basic_limit_amount',
                'flood_coverage_excess_nfipwyo_indicator', 'flood_coverage_excess_private_market_indicator',
                'flood_coverage_excess_business_income_indicator', 'flood_coverage_excess_extra_expense_indicator',
                'flood_coverage_excess_additional_living_expense_indicator', 'flood_coverage_excess_additional_living_expense_limit_amount',
                'flood_coverage_excess_loss_sustained_indicator', 'flood_coverage_excess_loss_sustained_number_of_months_count',
                'flood_coverage_excess_policy_type_excess_following_form_indicator',
                // Worker Compensation
                'worker_compensation_member_excluded', 'worker_compensation_limit_wc_stat_limits',
                'worker_compensation_limit_other_checkbox', 'worker_compensation_limit_other_value',
                'worker_compensation_limit_each_accident', 'worker_compensation_limit_ea_employee',
                'worker_compensation_limit_policy',
                // Other coverages
                'other_description', 'other_limit',
                'other2_description', 'other2_limit',
                'other3_description', 'other3_limit',
                'other4_description', 'other4_limit',
                // Home Owner Coverage
                'home_owner_coverage_form_type', 'home_owner_coverage_dwelling_limit',
                'home_owner_coverage_dwelling_premium_amount', 'home_owner_coverage_other_structure_limit',
                'home_owner_coverage_other_structure_premium_amount', 'home_owner_coverage_personal_property_limit',
                'home_owner_coverage_personal_property_premium_amount', 'home_owner_coverage_loss_of_use_limit',
                'home_owner_coverage_loss_of_use_premium_amount', 'home_owner_coverage_personal_liability_limit',
                'home_owner_coverage_personal_liability_premium_amount', 'home_owner_coverage_medical_payments_limit',
                'home_owner_coverage_medical_payments_premium_amount', 'home_owner_coverage_hurricane_deductible',
                'home_owner_coverage_hurricane_premium_amount', 'home_owner_coverage_wind_hail_deductible',
                'home_owner_coverage_ordinance_or_law', 'home_owner_coverage_all_other_perils_deductible',
                // ACORD 27 fields
                'acord27_prior_evidence_date', 'acord27_property_description',
                'acord27_coverage_description_first', 'acord27_limit_amount_first', 'acord27_deductible_amount_first',
                'acord27_coverage_description_second', 'acord27_limit_amount_second', 'acord27_deductible_amount_second',
                'acord27_coverage_description_third', 'acord27_limit_amount_third', 'acord27_deductible_amount_third',
                'acord27_coverage_description_fourth', 'acord27_limit_amount_fourth', 'acord27_deductible_amount_fourth',
                // Custom Coverages
                'custom_coverages_description', 'custom_coverages_benefit', 'custom_coverages_deductible',
            ],

            self::InsuredPolicies => [
                // Root Insured Fields
                'database_id', 'commercial_name', 'first_name', 'last_name', 'middle_name', 'dba',
                'type', 'address_line1', 'address_line2', 'state', 'city', 'zip_code',
                'email', 'email2', 'email3', 'fax', 'phone', 'cell_phone', 'sms_phone',
                'description', 'active', 'website', 'fein', 'customer_id', 'insured_id',
                'insured_type', 'date_of_birth', 'greeting_name', 'type_of_business',
                'preferred_language', 'sic_code', 'year_business_started', 'naic',
                // Co-insured
                'co_insured_date_of_birth', 'co_insured_first_name', 'co_insured_last_name', 'co_insured_middle_name',
                // Agency/Office
                'primary_agency_office_location_name', 'primary_agency_office_id',
                'referral_source_company_name', 'referral_source_company_id',
                'prospect_status_id', 'prospect_type', 'origin',
                'over_ride_user_request_value', 'tag_name', 'tag_description',
                // Custom Fields & Associations
                'custom_fields_simple', 'custom_fields', 'agents', 'cs_rs', 'lead_sources', 'x_dates_and_lines_of_business',
                // Policy Fields
                'policies', 'policies.insured_database_id', 'policies.insured_email',
                'policies.insured_first_name', 'policies.insured_last_name',
                'policies.database_id', 'policies.number', 'policies.effective_date', 'policies.expiration_date',
                'policies.bind_date', 'policies.business_type', 'policies.business_sub_type', 'policies.description',
                'policies.billing_type', 'policies.insured_name', 'policies.line_of_business_name',
                'policies.carrier_name', 'policies.mga_name', 'policies.premium', 'policies.agency_commission_percent',
                'policies.agency_commission_value', 'policies.agency_fee', 'policies.taxes', 'policies.binder_id',
                'policies.status', 'policies.status_change_date', 'policies.manual_status', 'policies.cancellation_date',
                'policies.policy_fee', 'policies.policy_url', 'policies.product_name', 'policies.old_premium',
                'policies.old_agency_commission_value', 'policies.old_agency_fee', 'policies.old_taxes',
                'policies.delete_other_lob', 'policies.primary_office_database_id', 'policies.referral_source_company_name',
                'policies.delete_base_premium_fees_and_taxes', 'policies.delete_existing_agents', 'policies.parent_policy_id',
                'policies.is_policy_renewal', 'policies.check_policy_on_number', 'policies.billing_company_database_id',
                'policies.line_of_business_names', 'policies.mortgage_billed', 'policies.blanket_ai', 'policies.blanket_ws',
                'policies.auto_renew', 'policies.premium_sent', 'policies.finance_company_name', 'policies.lead_sources',
                'policies.package_name', 'policies.over_ride_user_request_value', 'policies.state_abbreviation_code',
                'policies.agents', 'policies.cs_rs',
                // Quote Fields
                'quotes', 'quotes.insured_database_id', 'quotes.insured_email',
                'quotes.insured_first_name', 'quotes.insured_last_name',
                'quotes.database_id', 'quotes.number', 'quotes.effective_date', 'quotes.expiration_date',
                'quotes.bind_date', 'quotes.business_type', 'quotes.business_sub_type', 'quotes.description',
                'quotes.billing_type', 'quotes.insured_name', 'quotes.line_of_business_name', 'quotes.carrier_name',
                'quotes.mga_name', 'quotes.premium', 'quotes.agency_commission_percent', 'quotes.agency_commission_value',
                'quotes.agency_fee', 'quotes.taxes', 'quotes.binder_id', 'quotes.status', 'quotes.status_change_date',
                'quotes.manual_status', 'quotes.cancellation_date', 'quotes.policy_fee', 'quotes.policy_url',
                'quotes.product_name', 'quotes.old_premium', 'quotes.old_agency_commission_value', 'quotes.old_agency_fee',
                'quotes.old_taxes', 'quotes.delete_other_lob', 'quotes.primary_office_database_id',
                'quotes.referral_source_company_name', 'quotes.delete_base_premium_fees_and_taxes',
                'quotes.delete_existing_agents', 'quotes.parent_policy_id', 'quotes.is_policy_renewal',
                'quotes.check_policy_on_number', 'quotes.billing_company_database_id', 'quotes.line_of_business_names',
                'quotes.mortgage_billed', 'quotes.blanket_ai', 'quotes.blanket_ws', 'quotes.auto_renew',
                'quotes.premium_sent', 'quotes.finance_company_name', 'quotes.lead_sources', 'quotes.package_name',
                'quotes.over_ride_user_request_value', 'quotes.state_abbreviation_code', 'quotes.agents', 'quotes.cs_rs',
            ],

            self::Property => [
                // Linkage
                'insured_database_id', 'insured_email',
                'insured_first_name', 'insured_last_name', 'insured_commercial_name',
                'database_id', 'policies_database_id',
                // Location
                'address_line_1', 'address_line_2', 'city', 'county', 'state', 'zip',
                'location_number', 'building_number',
                // Property Details
                'property_use', 'description', 'description_of_operations',
                'any_area_leased_to_others', 'attach_to_policy_number',
                // Coverage
                'coverage_property_type_cd',
                'coverage_dwelling_a_limit', 'coverage_dwelling_a_premium',
                'coverage_other_structures_b_limit', 'coverage_other_structures_b_premium',
                'coverage_personal_property_c_limit', 'coverage_personal_property_c_premium',
                'coverage_loss_of_use_d_limit', 'coverage_loss_of_use_d_premium',
                'coverage_personal_liability_e_limit', 'coverage_personal_liability_e_premium',
                'coverage_medical_payments_f_limit', 'coverage_medical_payments_f_premium',
                'coverage_all_other_perils_deductible', 'coverage_all_other_perils_deductible_pct',
                'coverage_hurricane_premium', 'coverage_hurricane_deductible', 'coverage_hurricane_deductible_pct',
                'coverage_inc_ordinance_or_law_yes_no', 'coverage_inc_ordinance_or_law_premium',
                // Additional (Business)
                'additional_number_of_full_time_employees', 'additional_number_of_part_time_employees',
                'additional_annual_revenues', 'additional_occupied_pct', 'additional_occupied_area',
                'additional_open_to_public_area', 'additional_total_building_area',
                'additional_any_area_leased_to_others', 'additional_occupancy_desc',
                // Additional1 (Construction)
                'additional1_construction_cd', 'additional1_year_built', 'additional1_num_stories',
                'additional1_roof_material_cd', 'additional1_residence_type_cd', 'additional1_dwell_use_cd',
                'additional1_fire_protection_class_cd', 'additional1_distance_to_hydrant',
                'additional1_air_conditioning_cd', 'additional1_distance_to_fire_station',
                // Additional2 (Structure)
                'additional2_dwell_style_cd', 'additional2_estimated_repl_cost_amt',
                'additional2_number_of_units', 'additional2_heat_source_primary_cd',
                'additional2_num_families', 'additional2_fireplace_info_num_hearths',
                'additional2_number_of_pools', 'additional2_fireplace_info_num_chimneys',
                'additional2_garage_type_cd', 'additional2_parking_area', 'additional2_garage_num_vehs',
                // Flood Information
                'flood_address_line_1', 'flood_address_line_2', 'flood_city', 'flood_zip_code', 'flood_state',
                'flood_build_year', 'flood_flood_area', 'flood_elevation_height',
                'flood_house_elevated_after_prior_flood_loss',
                'flood_dwelling_tiv', 'flood_personal_property_tiv',
                'flood_buildings_limit', 'flood_contents_limit', 'flood_no_of_stories',
                'flood_building_over_water', 'flood_policy_type',
                'flood_personal_property_cost_value_type', 'flood_foundation_type',
                'flood_occupancy', 'flood_construction',
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
