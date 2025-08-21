<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Enums;

use Emoti\CommonResources\Traits\ArrayableEnumTrait;

enum GeoJsonGeometryType: string
{
    use ArrayableEnumTrait;

    case POINT = 'Point';
    case MULTIPOINT = 'MultiPoint';
    case LINE = 'LineString';
    case MULTILINE = 'MultiLineString';
    case POLYGON = 'Polygon';
    case MULTIPOLYGON = 'MultiPolygon';
    case GEOMETRY_COLLECTION = 'GeometryCollection';
}
