<?php

namespace App\Enums;

enum RoofMaterialType: int
{
    case CompositionFiberglassAsphalt = 0;
    case AsbestosShakes               = 1;
    case Copper                       = 2;
    case CedarShakes                  = 3;
    case SteelPorcelainShingles       = 4;
    case Plastic                      = 5;
    case RecycledRoofingProducts      = 6;
    case RollRoofing                  = 7;
    case SinglePlyMembraneSystems     = 8;
    case TarAndGravelBuiltUp          = 9;
    case CedarShingles                = 10;
    case Metal                        = 11;
    case ConcreteTile                 = 12;
    case Other                        = 13;
    case Poured                       = 14;
    case Rock                         = 15;
    case Slate                        = 16;
    case Tile                         = 17;
    case AluminumShingles             = 18;
    case WoodShakeShingle             = 19;
    case ClayTile                     = 20;
    case Plywood                      = 21;

    public function label(): string
    {
        return match ($this) {
            self::CompositionFiberglassAsphalt => 'Composition (Fiberglass, Asphalt, etc.)',
            self::AsbestosShakes               => 'Asbestos Shakes',
            self::Copper                       => 'Copper',
            self::CedarShakes                  => 'Cedar Shakes',
            self::SteelPorcelainShingles       => 'Steel/Porcelain Shingles',
            self::Plastic                      => 'Plastic',
            self::RecycledRoofingProducts      => 'Recycled Roofing Products',
            self::RollRoofing                  => 'Roll Roofing',
            self::SinglePlyMembraneSystems     => 'Single Ply Membrane Systems',
            self::TarAndGravelBuiltUp          => 'Tar & Gravel (Built-Up)',
            self::CedarShingles                => 'Cedar Shingles',
            self::Metal                        => 'Metal',
            self::ConcreteTile                 => 'Concrete Tile',
            self::Other                        => 'Other',
            self::Poured                       => 'Poured',
            self::Rock                         => 'Rock',
            self::Slate                        => 'Slate',
            self::Tile                         => 'Tile',
            self::AluminumShingles             => 'Aluminum Shingles',
            self::WoodShakeShingle             => 'Wood Shake/Shingle',
            self::ClayTile                     => 'Clay Tile',
            self::Plywood                      => 'Plywood',
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
