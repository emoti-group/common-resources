<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use Emoti\CommonResources\Rules\MultiGeoJsonRule;
use Tests\TestCase;

final class MultiGeoJsonRuleTest extends TestCase
{
    private function captureFailure(MultiGeoJsonRule $rule, mixed $value): ?string
    {
        $message = null;
        $rule->validate('geometry', $value, function (string $msg) use (&$message): void {
            $message = $msg;
        });

        return $message;
    }

    public function test_array_of_points_validated_as_multipoint(): void
    {
        $rule = new MultiGeoJsonRule();
        $value = [
            ['type' => 'Point', 'coordinates' => [21.0, 52.0]],
            ['type' => 'Point', 'coordinates' => [22.0, 53.0]],
        ];

        $this->assertNull($this->captureFailure($rule, $value));
    }

    public function test_single_geometry_passes(): void
    {
        $rule = new MultiGeoJsonRule();
        $value = [
            ['type' => 'Point', 'coordinates' => [21.0, 52.0]],
        ];

        $this->assertNull($this->captureFailure($rule, $value));
    }

    public function test_null_input_fails(): void
    {
        $rule = new MultiGeoJsonRule();

        $this->assertNotNull($this->captureFailure($rule, null));
    }

    public function test_non_array_with_type_key_fails(): void
    {
        $rule = new MultiGeoJsonRule();
        // Single geometry (not wrapped in array) — has 'type' key so it's rejected
        $value = ['type' => 'Point', 'coordinates' => [21.0, 52.0]];

        $this->assertNotNull($this->captureFailure($rule, $value));
    }

    public function test_missing_type_in_geometry_fails(): void
    {
        $rule = new MultiGeoJsonRule();
        $value = [
            ['coordinates' => [21.0, 52.0]],
        ];

        $this->assertNotNull($this->captureFailure($rule, $value));
    }
}
