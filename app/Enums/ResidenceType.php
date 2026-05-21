<?php

namespace App\Enums;

enum ResidenceType: int
{
    case Apartment                    = 0;
    case Condo                        = 1;
    case CoOp                         = 2;
    case DwellingInsuredResidence     = 3;
    case MobileHome                   = 4;
    case Other                        = 5;
    case RowHouse                     = 6;
    case Townhouse                    = 7;
    case MixedUse                     = 8;

    public function label(): string
    {
        return match ($this) {
            self::Apartment                => 'Apartment',
            self::Condo                    => 'Condo',
            self::CoOp                     => 'Co-op',
            self::DwellingInsuredResidence => 'Dwelling-Insured Residence (non-farm)',
            self::MobileHome               => 'Mobile Home',
            self::Other                    => 'Other',
            self::RowHouse                 => 'Row House',
            self::Townhouse                => 'Townhouse',
            self::MixedUse                 => 'Mixed-Use',
        };
    }

    public static function fromLabel(string $label): ?self
    {
        $normalized = strtolower(trim($label));

        foreach (self::cases() as $case) {
            if (strtolower($case->label()) === $normalized) {
                return $case;
            }
        }

        return null;
    }
}
