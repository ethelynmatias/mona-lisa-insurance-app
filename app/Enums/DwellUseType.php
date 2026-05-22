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

        // 1. Exact label match
        foreach (self::cases() as $case) {
            if (strtolower($case->label()) === $normalized) {
                return $case;
            }
        }

        // 2. Word-by-word: check if any case name contains any input word
        $words = array_filter(
            preg_split('/\W+/', $normalized, -1, PREG_SPLIT_NO_EMPTY),
            fn ($w) => strlen($w) > 2,
        );

        foreach (self::cases() as $case) {
            foreach ($words as $word) {
                if (str_contains(strtolower($case->name), $word)) {
                    return $case;
                }
            }
        }

        return null;
    }

}
