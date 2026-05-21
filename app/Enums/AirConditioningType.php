<?php

namespace App\Enums;

enum AirConditioningType: int
{
    case UsesHeatDucts           = 0;
    case UsesSeparateDuctSystem  = 1;
    case EvaporativeCooling      = 2;
    case HeatPumpCooling         = 3;
    case Other                   = 4;

    public function label(): string
    {
        return match ($this) {
            self::UsesHeatDucts          => 'Uses Heat Ducts',
            self::UsesSeparateDuctSystem => 'Uses Separate Duct System',
            self::EvaporativeCooling     => 'Evaporative Cooling',
            self::HeatPumpCooling        => 'Heat Pump Cooling',
            self::Other                  => 'Other',
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
