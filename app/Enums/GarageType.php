<?php

namespace App\Enums;

enum GarageType: int
{
    case Attached                  = 0;
    case BuiltIn                   = 1;
    case Carport                   = 2;
    case Detached                  = 3;
    case Basement                  = 4;
    case SubterraneanUnderground   = 5;
    case FirstFloorSubterranean    = 6;
    case HabitationalOverGarage    = 7;
    case TuckUnder                 = 8;
    case OpenLot                   = 9;
    case Other                     = 10;

    public function label(): string
    {
        return match ($this) {
            self::Attached                => 'Attached',
            self::BuiltIn                 => 'Built-In',
            self::Carport                 => 'Carport',
            self::Detached                => 'Detached',
            self::Basement                => 'Basement',
            self::SubterraneanUnderground => 'Subterranean / Underground',
            self::FirstFloorSubterranean  => '1st floor Subterranean style',
            self::HabitationalOverGarage  => 'Habitational over garage',
            self::TuckUnder               => 'Tuck Under',
            self::OpenLot                 => 'Open Lot',
            self::Other                   => 'Other',
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
