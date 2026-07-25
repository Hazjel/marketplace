<?php

namespace App\Interfaces;

interface GeocodingGatewayInterface
{
    /**
     * Geocode nama kota jadi koordinat. Return ['lat' => ?string, 'lon' => ?string]
     * (null kalau tidak ditemukan).
     *
     * @return array{lat: ?string, lon: ?string}
     */
    public function geocode(string $city): array;

    /**
     * Reverse geocode koordinat jadi alamat. Return null kalau lokasi tidak
     * ditemukan.
     *
     * @return array{display_name: ?string, road: ?string, suburb: ?string, city: ?string, state: ?string, postal_code: ?string}|null
     */
    public function reverseGeocode(float $lat, float $lon): ?array;
}
