<?php

namespace App\Interfaces;

interface ShippingGatewayInterface
{
    /**
     * Cari destinasi (kecamatan/kelurahan) berdasar keyword, dipakai untuk
     * autocomplete alamat. Return array of destination rows dari provider.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchDestinations(string $keyword): array;

    /**
     * Hitung ongkir kurir antara dua destinasi. Return array of courier
     * cost rows (kosong kalau tidak ada kurir yang tersedia/rute tidak
     * dikenal provider).
     *
     * @return array<int, array<string, mixed>>
     */
    public function calculateCosts(int $originId, int $destinationId, int $weightGrams, ?string $destinationCityName = null): array;
}
