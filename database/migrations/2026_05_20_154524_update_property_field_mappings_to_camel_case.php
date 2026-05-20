<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FIELD_MAP = [
        // Linkage
        'insured_database_id'             => 'insuredDatabaseId',
        'insured_email'                   => 'insuredEmail',
        'insured_first_name'              => 'insuredFirstName',
        'insured_last_name'               => 'insuredLastName',
        'insured_commercial_name'         => 'insuredCommercialName',
        'database_id'                     => 'databaseId',
        'policies_database_id'            => 'policiesDatabaseId',
        // Location
        'address_line_1'                  => 'addressLine1',
        'address_line_2'                  => 'addressLine2',
        'location_number'                 => 'locationNumber',
        'building_number'                 => 'buildingNumber',
        // Property Details
        'property_use'                    => 'propertyUse',
        'description_of_operations'       => 'descriptionOfOperations',
        'any_area_leased_to_others'       => 'anyAreaLeasedToOthers',
        'attach_to_policy_number'         => 'attachToPolicyNumber',
        // Coverage
        'coverage_property_type_cd'             => 'coverage_propertyTypeCd',
        'coverage_dwelling_a_limit'             => 'coverage_dwelling_A_limit',
        'coverage_dwelling_a_premium'           => 'coverage_dwelling_A_premium',
        'coverage_other_structures_b_limit'     => 'coverage_otherStructures_B_limit',
        'coverage_other_structures_b_premium'   => 'coverage_otherStructures_B_premium',
        'coverage_personal_property_c_limit'    => 'coverage_personalProperty_C_limit',
        'coverage_personal_property_c_premium'  => 'coverage_personalProperty_C_premium',
        'coverage_loss_of_use_d_limit'          => 'coverage_lossOfUse_D_limit',
        'coverage_loss_of_use_d_premium'        => 'coverage_lossOfUse_D_premium',
        'coverage_personal_liability_e_limit'   => 'coverage_personalLiability_E_limit',
        'coverage_personal_liability_e_premium' => 'coverage_personalLiability_E_premium',
        'coverage_medical_payments_f_limit'     => 'coverage_medicalPayments_F_limit',
        'coverage_medical_payments_f_premium'   => 'coverage_medicalPayments_F_premium',
        'coverage_all_other_perils_deductible'      => 'coverage_allOtherPerils_deductible',
        'coverage_all_other_perils_deductible_pct'  => 'coverage_allOtherPerils_deductiblePct',
        'coverage_hurricane_deductible_pct'         => 'coverage_hurricane_deductiblePct',
        'coverage_inc_ordinance_or_law_yes_no'      => 'coverage_incOrdinanceOrLaw_yesNo',
        'coverage_inc_ordinance_or_law_premium'     => 'coverage_incOrdinanceOrLaw_premium',
        // Additional (Business)
        'additional_number_of_full_time_employees'  => 'additional_numberOfFullTimeEmployees',
        'additional_number_of_part_time_employees'  => 'additional_numberOfPartTimeEmployees',
        'additional_annual_revenues'                => 'additional_annualRevenues',
        'additional_occupied_pct'                   => 'additional_occupiedPct',
        'additional_occupied_area'                  => 'additional_occupiedArea',
        'additional_open_to_public_area'            => 'additional_openToPublicArea',
        'additional_total_building_area'            => 'additional_totalBuildingArea',
        'additional_any_area_leased_to_others'      => 'additional_anyAreaLeasedToOthers',
        'additional_occupancy_desc'                 => 'additional_occupancyDesc',
        // Additional1 (Construction)
        'additional1_construction_cd'               => 'additional1_constructionCd',
        'additional1_year_built'                    => 'additional1_yearBuilt',
        'additional1_num_stories'                   => 'additional1_numStories',
        'additional1_roof_material_cd'              => 'additional1_roofMaterialCd',
        'additional1_residence_type_cd'             => 'additional1_residenceTypeCd',
        'additional1_dwell_use_cd'                  => 'additional1_dwellUseCd',
        'additional1_fire_protection_class_cd'      => 'additional1_fireProtectionClassCd',
        'additional1_distance_to_hydrant'           => 'additional1_distanceToHydrant',
        'additional1_air_conditioning_cd'           => 'additional1_airConditioningCd',
        'additional1_distance_to_fire_station'      => 'additional1_distanceToFireStation',
        // Additional2 (Structure)
        'additional2_dwell_style_cd'                => 'additional2_dwellStyleCd',
        'additional2_estimated_repl_cost_amt'       => 'additional2_estimatedReplCostAmt',
        'additional2_number_of_units'               => 'additional2_numberOfUnits',
        'additional2_heat_source_primary_cd'        => 'additional2_heatSourcePrimaryCd',
        'additional2_num_families'                  => 'additional2_numFamilies',
        'additional2_fireplace_info_num_hearths'    => 'additional2_fireplaceInfoNumHearths',
        'additional2_number_of_pools'               => 'additional2_numberOfPools',
        'additional2_fireplace_info_num_chimneys'   => 'additional2_fireplaceInfoNumChimneys',
        'additional2_garage_type_cd'                => 'additional2_garageTypeCd',
        'additional2_parking_area'                  => 'additional2_parkingArea',
        'additional2_garage_num_vehs'               => 'additional2_garageNumVehs',
        // Flood Information
        'flood_address_line_1'                        => 'propertyFloodInformation_addressLine1',
        'flood_address_line_2'                        => 'propertyFloodInformation_addressLine2',
        'flood_city'                                  => 'propertyFloodInformation_city',
        'flood_zip_code'                              => 'propertyFloodInformation_zipCode',
        'flood_state'                                 => 'propertyFloodInformation_state',
        'flood_build_year'                            => 'propertyFloodInformation_buildYear',
        'flood_flood_area'                            => 'propertyFloodInformation_floodArea',
        'flood_elevation_height'                      => 'propertyFloodInformation_elevationHeight',
        'flood_house_elevated_after_prior_flood_loss' => 'propertyFloodInformation_houseElevatedAfterPriorFloodLoss',
        'flood_dwelling_tiv'                          => 'propertyFloodInformation_dwellingTiv',
        'flood_personal_property_tiv'                 => 'propertyFloodInformation_personalPropertyTiv',
        'flood_buildings_limit'                       => 'propertyFloodInformation_buildingsLimit',
        'flood_contents_limit'                        => 'propertyFloodInformation_contentsLimit',
        'flood_no_of_stories'                         => 'propertyFloodInformation_noOfStories',
        'flood_building_over_water'                   => 'propertyFloodInformation_buildingOverWater',
        'flood_policy_type'                           => 'propertyFloodInformation_policyType',
        'flood_personal_property_cost_value_type'     => 'propertyFloodInformation_personalPropertyCostValueType',
        'flood_foundation_type'                       => 'propertyFloodInformation_foundationType',
        'flood_occupancy'                             => 'propertyFloodInformation_occupancy',
        'flood_construction'                          => 'propertyFloodInformation_construction',
    ];

    public function up(): void
    {
        foreach (self::FIELD_MAP as $old => $new) {
            DB::table('form_field_mappings')
                ->where('nowcerts_entity', 'Property')
                ->where('nowcerts_field', $old)
                ->update(['nowcerts_field' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::FIELD_MAP as $old => $new) {
            DB::table('form_field_mappings')
                ->where('nowcerts_entity', 'Property')
                ->where('nowcerts_field', $new)
                ->update(['nowcerts_field' => $old]);
        }
    }
};
