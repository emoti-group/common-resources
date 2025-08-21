<?php

namespace Emoti\CommonResources\Rules;

use Closure;
use Emoti\CommonResources\Enums\GeoJsonGeometryType;
use Illuminate\Contracts\Validation\ValidationRule;

class GeoJsonRule implements ValidationRule
{
    public function __construct(private readonly bool $validateIntersection = true) {}

    protected string|null $error;

    public function validate($attribute, $value, Closure $fail): void
    {
        if ($value === null) {
            $fail('Invalid JSON or unsupported type (expect GeoJSON geometry object).');
            return;
        }
        if ((!is_array($value) || !isset($value['type']))) {
            $fail("GeoJSON must be an object with a 'type' property.");
            return;
        }
        if (!($value['type'] instanceof GeoJsonGeometryType)) {
            $value['type'] = GeoJsonGeometryType::tryFrom($value['type']);
        }
        switch ($value['type']) {
            case GeoJsonGeometryType::POINT:
                $result = $this->validatePoint($value);
                break;
            case GeoJsonGeometryType::MULTIPOINT:
                $result = $this->validateMultiPoint($value);
                break;
            case GeoJsonGeometryType::LINE:
                $result = $this->validateLineString($value);
                break;
            case GeoJsonGeometryType::MULTILINE:
                $result = $this->validateMultiLineString($value);
                break;
            case GeoJsonGeometryType::POLYGON:
                $result = $this->validatePolygon($value);
                break;
            case GeoJsonGeometryType::MULTIPOLYGON:
                $result = $this->validateMultiPolygon($value);
                break;
            case GeoJsonGeometryType::GEOMETRY_COLLECTION:
                $fail('GeometryCollection validation is not implemented yet.');
                return;
            default:
                $fail("Unsupported GeoJSON geometry type: {$value['type']->value}.");
                return;
        }
        if (!$result) {
            $fail($this->error ?: 'Invalid GeoJSON geometry.');
        }
    }

    public function message(): string
    {
        return $this->error ?: 'Invalid GeoJSON geometry.';
    }

    // ---------- helpers ----------

    private function fail(string $msg): bool
    {
        $this->error = $msg;
        return false;
    }

    /** RFC 7946 position: [lon, lat] or [lon, lat, altitude] */
    private function isPosition($pos): bool
    {
        if (!is_array($pos) || count($pos) !== 2) {
            return false;
        }
        [$lon, $lat] = $pos;

        if (!is_numeric($lon) || !is_numeric($lat)) {
            return false;
        }

        // ranges per RFC 7946 (WGS84 lon/lat in degrees)
        if ($lon < -180 || $lon > 180) {
            return false;
        }
        if ($lat < -90 || $lat > 90) {
            return false;
        }

        return true;
    }

    private function positionsEqual($a, $b, float $eps = 1e-12): bool
    {
        if (!is_array($a) || !is_array($b) || count($a) < 2 || count($b) < 2) {
            return false;
        }
        return (abs($a[0] - $b[0]) <= $eps) && (abs($a[1] - $b[1]) <= $eps);
    }

    private function validatePoint(array $g): bool
    {
        if (!isset($g['coordinates'])) {
            return $this->fail("Point must have 'coordinates'.");
        }
        if (!$this->isPosition($g['coordinates'])) {
            return $this->fail('Point coordinates must be [lon, lat] within valid ranges.');
        }
        return true;
    }

    private function validateMultiPoint(array $g): bool
    {
        if (!isset($g['coordinates']) || !is_array($g['coordinates'])) {
            return $this->fail("MultiPoint must have 'coordinates' as an array of positions.");
        }
        if (count($g['coordinates']) < 1) {
            return $this->fail('MultiPoint must contain at least one position.');
        }
        foreach ($g['coordinates'] as $p) {
            if (!$this->isPosition($p)) {
                return $this->fail('MultiPoint contains an invalid position.');
            }
        }
        return true;
    }

