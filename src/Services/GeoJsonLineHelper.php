<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Services;

use DaveLiddament\PhpLanguageExtensions\Friend;
use Emoti\CommonResources\DTO\GeoJsonGeometryDTO;
use Emoti\CommonResources\Enums\GeoJsonGeometryType;
use InvalidArgumentException;

#[Friend(LocationsHelper::class)]
class GeoJsonLineHelper
{
    private const R = 6378137.0;

    public static function lineToPolygon(
        GeoJsonGeometryDTO $line,
        float $radiusMeters,
        int $roundnessPerQuarter = 10,
        int $precision = 6
    ): GeoJsonGeometryDTO {
        if ($line->type->value !== 'LineString') {
            throw new InvalidArgumentException('Expected GeoJSON LineString.');
        }

        $ll = self::removeDuplicates($line->coordinates);
        if (count($ll) < 2) {
            throw new InvalidArgumentException('LineString must have at least 2 distinct points.');
        }

        // WGS84 -> merc (meters)
        $xy = array_map(fn($p) => self::toMercator((float)$p[0], (float)$p[1]), $ll);
        $n  = count($xy) - 1;

        // Calculation of directions and normals for segments
        $theta = []; // 'i' segment angle (radians)
        for ($i = 0; $i < $n; $i++) {
            $dx = $xy[$i+1][0] - $xy[$i][0];
            $dy = $xy[$i+1][1] - $xy[$i][1];
            $theta[$i] = atan2($dy, $dx);
        }

        $leftPath  = [];
        $rightPath = [];

        // Calculation of angle of line (angle above + 90° and -90° in radians (90° = π/2))
        $nL0 = $theta[0] + M_PI_2;
        $nR0 = $theta[0] - M_PI_2;

        // Create start points of lines (A point)
        $A_left  = self::offsetPoint($xy[0], $nL0, $radiusMeters);
        $A_right = self::offsetPoint($xy[0], $nR0, $radiusMeters);

        // Add start points to path
        self::push($leftPath,  $A_left);
        self::push($rightPath, $A_right);

        // Process middle points of the line
        $miterLimit = 4.0;
        for ($i = 1; $i < $n; $i++) {
            $center = $xy[$i];

            $thetaPrev = $theta[$i-1];
            $thetaNext = $theta[$i];

            $delta = self::normPi($thetaNext - $thetaPrev);

            $a1 = $thetaPrev + M_PI_2;
            $a2 = $thetaNext + M_PI_2;
            $b1 = $thetaPrev - M_PI_2;
            $b2 = $thetaNext - M_PI_2;

            if ($delta > 0) {
                $bisL = self::bisectorAngle($a1, $a2);
                $scale = 1.0 / max(1e-6, sin(abs($delta) / 2.0));
                $scale = min($scale, $miterLimit);
                $B_left = self::offsetPoint($center, $bisL, $radiusMeters * $scale);
                self::push($leftPath, $B_left);
                self::push($rightPath, self::offsetPoint($center, $b2, $radiusMeters));
            } elseif ($delta < 0) {
                self::push($leftPath, self::offsetPoint($center, $a2, $radiusMeters));
                $bisR = self::bisectorAngle($b1, $b2);
                $scale = 1.0 / max(1e-6, sin(abs($delta) / 2.0));
                $scale = min($scale, $miterLimit);
                $B_right = self::offsetPoint($center, $bisR, $radiusMeters * $scale);
                self::push($rightPath, $B_right);
            } else {
                self::push($leftPath,  self::offsetPoint($center, $a2, $radiusMeters));
                self::push($rightPath, self::offsetPoint($center, $b2, $radiusMeters));
            }
        }

        // Offset points at the end
        $nLZ = $theta[$n-1] + M_PI_2;
        $nRZ = $theta[$n-1] - M_PI_2;

        $B_left  = self::offsetPoint($xy[$n], $nLZ, $radiusMeters);
        $B_right = self::offsetPoint($xy[$n], $nRZ, $radiusMeters);

        self::push($leftPath,  $B_left);
        self::push($rightPath, $B_right);

        // front CAP - half circle on the front of line
        $frontCap = self::arcHalf($xy[$n], $nLZ, $radiusMeters, $roundnessPerQuarter);
        $frontCap = array_slice($frontCap, 1);
        foreach ($frontCap as $p) self::push($leftPath, $p);

        // Right path in reverse - left path
        $rightPath = array_reverse($rightPath);
        $rightPath = array_slice($rightPath, 1);
        foreach ($rightPath as $p) self::push($leftPath, $p);

        // back CAP - half circle on the back of line
        $backCap  = self::arcHalf($xy[0],  $nR0, $radiusMeters, $roundnessPerQuarter);
        $backCap = array_slice($backCap, 1);
        foreach ($backCap as $p) self::push($leftPath, $p);

        // Ring closing - ensure first point == last point
        if (!empty($leftPath) && ($leftPath[0][0] !== end($leftPath)[0] || $leftPath[0][1] !== end($leftPath)[1])) {
            $leftPath[] = $leftPath[0];
        }

        // merc -> WGS84 + roundings
        $ringLonLat = array_map(fn($xy) => self::fromMercator($xy[0], $xy[1], $precision), $leftPath);

        return new GeoJsonGeometryDTO(
            type: GeoJsonGeometryType::POLYGON,
            coordinates: [ $ringLonLat ],
        );
    }

