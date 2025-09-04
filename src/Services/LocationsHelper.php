<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services;

use Emoti\CommonResources\DTO\GeoJsonGeometryDTO;
use Emoti\CommonResources\Enums\GeoJsonGeometryType;
use Emoti\CommonResources\Exceptions\NotImplemented;
use InvalidArgumentException;

class LocationsHelper
{
    public const R = 6378137.0;

    /**
     * Runs the Ramer-Douglas-Peucker algorithm to simplify the polygon.
     *
     * @param array<0|1, float> | list<array<0|1, float> | list<array<0|1, float> | list<array<0|1, float>>>> $coords The coordinates to be inverted as in GeoJson.
     * @return list<array<0|1, float>> The transformed and simplified array of points.
     */
    public static function simplifyCoords(GeoJsonGeometryType $type, array $coords, float $epsilon = 0.001): array
    {
        switch ($type) {
            case GeoJsonGeometryType::POINT:
            case GeoJsonGeometryType::MULTIPOINT:
            case GeoJsonGeometryType::LINE:
            case GeoJsonGeometryType::MULTILINE:
                break;
            case GeoJsonGeometryType::POLYGON:
                {
                    foreach ($coords as $key => $shape) {
                        $coords[$key] = self::rdpAlgorithm($shape, $epsilon);
                    }

                    break;
                }
            case GeoJsonGeometryType::MULTIPOLYGON:
                {
                    foreach ($coords as $key => $polygon) {
                        foreach ($polygon as $subKey => $poly) {
                            $coords[$key][$subKey] = self::rdpAlgorithm($poly, $epsilon);
                        }
                    }

                    break;
                }
            case GeoJsonGeometryType::GEOMETRY_COLLECTION:
                throw new NotImplemented('GeoJsonGeometryType: GEOMETRY_COLLECTION is not implemented.');
        }

        return $coords;
    }

    /**
     * Simplifies a set of points using the Ramer-Douglas-Peucker algorithm.
     *
     * @param list<array<0|1, float>> $points An array of points, where each point is an array with two elements (x, y).
     * @param float $epsilon The distance threshold for simplification.
     * @return list<array<0|1, float>> The simplified array of points.
     */
    private static function rdpAlgorithm(array $points, float $epsilon): array
    {
        if (count($points) < 5) {
            return $points;
        }
        $dmax = 0;
        $index = 0;
        $end = count($points) - 1;

        for ($i = 1; $i < $end; $i++) {
            $d = self::getPerpendicularDistance($points[$i], $points[0], $points[$end]);
            if ($d > $dmax) {
                $index = $i;
                $dmax = $d;
            }
        }

        if ($dmax > $epsilon) {
            $recResults1 = self::rdpAlgorithm(array_slice($points, 0, $index + 1), $epsilon);
            $recResults2 = self::rdpAlgorithm(array_slice($points, $index, count($points) - $index), $epsilon);

            return array_merge(array_slice($recResults1, 0, -1), $recResults2);
        } else {
            return [$points[0], $points[$end]];
        }
    }

    /**
     * Calculates the perpendicular distance from a point to a line segment defined by two endpoints.
     *
     * @param array<0|1, float> $pt The point from which the distance is calculated, represented as an array [x, y].
     * @param array<0|1, float> $lineStart The start point of the line segment, represented as an array [x, y].
     * @param array<0|1, float> $lineEnd The end point of the line segment, represented as an array [x, y].
     * @return float The perpendicular distance from the point to the line segment.
     */
    private static function getPerpendicularDistance(array $pt, array $lineStart, array $lineEnd): float
    {
        if ($lineStart == $lineEnd) {
            return sqrt(pow($pt[0] - $lineStart[0], 2) + pow($pt[1] - $lineStart[1], 2));
        }
        $x = $pt[0];
        $y = $pt[1];
        $x1 = $lineStart[0];
        $y1 = $lineStart[1];
        $x2 = $lineEnd[0];
        $y2 = $lineEnd[1];

        $num = abs(($y2 - $y1) * $x - ($x2 - $x1) * $y + $x2 * $y1 - $y2 * $x1);
        $den = sqrt(pow($y2 - $y1, 2) + pow($x2 - $x1, 2));
        return (float) ($num / $den);
    }

