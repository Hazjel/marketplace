<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BackfillCoordinates extends Command
{
    protected $signature = 'geo:backfill {--dry-run : Tampilkan yang akan diisi tanpa menyimpan}';

    protected $description = 'Isi latitude/longitude toko & alamat lama dari nama kota (geocode Nominatim, level kota)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $stores = Store::whereNull('latitude')->whereNotNull('city')->where('city', '!=', '')->get();
        $addresses = Address::whereNull('latitude')->whereNotNull('city')->where('city', '!=', '')->get();

        $this->info(sprintf('Toko tanpa koordinat: %d | Alamat tanpa koordinat: %d', $stores->count(), $addresses->count()));

        $filled = 0;
        $failed = 0;

        foreach ([['Toko', $stores], ['Alamat', $addresses]] as [$label, $rows]) {
            foreach ($rows as $row) {
                $coords = $this->geocodeCity($row->city);

                if ($coords === null) {
                    $this->warn("  ✗ {$label} '{$row->city}' tidak ketemu");
                    $failed++;

                    continue;
                }

                if (! $dry) {
                    $row->latitude = $coords['lat'];
                    $row->longitude = $coords['lon'];
                    $row->save();
                }

                $this->line(sprintf('  ✓ %s %s -> %.5f, %.5f%s', $label, $row->city, $coords['lat'], $coords['lon'], $dry ? ' (dry-run)' : ''));
                $filled++;
            }
        }

        $this->info("Selesai: {$filled} terisi, {$failed} gagal.".($dry ? ' (dry-run, tidak ada yang disimpan)' : ''));

        return self::SUCCESS;
    }

    /**
     * Geocode nama kota. Cache 7 hari (key sama dengan ShipmentController),
     * jeda 1 detik antar request non-cache sesuai rate limit Nominatim.
     *
     * @return array{lat: float, lon: float}|null
     */
    private function geocodeCity(string $city): ?array
    {
        $cacheKey = 'geocode:'.md5(mb_strtolower($city));

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            return $cached ? ['lat' => (float) $cached['lat'], 'lon' => (float) $cached['lon']] : null;
        }

        sleep(2); // Nominatim: maks 1 request/detik — 2s untuk margin aman

        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Marketplace/1.0 (contact@marketplace.id)'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $city.', Indonesia',
                    'format' => 'json',
                    'limit' => 1,
                ]);

            // Rate limit / error server: jangan di-cache, coba lagi lain waktu
            if (! $response->successful()) {
                $this->warn("  ! HTTP {$response->status()} untuk '{$city}' — dilewati (tidak di-cache)");

                return null;
            }

            $data = $response->json();

            if (empty($data[0]['lat'])) {
                Cache::put($cacheKey, null, now()->addDays(7));

                return null;
            }

            $result = ['lat' => $data[0]['lat'], 'lon' => $data[0]['lon']];
            Cache::put($cacheKey, $result, now()->addDays(7));

            return ['lat' => (float) $result['lat'], 'lon' => (float) $result['lon']];
        } catch (\Throwable $e) {
            $this->warn('  ! Geocode error: '.$e->getMessage());

            return null;
        }
    }
}