    /* ====================== Geometria pomocnicza ====================== */

    private static function toMercator(float $lon, float $lat): array
    {
        $x = deg2rad($lon) * self::R;
        $y = log(tan(M_PI / 4 + deg2rad($lat) / 2)) * self::R;
        return [$x, $y];
    }

    private static function fromMercator(float $x, float $y, int $precision): array
    {
        $lon = rad2deg($x / self::R);
        $lat = rad2deg(2 * atan(exp($y / self::R)) - M_PI / 2);
        return [round($lon, $precision), round($lat, $precision)];
    }

    private static function offsetPoint(array $xy, float $angleRad, float $dist): array
    {
        return [$xy[0] + $dist * cos($angleRad), $xy[1] + $dist * sin($angleRad)];
    }

    /**
     * Arc with minimal range ([-π, π]) around center from startAngle to endAngle.
     * Returns points including endpoints (start and end are included).
     */
    private static function arcMinimal(array $center, float $startAngle, float $endAngle, float $radius, int $roundnessPerQuarter): array
    {
        // minimal rotation ([-π, π])
        $delta = atan2(sin($endAngle - $startAngle), cos($endAngle - $startAngle));
        $abs   = abs($delta);

        // steps count proportional to angle
        $steps = max(1, (int)ceil(($abs / (M_PI / 2)) * $roundnessPerQuarter));
        $pts = [];
        for ($i = 0; $i <= $steps; $i++) {
            $t = $i / $steps;
            $a = $startAngle + $delta * $t;
            $pts[] = [$center[0] + $radius * cos($a), $center[1] + $radius * sin($a)];
        }
        return $pts;
    }

    /** Half circle 180° from startAngle; $sign = +1 (CCW) or -1 (CW). */
    private static function arcHalf(array $center, float $startAngle, float $radius, int $roundnessPerQuarter): array
    {
        $delta = -M_PI;
        $steps = max(1, 2 * $roundnessPerQuarter); // 2 quarters
        $pts = [];
        for ($i = 0; $i <= $steps; $i++) {
            $t = $i / $steps;
            $a = $startAngle + $delta * $t;
            $pts[] = [$center[0] + $radius * cos($a), $center[1] + $radius * sin($a)];
        }
        return $pts;
    }

    /** Adds a point if it is different from the previous one (in Mercator mm). */
    private static function push(array &$arr, array $p): void
    {
        if (empty($arr)) { $arr[] = $p; return; }
        $last = end($arr);
        // ~1e-6 m in Mercator system is a very tight threshold
        if (abs($last[0]-$p[0]) > 1e-6 || abs($last[1]-$p[1]) > 1e-6) {
            $arr[] = $p;
        }
    }

    /**
     * Removes duplicates from coordinates array.
     *
     * @param list<list<float>> $coords
     * @return list<list<float>>
     */
    private static function removeDuplicates(array $coords): array
    {
        $out = [];
        foreach ($coords as $p) {
            if (empty($out) || $out[count($out)-1][0] !== $p[0] || $out[count($out)-1][1] !== $p[1]) {
                $out[] = $p;
            }
        }
        return $out;
    }

    private static function normPi(float $a): float {
        // normalization to (-π, π]
        return atan2(sin($a), cos($a));
    }

    /** bisector angle (safe for 2π wrap) */
    private static function bisectorAngle(float $a, float $b): float {
        $x = cos($a) + cos($b);
        $y = sin($a) + sin($b);
        if (abs($x) < 1e-12 && abs($y) < 1e-12) {
            // opposite vectors (180°) – return e.g. a
            return $a;
        }
        return atan2($y, $x);
    }
}
