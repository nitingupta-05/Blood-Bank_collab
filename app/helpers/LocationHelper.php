<?php
/**
 * Geographic helpers (Haversine distance, validation).
 */
class LocationHelper {

    /**
     * Distance in kilometers between two coordinates.
     */
    public static function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
        $earth = 6371.0;
        $dLat  = deg2rad($lat2 - $lat1);
        $dLon  = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earth * $c, 2);
    }

    public static function findNearby(float $lat, float $lon, array $locations, int $radius = EMERGENCY_RADIUS): array {
        $out = [];
        foreach ($locations as $loc) {
            if (!isset($loc['latitude'], $loc['longitude'])) continue;
            $d = self::calculateDistance($lat, $lon, (float) $loc['latitude'], (float) $loc['longitude']);
            if ($d <= $radius) {
                $loc['distance_km'] = $d;
                $out[] = $loc;
            }
        }
        usort($out, fn($a, $b) => ($a['distance_km'] ?? 0) <=> ($b['distance_km'] ?? 0));
        return $out;
    }

    public static function validateCoordinates($lat, $lon): bool {
        return is_numeric($lat) && is_numeric($lon)
            && $lat >= -90 && $lat <= 90
            && $lon >= -180 && $lon <= 180;
    }
}
