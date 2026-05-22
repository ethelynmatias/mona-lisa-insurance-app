<?php

namespace App\Enums;

enum DwellStyleType: int
{
    case OneAndThreeQuarterStories   = 0;
    case TwoAndThreeQuarterStories   = 1;
    case ThreeAndThreeQuarterStories = 2;
    case ThreeAndOneHalfStories      = 3;
    case BackSplit                   = 4;
    case Bungalow                    = 5;
    case CapeCod                     = 6;
    case Colonial                    = 7;
    case Contemporary                = 8;
    case Cottage                     = 9;
    case Craftsman                   = 10;
    case Dome                        = 11;
    case DuplexSemiAttached          = 12;
    case EarthHome                   = 13;
    case Envelope                    = 14;
    case FederalColonial             = 15;
    case Log                         = 16;
    case Manufactured                = 17;
    case Mediterranean               = 18;
    case Mobile                      = 19;
    case OrnateVictorian             = 20;
    case QueenAnne                   = 21;
    case RaisedRanch                 = 22;
    case Rambler                     = 23;
    case RowHouseCenterUnit          = 24;
    case RowHouseEndUnit             = 25;
    case SplitFoyer                  = 26;
    case SplitLevel                  = 27;
    case SteelFrame                  = 28;
    case SubStandard                 = 29;
    case SouthwestAdobe              = 30;
    case TownHouseCenterUnit         = 31;
    case TownHouseEndUnit            = 32;
    case TimberFrame                 = 33;
    case Victorian                   = 34;
    case ApartmentBuilding           = 35;

    public function label(): string
    {
        return match ($this) {
            self::OneAndThreeQuarterStories   => 'One and Three Quarter Stories',
            self::TwoAndThreeQuarterStories   => 'Two and Three Quarter Stories',
            self::ThreeAndThreeQuarterStories => 'Three and Three Quarter Stories',
            self::ThreeAndOneHalfStories      => 'Three and One Half Stories',
            self::BackSplit                   => 'Back Split',
            self::Bungalow                    => 'Bungalow',
            self::CapeCod                     => 'Cape Cod',
            self::Colonial                    => 'Colonial',
            self::Contemporary                => 'Contemporary',
            self::Cottage                     => 'Cottage',
            self::Craftsman                   => 'Craftsman',
            self::Dome                        => 'Dome',
            self::DuplexSemiAttached          => 'Duplex/Semi-attached',
            self::EarthHome                   => 'Earth Home',
            self::Envelope                    => 'Envelope',
            self::FederalColonial             => 'Federal Colonial',
            self::Log                         => 'Log',
            self::Manufactured                => 'Manufactured',
            self::Mediterranean               => 'Mediterranean',
            self::Mobile                      => 'Mobile',
            self::OrnateVictorian             => 'Ornate Victorian',
            self::QueenAnne                   => 'Queen Anne',
            self::RaisedRanch                 => 'Raised Ranch',
            self::Rambler                     => 'Rambler',
            self::RowHouseCenterUnit          => 'Row House Center Unit',
            self::RowHouseEndUnit             => 'Row House End Unit',
            self::SplitFoyer                  => 'Split Foyer',
            self::SplitLevel                  => 'Split Level',
            self::SteelFrame                  => 'Steel Frame',
            self::SubStandard                 => 'Sub Standard',
            self::SouthwestAdobe              => 'Southwest Adobe',
            self::TownHouseCenterUnit         => 'Town House Center Unit',
            self::TownHouseEndUnit            => 'Town House End Unit',
            self::TimberFrame                 => 'Timber Frame',
            self::Victorian                   => 'Victorian',
            self::ApartmentBuilding           => 'Apartment Building',
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
