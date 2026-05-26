<?php

namespace App\Enums;

enum ExteriorWallType: int
{
    case BrickVeneer = 0;
    case EIFS        = 1;
    case Frame       = 2;
    case Log         = 3;
    case Masonry     = 4;
    case Stucco      = 5;
    case Asbestos    = 6;

    public function label(): string
    {
        return match ($this) {
            self::BrickVeneer => 'Brick Veneer',
            self::EIFS        => 'EIFS',
            self::Frame       => 'Frame',
            self::Log         => 'Log',
            self::Masonry     => 'Masonry',
            self::Stucco      => 'Stucco',
            self::Asbestos    => 'Asbestos',
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
