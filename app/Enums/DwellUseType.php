<?php

namespace App\Enums;

enum DwellUseType: int
{
    case PrimaryNonSeasonal   = 0;
    case PrimarySeasonal      = 1;
    case SecondaryNonSeasonal = 2;
    case SeasonalSecondary    = 3;
    case Other                = 4;
    case Farm                 = 5;

    public function label(): string
    {
        return match ($this) {
            self::PrimaryNonSeasonal   => 'Primary, (non-seasonal)',
            self::PrimarySeasonal      => 'Primary, Seasonal',
            self::SecondaryNonSeasonal => 'Secondary, (non-seasonal)',
            self::SeasonalSecondary    => 'Seasonal, (secondary)',
            self::Other                => 'Other',
            self::Farm                 => 'Farm',
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