    private function validateLineString(array $g): bool
    {
        if (!isset($g['coordinates']) || !is_array($g['coordinates'])) {
            return $this->fail("LineString must have 'coordinates' as an array of positions.");
        }
        if (count($g['coordinates']) < 2) {
            return $this->fail('LineString must have at least two positions.');
        }
        foreach ($g['coordinates'] as $p) {
            if (!$this->isPosition($p)) {
                return $this->fail('LineString contains an invalid position.');
            }
        }
        return true;
    }

    private function validateMultiLineString(array $g): bool
    {
        if (!isset($g['coordinates']) || !is_array($g['coordinates'])) {
            return $this->fail("MultiLineString must have 'coordinates' as an array of LineString coordinate arrays.");
        }
        if (count($g['coordinates']) < 1) {
            return $this->fail('MultiLineString must contain at least one LineString.');
        }
        foreach ($g['coordinates'] as $line) {
            if (!is_array($line) || count($line) < 2) {
                return $this->fail('Each LineString in MultiLineString must have at least two positions.');
            }
            foreach ($line as $p) {
                if (!$this->isPosition($p)) {
                    return $this->fail('MultiLineString contains an invalid position.');
                }
            }
        }
        return true;
    }

    private function validatePolygon(array $g): bool
    {
        if (!isset($g['coordinates']) || !is_array($g['coordinates'])) {
            return $this->fail("Polygon must have 'coordinates' as an array of LinearRings.");
        }
        if (count($g['coordinates']) < 1) {
            return $this->fail('Polygon must have at least one LinearRing (the exterior ring).');
        }
        if ($this->hasHoles($g)) {
            return $this->fail(
                'Polygon cannot have holes.',
            );
        }
        foreach ($g['coordinates'] as $idx => $ring) {
            if (!$this->isLinearRing($ring)) {
                return $this->fail(
                    'Polygon ring #' . ($idx + 1) . ' must have ≥3 unique positions, valid lon/lat, and be closed (first==last).',
                );
            }
            if ($this->validateIntersection && $this->ringHasSelfIntersections($ring)) {
                return $this->fail('Polygon ring #' . ($idx + 1) . ' must not have self-intersections.');
            }
        }
        return true;
    }

    private function polygonsIntersect(array $poly1, array $poly2): bool
    {
        $ring1 = $poly1[0];
        $ring2 = $poly2[0];
        $n1 = count($ring1);
        $n2 = count($ring2);
        for ($i = 0; $i < $n1 - 1; $i++) {
            for ($j = 0; $j < $n2 - 1; $j++) {
                if ($this->segmentsIntersect($ring1[$i], $ring1[$i + 1], $ring2[$j], $ring2[$j + 1])) {
                    return true;
                }
            }
        }

        if ($this->pointInPolygon($ring1[0], $ring2) || $this->pointInPolygon($ring2[0], $ring1)) {
            return true;
        }
        return false;
    }

