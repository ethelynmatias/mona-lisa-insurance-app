<?php

namespace App\Enums;

enum FoundationType: int
{
    case PiersPostsPiles        = 0;
    case ReinforcedMasonryEtc   = 1;
    case ReinforcedShearWalls   = 2;
    case SolidFoundationWalls   = 3;
    case FoundationWall         = 4;
    case SlabOnGrade            = 5;
    case SlabOnFill             = 6;

    public function label(): string
    {
        return match ($this) {
            self::PiersPostsPiles       => 'Piers Posts Piles',
            self::ReinforcedMasonryEtc  => 'Reinforced Masonry Etc',
            self::ReinforcedShearWalls  => 'Rein forced Shear Walls',
            self::SolidFoundationWalls  => 'Solid Foundation Walls',
            self::FoundationWall        => 'Foundation Wall',
            self::SlabOnGrade           => 'Slab On Grade',
            self::SlabOnFill            => 'SlabOnFill',
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