    /**
     * Inverts the coordinates of a GeoJSON geometry type.
     *
     * @param GeoJsonGeometryType $type The type of the GeoJSON geometry.
     * @param array<0|1, float> | list<array<0|1, float> | list<array<0|1, float> | list<array<0|1, float>>>> $coords The coordinates to be inverted as in GeoJson.
     * @return array The inverted coordinates.
     */
    public static function invertCoords(GeoJsonGeometryType $type, array $coords): array
    {
        switch ($type) {
            case GeoJsonGeometryType::POINT:
                {
                    $coords = [$coords[1], $coords[0]];

                    break;
                }
            case GeoJsonGeometryType::MULTIPOINT:
            case GeoJsonGeometryType::LINE:
                {
                    foreach ($coords as $key => $cord) {
                        $coords[$key] = [$cord[1], $cord[0]];
                    }

                    break;
                }
            case GeoJsonGeometryType::MULTILINE:
            case GeoJsonGeometryType::POLYGON:
                {
                    foreach ($coords as $key => $shape) {
                        foreach ($shape as $subKey => $cord) {
                            $coords[$key][$subKey] = [$cord[1], $cord[0]];
                        }
                    }

                    break;
                }
            case GeoJsonGeometryType::MULTIPOLYGON:
                {
                    foreach ($coords as $key => $polygon) {
                        foreach ($polygon as $subKey => $poly) {
                            foreach ($poly as $subSubKey => $cord) {
                                $coords[$key][$subKey][$subSubKey] = [$cord[1], $cord[0]];
                            }
                        }
                    }

                    break;
                }
            case GeoJsonGeometryType::GEOMETRY_COLLECTION:
                throw new NotImplemented('GeoJsonGeometryType: GEOMETRY_COLLECTION is not implemented.');
        }

        return $coords;
    }

    /**
     * Converts coordinates in an array to float values.
     *
     * @param array $coords The coordinates to convert, which can be nested arrays.
     * @return array The coordinates with all values converted to float.
     */
    public static function convertCoordsToFloat(array $coords): array
    {
        foreach ($coords as $key => $value) {
            if (is_array($value)) {
                $coords[$key] = self::convertCoordsToFloat($value);
            } else {
                $coords[$key] = round((float) $value, 6);
            }
        }

        return $coords;
    }

    /**
     * Changes a list of GeoJSON geometries into a complexified GeoJsonGeometryDTO. For example: from a list of points,
     * multipoint is created, from a list of polygons, multipolygon is created, etc.
     *
     * @param list<GeoJsonGeometryDTO> $geoJsons An array of GeoJSON geometries.
     * @return GeoJsonGeometryDTO A complexified GeoJsonGeometryDTO.
     * @throws InvalidArgumentException If the geometry array is empty.
     */
    public static function complexifyGeoJsonTypes(array $geoJsons): GeoJsonGeometryDTO
    {
        if (count($geoJsons) === 1) {
            return GeoJsonGeometryDTO::fromValues(
                $geoJsons[0]->type,
                $geoJsons[0]->coordinates,
                true,
            );
        }

        if (count($geoJsons) === 0) {
            throw new InvalidArgumentException('Empty geometry array.');
        }

        $firstType = $geoJsons[0]->type;

        switch ($firstType) {
            case GeoJsonGeometryType::POINT:
                $coordinates = array_map(fn($g) => $g->coordinates, $geoJsons);
                return GeoJsonGeometryDTO::fromValues(GeoJsonGeometryType::MULTIPOINT, $coordinates, true);

            case GeoJsonGeometryType::LINE:
                $coordinates = array_map(fn($g) => $g->coordinates, $geoJsons);
                return GeoJsonGeometryDTO::fromValues(GeoJsonGeometryType::MULTILINE, $coordinates, true);

            case GeoJsonGeometryType::POLYGON:
                $coordinates = array_map(fn($g) => $g->coordinates, $geoJsons);
                $flattened = array_map(fn($c) => $c[0], $coordinates);
                return GeoJsonGeometryDTO::fromValues(
                    GeoJsonGeometryType::MULTIPOLYGON,
                    array_map(fn($c) => [$c], $flattened),
                    true,
                );

            default:
                throw new NotImplemented("Unsupported geometry type: $firstType->value");
        }
    }

    /**
     * Simplifies GeoJSON geometries by converting multi-types into simpler types.
     *
     * @return list<GeoJsonGeometryDTO> An array of simplified GeoJsonGeometryDTO objects.
     */
    public static function simplifyGeoJsonTypes(GeoJsonGeometryDTO $geoJsonGeometryDTO): array
    {
        $geometries = [];

        switch ($geoJsonGeometryDTO->type) {
            case GeoJsonGeometryType::MULTILINE:
                {
                    foreach ($geoJsonGeometryDTO->coordinates as $cords) {
                        $geometries[] = GeoJsonGeometryDTO::fromValues(
                            GeoJsonGeometryType::LINE,
                            $cords,
                            true,
                        );
                    }

                    break;
                }
            case GeoJsonGeometryType::MULTIPOINT:
                {
                    foreach ($geoJsonGeometryDTO->coordinates as $cords) {
                        $geometries[] = GeoJsonGeometryDTO::fromValues(
                            GeoJsonGeometryType::POINT,
                            $cords,
                            true,
                        );
                    }

                    break;
                }
            case GeoJsonGeometryType::MULTIPOLYGON:
                {
                    foreach ($geoJsonGeometryDTO->coordinates as $polygon) {
                        foreach ($polygon as $poly) {
                            $geometries[] = GeoJsonGeometryDTO::fromValues(
                                GeoJsonGeometryType::POLYGON,
                                [$poly],
                                true,
                            );
                        }
                    }

                    break;
                }
            case GeoJsonGeometryType::GEOMETRY_COLLECTION:
                throw new NotImplemented('GeoJsonGeometryType: GEOMETRY_COLLECTION is not implemented.');
            case GeoJsonGeometryType::POINT:
            case GeoJsonGeometryType::LINE:
            case GeoJsonGeometryType::POLYGON:
                $geometries[] = $geoJsonGeometryDTO;
        }

        return $geometries;
    }