    private function pointInPolygon(array $point, array $ring): bool
    {
        // Algorytm ray-casting
        $x = $point[0];
        $y = $point[1];
        $inside = false;
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $ring[$i][0];
            $yi = $ring[$i][1];
            $xj = $ring[$j][0];
            $yj = $ring[$j][1];
            $intersect = (($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi + 1e-12) + $xi);
            if ($intersect) {
                $inside = !$inside;
            }
        }
        return $inside;
    }

    private function validateMultiPolygon(array $g): bool
    {
        if (!isset($g['coordinates']) || !is_array($g['coordinates'])) {
            return $this->fail("MultiPolygon must have 'coordinates' as an array of Polygon coordinate arrays.");
        }
        if (count($g['coordinates']) < 1) {
            return $this->fail('MultiPolygon must contain at least one Polygon.');
        }
        if ($this->hasHoles($g)) {
            return $this->fail('Multipolygon polygons cannot have holes.');
        }
        foreach ($g['coordinates'] as $pIdx => $poly) {
            if (!is_array($poly) || count($poly) < 1) {
                return $this->fail('Polygon #' . ($pIdx + 1) . ' in MultiPolygon must have at least one LinearRing.');
            }
            foreach ($poly as $rIdx => $ring) {
                if (!$this->isLinearRing($ring)) {
                    return $this->fail(
                        'MultiPolygon polygon #' . ($pIdx + 1) . ' ring #' . ($rIdx + 1) . ' must be a closed LinearRing with ≥3 unique positions.',
                    );
                }
                if ($this->validateIntersection && $this->ringHasSelfIntersections($ring)) {
                    return $this->fail(
                        'MultiPolygon polygon #' . ($pIdx + 1) . ' ring #' . ($rIdx + 1) . ' must not have self-intersections.',
                    );
                }
            }
        }
        if ($this->validateIntersection) {
            $polygons = $g['coordinates'];
            for ($i = 0; $i < count($polygons); $i++) {
                for ($j = $i + 1; $j < count($polygons); $j++) {
                    if ($this->polygonsIntersect($polygons[$i], $polygons[$j])) {
                        return $this->fail(
                            'Polygons in MultiPolygon must not intersect, overlap or contain each other.',
                        );
                    }
                }
            }
        }

        return true;
    }

    private function hasHoles(array $g): bool
    {
        if ($g['type'] === GeoJsonGeometryType::POLYGON) {
            return count($g['coordinates']) > 1;
        } elseif ($g['type'] === GeoJsonGeometryType::MULTIPOLYGON) {
            return count(
                array_filter(
                    $g['coordinates'],
                    fn(array $polygon) => count($polygon) > 1,
                ),
            ) > 0;
        }

        return false;
    }

    /** LinearRing: ≥4 positions & first == last */
    private function isLinearRing($ring): bool
    {
        if (!is_array($ring) || count($ring) < 4 || !$this->allUniqueExceptFirstLast($ring)) {
            return false;
        }

        foreach ($ring as $p) {
            if (!$this->isPosition($p)) {
                return false;
            }
        }
        return $this->positionsEqual($ring[0], $ring[count($ring) - 1]);
    }

    private function allUniqueExceptFirstLast(array $arr): bool
    {
        if (count($arr) <= 2) {
            return true;
        }

        $middle = array_slice($arr, 1, -1);
        $middleStr = array_map(fn($v) => is_array($v) ? implode(',', $v) : (string) $v, $middle);

        return count($middleStr) === count(array_unique($middleStr));
    }

    private function segmentsIntersect(array $p1, array $p2, array $q1, array $q2): bool
    {
        $o1 = $this->orientation($p1, $p2, $q1);
        $o2 = $this->orientation($p1, $p2, $q2);
        $o3 = $this->orientation($q1, $q2, $p1);
        $o4 = $this->orientation($q1, $q2, $p2);
        if ($o1 !== $o2 && $o3 !== $o4) {
            return true;
        }
        return false;
    }

    private function orientation(array $a, array $b, array $c): int
    {
        $val = ($b[1] - $a[1]) * ($c[0] - $b[0]) - ($b[0] - $a[0]) * ($c[1] - $b[1]);
        if ($val === 0) {
            return 0;
        }
        return ($val > 0) ? 1 : 2;
    }

    private function ringHasSelfIntersections(array $ring): bool
    {
        $n = count($ring);
        for ($i = 0; $i < $n - 1; $i++) {
            for ($j = $i + 1; $j < $n - 1; $j++) {
                if (abs($i - $j) <= 1 || ($i === 0 && $j === $n - 2)) {
                    continue;
                }
                if ($this->segmentsIntersect($ring[$i], $ring[$i + 1], $ring[$j], $ring[$j + 1])) {
                    return true;
                }
            }
        }
        return false;
    }
}
