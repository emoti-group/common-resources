<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Emoti\CommonResources\DTO\GeoJsonGeometryDTO;
use Emoti\CommonResources\Enums\GeoJsonGeometryType;
use Emoti\CommonResources\Exceptions\NotImplemented;
use Emoti\CommonResources\Services\LocationsHelper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LocationsHelperTest extends TestCase
{
    public function test_invert_coords_swaps_lon_lat_for_point(): void
    {
        $result = LocationsHelper::invertCoords(GeoJsonGeometryType::POINT, [52.0, 21.0]);

        $this->assertSame([21.0, 52.0], $result);
    }

    public function test_invert_coords_swaps_all_positions_in_polygon(): void
    {
        $coords = [
            [[52.0, 21.0], [53.0, 22.0], [54.0, 23.0], [52.0, 21.0]],
        ];

        $result = LocationsHelper::invertCoords(GeoJsonGeometryType::POLYGON, $coords);

        $this->assertSame([21.0, 52.0], $result[0][0]);
        $this->assertSame([22.0, 53.0], $result[0][1]);
        $this->assertSame([23.0, 54.0], $result[0][2]);
    }

    public function test_convert_coords_to_float_casts_string_values(): void
    {
        $result = LocationsHelper::convertCoordsToFloat(['21.5', '52.3']);

        $this->assertSame(21.5, $result[0]);
        $this->assertSame(52.3, $result[1]);
    }

    public function test_convert_coords_to_float_rounds_to_six_decimal_places(): void
    {
        $result = LocationsHelper::convertCoordsToFloat([21.1234567]);

        $this->assertSame(21.123457, $result[0]);
    }

    public function test_close_geometry_type_appends_first_point_to_open_polygon(): void
    {
        $coords = [
            [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0]],
        ];

        $result = LocationsHelper::closeGeometryType(GeoJsonGeometryType::POLYGON, $coords);

        $ring = $result[0];
        $this->assertSame($ring[0], $ring[array_key_last($ring)]);
        $this->assertCount(5, $ring);
    }

    public function test_close_geometry_type_leaves_closed_polygon_unchanged(): void
    {
        $coords = [
            [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]],
        ];

        $result = LocationsHelper::closeGeometryType(GeoJsonGeometryType::POLYGON, $coords);

        $this->assertCount(5, $result[0]);
    }

    public function test_complexify_geo_json_types_list_of_points_to_multipoint(): void
    {
        $point1 = new GeoJsonGeometryDTO(GeoJsonGeometryType::POINT, [21.0, 52.0]);
        $point2 = new GeoJsonGeometryDTO(GeoJsonGeometryType::POINT, [22.0, 53.0]);

        $result = LocationsHelper::complexifyGeoJsonTypes([$point1, $point2]);

        $this->assertSame(GeoJsonGeometryType::MULTIPOINT, $result->type);
    }

    public function test_complexify_geo_json_types_empty_array_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LocationsHelper::complexifyGeoJsonTypes([]);
    }

    public function test_simplify_geo_json_types_multipolygon_splits_into_polygons(): void
    {
        $multipolygon = new GeoJsonGeometryDTO(
            GeoJsonGeometryType::MULTIPOLYGON,
            [
                [[[0.0, 0.0], [5.0, 0.0], [5.0, 5.0], [0.0, 5.0], [0.0, 0.0]]],
                [[[10.0, 10.0], [15.0, 10.0], [15.0, 15.0], [10.0, 15.0], [10.0, 10.0]]],
            ],
        );

        $result = LocationsHelper::simplifyGeoJsonTypes($multipolygon);

        $this->assertCount(2, $result);
        $this->assertSame(GeoJsonGeometryType::POLYGON, $result[0]->type);
        $this->assertSame(GeoJsonGeometryType::POLYGON, $result[1]->type);
    }

    public function test_geometry_collection_throws_not_implemented(): void
    {
        $this->expectException(NotImplemented::class);

        LocationsHelper::invertCoords(GeoJsonGeometryType::GEOMETRY_COLLECTION, []);
    }
}
