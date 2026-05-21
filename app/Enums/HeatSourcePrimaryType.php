<?php

namespace App\Enums;

enum HeatSourcePrimaryType: int
{
    case CommercialSolidFuelInstallation = 0;
    case CommercialBoilerInstallation    = 1;
    case CoalProfessionallyInstalled     = 2;
    case CoalNonProfessionallyInstalled  = 3;
    case Electric                        = 4;
    case ElectricPortableHeater          = 5;
    case NaturalGas                      = 6;
    case KerosenPortableHeater           = 7;
    case Kerosene                        = 8;
    case LiquidPropaneGas                = 9;
    case LiquidPropanePortableHeater     = 10;
    case None                            = 11;
    case Oil                             = 12;
    case ElectricHeatPump                = 13;
    case Other                           = 14;
    case SolarProfessionallyInstalled    = 15;
    case SolarNonProfessionallyInstalled = 16;
    case WaterElectricallyHeated         = 17;
    case WaterGasHeated                  = 18;
    case WoodProfessionallyInstalled     = 19;
    case WoodNonProfessionallyInstalled  = 20;

    public function label(): string
    {
        return match ($this) {
            self::CommercialSolidFuelInstallation => 'Commercial Solid Fuel Installation',
            self::CommercialBoilerInstallation    => 'Commercial Boiler Installation',
            self::CoalProfessionallyInstalled     => 'Coal Professionally Installed',
            self::CoalNonProfessionallyInstalled  => 'Coal Non-Professionally Installed',
            self::Electric                        => 'Electric',
            self::ElectricPortableHeater          => 'Electric Portable Heater',
            self::NaturalGas                      => 'Natural Gas',
            self::KerosenPortableHeater           => 'Kerosene Portable Heater',
            self::Kerosene                        => 'Kerosene',
            self::LiquidPropaneGas                => 'Liquid Propane Gas',
            self::LiquidPropanePortableHeater     => 'Liquid Propane Portable Heater',
            self::None                            => 'None',
            self::Oil                             => 'Oil',
            self::ElectricHeatPump                => 'Electric - Heat Pump',
            self::Other                           => 'Other',
            self::SolarProfessionallyInstalled    => 'Solar Professionally Installed',
            self::SolarNonProfessionallyInstalled => 'Solar Non-Professionally Installed',
            self::WaterElectricallyHeated         => 'Water - Electrically Heated',
            self::WaterGasHeated                  => 'Water - Gas Heated',
            self::WoodProfessionallyInstalled     => 'Wood Professionally Installed',
            self::WoodNonProfessionallyInstalled  => 'Wood Non-Professionally Installed',
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
