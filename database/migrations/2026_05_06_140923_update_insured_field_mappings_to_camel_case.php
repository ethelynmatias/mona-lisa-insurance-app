<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FIELD_MAP = [
        'FirstName'                       => 'firstName',
        'LastName'                        => 'lastName',
        'MiddleName'                      => 'middleName',
        'CommercialName'                  => 'commercialName',
        'Dba'                             => 'dba',
        'Type'                            => 'type',
        'InsuredType'                     => 'insuredType',
        'EMail'                           => 'email',
        'EMail2'                          => 'email2',
        'EMail3'                          => 'email3',
        'Phone'                           => 'phoneNumber',
        'CellPhone'                       => 'cellPhone',
        'SmsPhone'                        => 'smsPhone',
        'Fax'                             => 'fax',
        'AddressLine1'                    => 'addressLine1',
        'AddressLine2'                    => 'addressLine2',
        'City'                            => 'city',
        'State'                           => 'state',
        'ZipCode'                         => 'zipCode',
        'DateOfBirth'                     => 'dateOfBirth',
        'Description'                     => 'description',
        'Active'                          => 'active',
        'Website'                         => 'website',
        'FEIN'                            => 'fein',
        'GreetingName'                    => 'greetingName',
        'PreferredLanguage'               => 'preferredLanguage',
        'Naic'                            => 'naic',
        'TypeOfBusiness'                  => 'typeOfBusiness',
        'SicCode'                         => 'sicCode',
        'YearBusinessStarted'             => 'yearBusinessStarted',
        'ProspectType'                    => 'prospectType',
        'CoInsured_FirstName'             => 'coInsured_FirstName',
        'CoInsured_LastName'              => 'coInsured_LastName',
        'CoInsured_MiddleName'            => 'coInsured_MiddleName',
        'CoInsured_DateOfBirth'           => 'coInsured_DateOfBirth',
        'CustomerId'                      => 'customerId',
        'InsuredId'                       => 'insuredId',
        'TagName'                         => 'tagName',
        'TagDescription'                  => 'tagDescription',
        'ReferralSourceCompanyName'       => 'referralSourceCompanyName',
        'PrimaryAgencyOfficeLocationName' => 'primaryAgencyOfficeLocationName',
    ];

    public function up(): void
    {
        foreach (self::FIELD_MAP as $old => $new) {
            DB::table('form_field_mappings')
                ->where('nowcerts_entity', 'Insured')
                ->where('nowcerts_field', $old)
                ->update(['nowcerts_field' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::FIELD_MAP as $old => $new) {
            DB::table('form_field_mappings')
                ->where('entity', 'Insured')
                ->where('field', $new)
                ->update(['field' => $old]);
        }
    }
};
