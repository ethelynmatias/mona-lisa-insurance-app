<?php

namespace App\Enums;

enum OccupancyType: int
{
    case Primary         = 0;
    case Secondary       = 1;
    case Seasonal        = 2;
    case Tenanted        = 3;
    case Vacant          = 4;
    case Coc             = 5;
    case VacantRenovation = 6;

    public function label(): string
    {
        return match ($this) {
            self::Primary          => 'Primary',
            self::Secondary        => 'Secondary',
            self::Seasonal         => 'Seasonal',
            self::Tenanted         => 'Tenanted',
            self::Vacant           => 'Vacant',
            self::Coc              => 'Coc',
            self::VacantRenovation => 'Vacant Renovation',
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
