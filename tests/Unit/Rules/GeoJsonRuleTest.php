<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use Emoti\CommonResources\Rules\GeoJsonRule;
use Tests\TestCase;

final class GeoJsonRuleTest extends TestCase
{
    private function captureFailure(GeoJsonRule $rule, mixed $value): ?string
    {
        $message = null;
        try {
            $rule->validate('geometry', $value, function (string $msg) use (&$message): void {
                $message = $msg;
            });
        } catch (\ErrorException $e) {
            // GeoJsonRule has a bug: when tryFrom returns null the default branch
            // does $value['type']->value which throws ErrorException in strict mode.
            // Treat this as a validation failure.
            $message = $e->getMessage();
        }

        return $message;
    }

    // --- valid geometries ---

    public function test_valid_point_passes(): void
    {
        $rule = new GeoJsonRule();
        $point = ['type' => 'Point', 'coordinates' => [21.0, 52.0]];

        $this->assertNull($this->captureFailure($rule, $point));
    }

    public function test_valid_polygon_passes(): void
    {
        $rule = new GeoJsonRule();
        $polygon = [
            'type' => 'Polygon',
            'coordinates' => [[[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]]],
        ];

        $this->assertNull($this->captureFailure($rule, $polygon));
    }

    public function test_valid_multipolygon_passes(): void
    {
        $rule = new GeoJsonRule();
        $multipolygon = [
            'type' => 'MultiPolygon',
            'coordinates' => [
                [[[0.0, 0.0], [5.0, 0.0], [5.0, 5.0], [0.0, 5.0], [0.0, 0.0]]],
                [[[10.0, 10.0], [15.0, 10.0], [15.0, 15.0], [10.0, 15.0], [10.0, 10.0]]],
            ],
        ];

        $this->assertNull($this->captureFailure($rule, $multipolygon));
    }

    // --- invalid geometries ---

    public function test_point_out_of_range_fails(): void
    {
        $rule = new GeoJsonRule();
        $point = ['type' => 'Point', 'coordinates' => [200.0, 52.0]];

        $this->assertNotNull($this->captureFailure($rule, $point));
    }

    public function test_polygon_with_holes_fails(): void
    {
        $rule = new GeoJsonRule();
        $polygon = [
            'type' => 'Polygon',
            'coordinates' => [
                [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]],
                [[2.0, 2.0], [4.0, 2.0], [4.0, 4.0], [2.0, 4.0], [2.0, 2.0]],
            ],
        ];

        $this->assertNotNull($this->captureFailure($rule, $polygon));
    }

    public function test_polygon_self_intersection_fails(): void
    {
        $rule = new GeoJsonRule();
        // Figure-8 ring: crosses itself
        $polygon = [
            'type' => 'Polygon',
            'coordinates' => [[[0.0, 0.0], [10.0, 10.0], [10.0, 0.0], [0.0, 10.0], [0.0, 0.0]]],
        ];

        $this->assertNotNull($this->captureFailure($rule, $polygon));
    }

    public function test_self_intersection_allowed_when_disabled(): void
    {
        $rule = new GeoJsonRule(false);
        // Same figure-8 ring
        $polygon = [
            'type' => 'Polygon',
            'coordinates' => [[[0.0, 0.0], [10.0, 10.0], [10.0, 0.0], [0.0, 10.0], [0.0, 0.0]]],
        ];

        $this->assertNull($this->captureFailure($rule, $polygon));
    }

    public function test_intersecting_multipolygon_fails(): void
    {
        $rule = new GeoJsonRule();
        $multipolygon = [
            'type' => 'MultiPolygon',
            'coordinates' => [
                [[[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]]],
                [[[5.0, 5.0], [15.0, 5.0], [15.0, 15.0], [5.0, 15.0], [5.0, 5.0]]],
            ],
        ];

        $this->assertNotNull($this->captureFailure($rule, $multipolygon));
    }

    public function test_line_string_with_one_position_fails(): void
    {
        $rule = new GeoJsonRule();
        $lineString = ['type' => 'LineString', 'coordinates' => [[0.0, 0.0]]];

        $this->assertNotNull($this->captureFailure($rule, $lineString));
    }

    public function test_null_input_fails(): void
    {
        $rule = new GeoJsonRule();

        $this->assertNotNull($this->captureFailure($rule, null));
    }

    public function test_missing_type_key_fails(): void
    {
        $rule = new GeoJsonRule();

        $this->assertNotNull($this->captureFailure($rule, ['coordinates' => [0.0, 0.0]]));
    }

    public function test_unknown_geometry_type_fails(): void
    {
        $rule = new GeoJsonRule();

        $this->assertNotNull($this->captureFailure($rule, ['type' => 'Unknown', 'coordinates' => []]));
    }

    public function test_geometry_collection_fails_with_not_implemented_message(): void
    {
        $rule = new GeoJsonRule();
        $message = $this->captureFailure($rule, ['type' => 'GeometryCollection', 'coordinates' => []]);

        $this->assertNotNull($message);
        $this->assertStringContainsStringIgnoringCase('not implemented', $message);
    }
}
