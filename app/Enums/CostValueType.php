<?php

namespace App\Enums;

enum CostValueType: int
{
    case ActualCostValue      = 0;
    case ReplacementCostValue = 1;

    public function label(): string
    {
        return match ($this) {
            self::ActualCostValue      => 'Actual Cost Value',
            self::ReplacementCostValue => 'Replacement Cost Value',
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