    /**
     * closeGeometryType
     *
     * @param GeoJsonGeometryType $type The type of the GeoJSON geometry.
     * @param array<0|1, float> | list<array<0|1, float> | list<array<0|1, float> | list<array<0|1, float>>>> $coords The coordinates to be closed.
     * @return array The closed coordinates.
     */
    public static function closeGeometryType(GeoJsonGeometryType $type, array $coords): array
    {
        switch ($type) {
            case GeoJsonGeometryType::POINT:
            case GeoJsonGeometryType::MULTIPOINT:
            case GeoJsonGeometryType::LINE:
            case GeoJsonGeometryType::MULTILINE:
                // no closing needed for these types
                break;
            case GeoJsonGeometryType::POLYGON:
                {
                    foreach ($coords as $key => $polygon) {
                        if ($polygon[0] !== $polygon[array_key_last($polygon)]) {
                            $coords[$key][] = $polygon[0];
                        }
                    }

                    break;
                }
            case GeoJsonGeometryType::MULTIPOLYGON:
                {
                    foreach ($coords as $key => $polygon) {
                        foreach ($polygon as $subKey => $poly) {
                            if ($poly[0] !== $poly[array_key_last($poly)]) {
                                $coords[$key][$subKey][] = $poly[0];
                            }
                        }
                    }

                    break;
                }
            case GeoJsonGeometryType::GEOMETRY_COLLECTION:
                throw new NotImplemented('GeoJsonGeometryType: GEOMETRY_COLLECTION is not implemented.');
        }

        return $coords;
    }

    public static function circleToPolygon(float $latDeg, float $lonDeg, float $radiusKm, int $numSides = 64): GeoJsonGeometryDTO
    {
        $d = $radiusKm / self::R;
        $lat1 = deg2rad($latDeg);
        $lon1 = deg2rad($lonDeg);

        $ring = [];
        for ($i = 0; $i < $numSides; $i++) {
            $bearing = 2 * M_PI * $i / $numSides;

            $lat2 = asin(sin($lat1) * cos($d) + cos($lat1) * sin($d) * cos($bearing));
            $lon2 = $lon1 + atan2(
                    sin($bearing) * sin($d) * cos($lat1),
                    cos($d) - sin($lat1) * sin($lat2),
                );

            $ring[] = [rad2deg($lon2), rad2deg($lat2)];
        }

        if ($ring[0] !== end($ring)) {
            $ring[] = $ring[0];
        }

        return GeoJsonGeometryDTO::fromValues(
            GeoJsonGeometryType::POLYGON,
            [$ring],
            true,
        );
    }

    /**
     * @throws NotImplemented
     */
    public static function extendGeometryWithRadius(GeoJsonGeometryDTO $geoJsonGeometryDTO, float $radius): GeoJsonGeometryDTO
    {
        switch ($geoJsonGeometryDTO->type) {
            case GeoJsonGeometryType::POINT:
                return self::circleToPolygon(
                    latDeg: $geoJsonGeometryDTO->coordinates[1],
                    lonDeg: $geoJsonGeometryDTO->coordinates[0],
                    radiusKm: $radius,
                );
            case GeoJsonGeometryType::MULTIPOINT:
                $polygons = [];
                foreach ($geoJsonGeometryDTO->coordinates as $point) {
                    $polygons[] = self::circleToPolygon(
                        latDeg: $point[1],
                        lonDeg: $point[0],
                        radiusKm: $radius,
                    );
                }

                return self::complexifyGeoJsonTypes($polygons);
            case GeoJsonGeometryType::LINE:
                return GeoJsonLineHelper::lineToPolygon($geoJsonGeometryDTO, $radius * 1000);
            case GeoJsonGeometryType::MULTILINE:
                $lines = [];
                foreach ($geoJsonGeometryDTO->coordinates as $line) {
                    $lines[] = GeoJsonLineHelper::lineToPolygon($geoJsonGeometryDTO, $radius * 1000);
                }

                $lines = self::complexifyGeoJsonTypes($lines);

                if ($lines->type === GeoJsonGeometryType::MULTIPOLYGON) {
                    return GeoJsonMultiPolygonHelper::fixGeoJson($lines);
                }

                return $lines;
            case GeoJsonGeometryType::POLYGON:
                return GeoJsonPolygonHelper::expand($geoJsonGeometryDTO, $radius * 1000);
            case GeoJsonGeometryType::MULTIPOLYGON:
                return GeoJsonMultiPolygonHelper::expand($geoJsonGeometryDTO, $radius * 1000);
            default:
                throw new NotImplemented('GeoJsonGeometryType: '. $geoJsonGeometryDTO->type->value .' is not implemented.');
        }
    }
}
