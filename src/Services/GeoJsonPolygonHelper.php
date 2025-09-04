<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services;

use DaveLiddament\PhpLanguageExtensions\Friend;
use Emoti\CommonResources\DTO\GeoJsonGeometryDTO;
use Emoti\CommonResources\Enums\GeoJsonGeometryType;
use InvalidArgumentException;

#[Friend(LocationsHelper::class, GeoJsonMultiPolygonHelper::class)]
class GeoJsonPolygonHelper
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
        if ($polygon->type !== GeoJsonGeometryType::POLYGON) {
            throw new InvalidArgumentException('Expected GeoJSON Polygon.');
        }
        if ($radiusMeters <= 0) {
            throw new InvalidArgumentException('radiusMeters must be > 0.');
        }
        if (empty($polygon->coordinates) || count($polygon->coordinates[0]) < 3) {
            throw new InvalidArgumentException('Polygon must have at least 3 vertices.');
        }

        // 0) prepare rings (without closing duplicate)
        $ringsLL = array_map([self::class, 'dedupeAndUnclose'], $polygon->coordinates);

        // 1) lon/lat -> Mercator (meters)
        $ringsXY = array_map(
            fn(array $ring) => array_map(fn($p) => self::toMercator((float)$p[0], (float)$p[1]), $ring),
            $ringsLL
        );

        // 2) global centroid (holes as negative contribution)
        $globalC = self::centroidOfPolygon($ringsXY);

        // 3) determine which ring is outer (largest |area|)
        $areas = array_map([self::class, 'signedArea2'], $ringsXY);
        $outerIdx = self::indexOfMaxAbs($areas);

        // 4) move points along rays
        $outRingsXY = [];
        foreach ($ringsXY as $idx => $ringXY) {
            if (count($ringXY) < 3) { continue; }

            // choose center (global or per ring)
            $center = $perRingCenter ? self::centroidOfRing($ringXY) : $globalC;

            $sign = +1.0; // default "outward"
            if ($idx !== $outerIdx && $shrinkHoles) {
                // for holes: "inward" (towards center), i.e. negative sign
                $sign = -1.0;
            }

            $expanded = [];
            foreach ($ringXY as $P) {
                $v = [$P[0] - $center[0], $P[1] - $center[1]];
                $len = hypot($v[0], $v[1]);
                if ($len < 1e-9) {
                    // rare case: vertex ~centroid; move in a random stable direction
                    $dir = [1.0, 0.0];
                } else {
                    $dir = [$v[0] / $len, $v[1] / $len];
                }
                // move by fixed radius along the ray (C->P)
                $expanded[] = [$P[0] + $sign * $radiusMeters * $dir[0], $P[1] + $sign * $radiusMeters * $dir[1]];
            }

            $outRingsXY[] = self::ensureClosed($expanded);
        }

        // 5) back to WGS84
        $outRingsLL = array_map(
            fn(array $ring) => array_map(fn($xy) => self::fromMercator($xy[0], $xy[1], $precision), $ring),
            $outRingsXY
        );

        return new GeoJsonGeometryDTO(
            type: GeoJsonGeometryType::POLYGON,
            coordinates: $outRingsLL
        );
    }

    /* ================== CENTROIDS ================== */

    /** Centroid of a single ring (shoelace, in meters). Returns [x,y]. */
    private static function centroidOfRing(array $ringXY): array
    {
        $A2 = self::signedArea2($ringXY); // 2*A with sign
        $n = count($ringXY);
        if (abs($A2) < 1e-9) {
            // degenerate: simple average
            $sx = 0.0; $sy = 0.0;
            foreach ($ringXY as $p) { $sx += $p[0]; $sy += $p[1]; }
            return [$sx/$n, $sy/$n];
        }
        $cx = 0.0; $cy = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $cross = $ringXY[$i][0]*$ringXY[$j][1] - $ringXY[$i][1]*$ringXY[$j][0];
            $cx += ($ringXY[$i][0] + $ringXY[$j][0]) * $cross;
            $cy += ($ringXY[$i][1] + $ringXY[$j][1]) * $cross;
        }
        $factor = 1.0 / (3.0 * $A2);
        return [$cx * $factor, $cy * $factor];
    }

    /**
     * Centroid of the whole polygon (outer + holes). Each ring contributes ~ signedArea * centroid.
     * Holes (opposite orientation) contribute with a negative sign.
     */
    private static function centroidOfPolygon(array $ringsXY): array
    {
        $sumA2 = 0.0; $sumX = 0.0; $sumY = 0.0;
        foreach ($ringsXY as $ring) {
            if (count($ring) < 3) continue;
            $A2 = self::signedArea2($ring);
            $c  = self::centroidOfRing($ring);
            $sumA2 += $A2;
            $sumX  += $c[0] * $A2;
            $sumY  += $c[1] * $A2;
        }
        if (abs($sumA2) < 1e-9) {
            // fallback: centroid of the largest ring
            $idx = self::indexOfMaxAbs(array_map([self::class,'signedArea2'], $ringsXY));
            return self::centroidOfRing($ringsXY[$idx]);
        }
        return [$sumX / $sumA2, $sumY / $sumA2];
    }

    /* ================== BASIC GEOMETRY ================== */

    private static function toMercator(float $lon, float $lat): array
    {
        $x = deg2rad($lon) * LocationsHelper::R;
        $lat = max(min($lat, 85.0), -85.0); // stabilization
        $y = log(tan(M_PI / 4 + deg2rad($lat) / 2)) * LocationsHelper::R;
        return [$x, $y];
    }

    private static function fromMercator(float $x, float $y, int $precision): array
    {
        $lon = rad2deg($x / LocationsHelper::R);
        $lat = rad2deg(2 * atan(exp($y / LocationsHelper::R)) - M_PI / 2);
        return [round($lon, $precision), round($lat, $precision)];
    }

    /** Signed double area of the ring (shoelace); CCW > 0, CW < 0 */
    private static function signedArea2(array $ringXY): float
    {
        $n = count($ringXY);
        $s = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $s += $ringXY[$i][0]*$ringXY[$j][1] - $ringXY[$i][1]*$ringXY[$j][0];
        }
        return $s;
    }

    private static function indexOfMaxAbs(array $vals): int
    {
        $idx = 0; $max = -INF;
        foreach ($vals as $i => $v) {
            $a = abs($v);
            if ($a > $max) { $max = $a; $idx = $i; }
        }
        return $idx;
    }

    /* ================== UTIL ================== */

    /** Remove closing duplicate and consecutive identical points */
    private static function dedupeAndUnclose(array $ring): array
    {
        $n = count($ring);
        if ($n >= 2 && $ring[0] === $ring[$n-1]) { array_pop($ring); $n--; }
        $out = [];
        foreach ($ring as $p) {
            if (empty($out) || $out[count($out)-1][0] !== $p[0] || $out[count($out)-1][1] !== $p[1]) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /** Ensure the ring is closed (first point == last point) */
    private static function ensureClosed(array $ring): array
    {
        if (empty($ring)) return $ring;
        $first = $ring[0]; $last = $ring[count($ring)-1];
        if ($first[0] !== $last[0] || $first[1] !== $last[1]) $ring[] = $first;
        return $ring;
    }
}
