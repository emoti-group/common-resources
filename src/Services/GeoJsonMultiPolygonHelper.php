<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services;

use DaveLiddament\PhpLanguageExtensions\Friend;
use Emoti\CommonResources\DTO\GeoJsonGeometryDTO;
use Emoti\CommonResources\Enums\GeoJsonGeometryType;
use InvalidArgumentException;

#[Friend(LocationsHelper::class)]
final class GeoJsonMultiPolygonHelper
{
    /**
     * Radial polygon expansion:
     * 1) Calculate the centroid of the ENTIRE polygon (including holes),
     * 2) Move each vertex by a fixed 'radiusMeters' along the ray from the centroid to the point.
     *
     * @param bool $shrinkHoles   true = holes are moved "inward" (i.e. SHRINKED), false = expanded like the outer ring
     * @param bool $perRingCenter false = use ONE centroid for the whole polygon; true = calculate center separately for each ring
     * @param int  $precision     coordinate rounding (lon/lat)
     */
    public static function expand(
        GeoJsonGeometryDTO $polygon,
        float $radiusMeters,
        bool $shrinkHoles = true,
        bool $perRingCenter = false,
        int $precision = 6
    ): GeoJsonGeometryDTO {
        if ($polygon->type !== GeoJsonGeometryType::MULTIPOLYGON) {
            throw new InvalidArgumentException('Expected GeoJSON Polygon.');
        }
        if ($radiusMeters <= 0) {
            throw new InvalidArgumentException('radiusMeters must be > 0.');
        }

        $polygons = [];
        foreach ($polygon->coordinates as $poly) {
            if (empty($poly) || count($poly[0]) < 3) {
                continue;
            }
            $polyDTO = new GeoJsonGeometryDTO(GeoJsonGeometryType::POLYGON, $poly, true);
            $expandedPoly = GeoJsonPolygonHelper::expand(
                polygon: $polyDTO,
                radiusMeters: $radiusMeters,
                shrinkHoles: $shrinkHoles,
                perRingCenter: $perRingCenter,
                precision: $precision
            );
            $polygons[] = $expandedPoly;
        }
        $mp = LocationsHelper::complexifyGeoJsonTypes($polygons);

        return self::fixGeoJson($mp);
    }

    public static function fixGeoJson(GeoJsonGeometryDTO $geometryDTO): GeoJsonGeometryDTO
    {
        $geoJson = json_encode(['type' => GeoJsonGeometryType::MULTIPOLYGON->value, 'coordinates' => $geometryDTO->coordinates]);
        $sql = <<<'SQL'
            WITH RECURSIVE
            params AS (
              SELECT ST_GeomFromGeoJSON(?) AS g
            ),
            fold AS (
              SELECT
                1 AS i,
                CASE
                  WHEN ST_NumGeometries(g) IS NULL THEN g
                  ELSE ST_GeometryN(g, 1)
                END AS acc,
                COALESCE(ST_NumGeometries(g), 1) AS n,
                g
              FROM params
              UNION ALL
              SELECT
                i + 1,
                ST_Union(acc, ST_GeometryN(g, i + 1)),
                n,
                g
              FROM fold
              WHERE i < n
            )
            SELECT ST_AsGeoJSON(acc) AS geojson
            FROM fold
            ORDER BY i DESC
            LIMIT 1
        SQL;

        // \DB::statement('SET SESSION cte_max_recursion_depth = 100000');

        $row = \DB::selectOne($sql, [$geoJson]);

        if (!$row) {
            throw new InvalidArgumentException('Failed to process GeoJSON geometry.');
        }
        $geometry = json_decode($row->geojson, true);

        return GeoJsonGeometryDTO::fromValues(GeoJsonGeometryType::tryFrom($geometry['type']), $geometry['coordinates'], true);
    }
}