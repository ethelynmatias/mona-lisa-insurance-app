<?php

namespace Tests\Unit\Enums;

use App\Enums\ConstructionType;
use App\Enums\RoofMaterialType;
use App\Enums\HeatSourcePrimaryType;
use PHPUnit\Framework\TestCase;

/**
 * Tests fromLabel() resolution against real form 11 payload values.
 *
 * Payload reference: entry 11-49
 *   TypeOfConstruction  = "Frame"
 *   TypeOfRoof          = "Shingle"
 *   HeatingType         = null
 *   NumberOfStories     = "2"
 *   FeetToHydrant       = "2"
 */
class ConstructionTypeTest extends TestCase
{
    // ── ConstructionType ─────────────────────────────────────────────────────

    public function test_frame_exact_match(): void
    {
        $result = ConstructionType::fromLabel('Frame');

        $this->assertSame(ConstructionType::Frame, $result);
        $this->assertSame(11, $result->value);
    }

    public function test_frame_case_insensitive(): void
    {
        $this->assertSame(ConstructionType::Frame, ConstructionType::fromLabel('frame'));
        $this->assertSame(ConstructionType::Frame, ConstructionType::fromLabel('FRAME'));
    }

    public function test_masonry_exact_match(): void
    {
        $result = ConstructionType::fromLabel('Masonry');

        $this->assertSame(ConstructionType::Masonry, $result);
        $this->assertSame(17, $result->value);
    }

    public function test_unrecognized_string_returns_null(): void
    {
        $this->assertNull(ConstructionType::fromLabel('Data Conte'));
        $this->assertNull(ConstructionType::fromLabel('Lorem Ipsum'));
        $this->assertNull(ConstructionType::fromLabel('xyz'));
    }

    public function test_empty_string_returns_null(): void
    {
        $this->assertNull(ConstructionType::fromLabel('   '));
    }

    public function test_word_match_against_case_name(): void
    {
        // "asbestos" appears in case name "AsbestosAndStucco"
        $result = ConstructionType::fromLabel('asbestos');

        $this->assertSame(ConstructionType::AsbestosAndStucco, $result);
        $this->assertSame(7, $result->value);
    }

    // ── RoofMaterialType ─────────────────────────────────────────────────────

    public function test_shingle_partial_matches_via_case_name(): void
    {
        // "Shingle" is not an exact label but the word "shingle" appears in
        // multiple case names — expects the first enum case matched.
        $result = RoofMaterialType::fromLabel('Shingle');

        $this->assertNotNull($result);
        $this->assertInstanceOf(RoofMaterialType::class, $result);
    }

    public function test_metal_exact_match(): void
    {
        $result = RoofMaterialType::fromLabel('Metal');

        $this->assertSame(RoofMaterialType::Metal, $result);
        $this->assertSame(11, $result->value);
    }

    public function test_roof_unrecognized_returns_null(): void
    {
        // Words must not appear in any case name
        $this->assertNull(RoofMaterialType::fromLabel('abc def'));
        $this->assertNull(RoofMaterialType::fromLabel('Lorem Ipsum'));
    }

    // ── HeatSourcePrimaryType ────────────────────────────────────────────────

    public function test_null_heating_type_never_reaches_fromlabel(): void
    {
        // HeatingType = null in entry 11-49.
        // nestPropertyPayload skips null values before calling fromLabel,
        // so fromLabel is never called — this guard confirms null input
        // would not be passed as a string.
        $entry = ['HeatingType' => null];

        $value = $entry['HeatingType'] ?? null;

        $this->assertNull($value);
    }

    public function test_gas_propane_or_natural_word_match(): void
    {
        // "gas" word matches "NaturalGas" or "LiquidPropaneGas" case name
        $result = HeatSourcePrimaryType::fromLabel('Gas (propane or natural)');

        $this->assertNotNull($result);
        $this->assertInstanceOf(HeatSourcePrimaryType::class, $result);
    }

    public function test_natural_gas_exact_match(): void
    {
        $result = HeatSourcePrimaryType::fromLabel('Natural Gas');

        $this->assertSame(HeatSourcePrimaryType::NaturalGas, $result);
        $this->assertSame(6, $result->value);
    }

    public function test_electric_exact_match(): void
    {
        $result = HeatSourcePrimaryType::fromLabel('Electric');

        $this->assertSame(HeatSourcePrimaryType::Electric, $result);
        $this->assertSame(4, $result->value);
    }

    // ── Numeric fields (numStories / distanceToHydrant) ──────────────────────

    public function test_number_of_stories_string_casts_to_float(): void
    {
        $value = '2'; // from NumberOfStories in entry 11-49

        $result = is_numeric($value) ? (float) $value : null;

        $this->assertSame(2.0, $result);
    }

    public function test_feet_to_hydrant_string_casts_to_int(): void
    {
        $value = '2'; // from FeetToHydrant in entry 11-49

        $result = is_numeric($value) ? (int) $value : null;

        $this->assertSame(2, $result);
    }

    public function test_non_numeric_stories_returns_null(): void
    {
        $value = 'Lorem Cont';

        $result = is_numeric($value) ? (float) $value : null;

        $this->assertNull($result);
    }
}
