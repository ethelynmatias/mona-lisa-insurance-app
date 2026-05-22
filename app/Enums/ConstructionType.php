<?php

namespace App\Enums;

enum ConstructionType: int
{
    case NonCombustible                  = 0;
    case MasonryNonCombustible           = 1;
    case ModifiedFireResistive           = 2;
    case SuperiorNonCombustible          = 3;
    case SuperiorMasonryNonCombustible   = 4;
    case PouredConcrete                  = 5;
    case ConcreteTiltUp                  = 6;
    case AsbestosAndStucco               = 7;
    case ConcreteBlock                   = 8;
    case Steel                           = 9;
    case EarthShelter                    = 10;
    case Frame                           = 11;
    case Adobe                           = 12;
    case HeavyTimberedJoistedMasonry     = 13;
    case PlasticVinylSiding              = 14;
    case JoistedMasonry                  = 15;
    case Log                             = 16;
    case Masonry                         = 17;
    case MetalAluminumSiding             = 18;
    case Other                           = 19;
    case PreFabricated                   = 20;
    case FireResistiveSuperior           = 21;
    case MetalPlasticSiding              = 22;
    case TrailerMobileHome               = 23;
    case MasonryVeneer                   = 24;

    public function label(): string
    {
        return match ($this) {
            self::NonCombustible                => 'Non-Combustible',
            self::MasonryNonCombustible         => 'Masonry Non-Combustible',
            self::ModifiedFireResistive         => 'Modified Fire Resistive',
            self::SuperiorNonCombustible        => 'Superior Non-Combustible',
            self::SuperiorMasonryNonCombustible => 'Superior Masonry Non-Combustible',
            self::PouredConcrete                => 'Poured Concrete',
            self::ConcreteTiltUp                => 'Concrete Tilt-up',
            self::AsbestosAndStucco             => 'Asbestos & Stucco',
            self::ConcreteBlock                 => 'Concrete Block',
            self::Steel                         => 'Steel',
            self::EarthShelter                  => 'Earth Shelter',
            self::Frame                         => 'Frame',
            self::Adobe                         => 'Adobe',
            self::HeavyTimberedJoistedMasonry   => 'Heavy Timbered Joisted Masonry',
            self::PlasticVinylSiding            => 'Plastic/Vinyl Siding',
            self::JoistedMasonry                => 'Joisted Masonry',
            self::Log                           => 'Log',
            self::Masonry                       => 'Masonry',
            self::MetalAluminumSiding           => 'Metal/Aluminum Siding',
            self::Other                         => 'Other',
            self::PreFabricated                 => 'Pre-Fabricated',
            self::FireResistiveSuperior         => 'Fire Resistive/Superior',
            self::MetalPlasticSiding            => 'Metal/Plastic Siding',
            self::TrailerMobileHome             => 'Trailer (Mobile Home)',
            self::MasonryVeneer                 => 'Masonry Veneer',
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
