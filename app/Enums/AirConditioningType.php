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
