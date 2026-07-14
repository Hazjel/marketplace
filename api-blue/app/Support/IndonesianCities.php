<?php

namespace App\Support;

class IndonesianCities
{
    /**
     * Nama kabupaten/kota Indonesia (tanpa prefiks "Kabupaten"/"Kota").
     * Dipakai untuk ekspansi pencarian destinasi: keyword pendek dicocokkan
     * sebagai prefix ke daftar ini, lalu tiap kota yang cocok di-query ke
     * Komerce (yang hanya mendukung pencarian kata utuh).
     */
    public const NAMES = [
        'Aceh Barat', 'Aceh Besar', 'Aceh Selatan', 'Aceh Tamiang', 'Aceh Tengah', 'Aceh Tenggara', 'Aceh Timur', 'Aceh Utara',
        'Agam', 'Alor', 'Ambon', 'Asahan', 'Asmat',
        'Badung', 'Balangan', 'Balikpapan', 'Banda Aceh', 'Bandar Lampung', 'Bandung', 'Bandung Barat', 'Banggai',
        'Bangka', 'Bangka Barat', 'Bangka Selatan', 'Bangka Tengah', 'Bangkalan', 'Bangli', 'Banjar', 'Banjarbaru',
        'Banjarmasin', 'Banjarnegara', 'Bantaeng', 'Bantul', 'Banyuasin', 'Banyumas', 'Banyuwangi', 'Barito Kuala',
        'Barito Selatan', 'Barito Timur', 'Barito Utara', 'Barru', 'Batam', 'Batang', 'Batang Hari', 'Batu', 'Batu Bara',
        'Baubau', 'Bekasi', 'Belitung', 'Belitung Timur', 'Belu', 'Bener Meriah', 'Bengkalis', 'Bengkayang', 'Bengkulu',
        'Bengkulu Selatan', 'Bengkulu Tengah', 'Bengkulu Utara', 'Berau', 'Biak Numfor', 'Bima', 'Binjai', 'Bintan',
        'Bireuen', 'Bitung', 'Blitar', 'Blora', 'Boalemo', 'Bogor', 'Bojonegoro', 'Bolaang Mongondow', 'Bombana',
        'Bondowoso', 'Bone', 'Bone Bolango', 'Bontang', 'Boven Digoel', 'Boyolali', 'Brebes', 'Bukittinggi', 'Buleleng',
        'Bulukumba', 'Bulungan', 'Bungo', 'Buol', 'Buru', 'Buton',
        'Ciamis', 'Cianjur', 'Cilacap', 'Cilegon', 'Cimahi', 'Cirebon',
        'Deiyai', 'Deli Serdang', 'Demak', 'Denpasar', 'Depok', 'Dharmasraya', 'Dogiyai', 'Dompu', 'Donggala', 'Dumai',
        'Empat Lawang', 'Ende', 'Enrekang',
        'Fakfak', 'Flores Timur',
        'Garut', 'Gayo Lues', 'Gianyar', 'Gorontalo', 'Gorontalo Utara', 'Gowa', 'Gresik', 'Grobogan', 'Gunung Kidul',
        'Gunung Mas', 'Gunungsitoli',
        'Halmahera Barat', 'Halmahera Selatan', 'Halmahera Tengah', 'Halmahera Timur', 'Halmahera Utara', 'Hulu Sungai Selatan',
        'Hulu Sungai Tengah', 'Hulu Sungai Utara', 'Humbang Hasundutan',
        'Indragiri Hilir', 'Indragiri Hulu', 'Indramayu', 'Intan Jaya',
        'Jakarta Barat', 'Jakarta Pusat', 'Jakarta Selatan', 'Jakarta Timur', 'Jakarta Utara', 'Jambi', 'Jayapura', 'Jayawijaya',
        'Jember', 'Jembrana', 'Jeneponto', 'Jepara', 'Jombang',
        'Kampar', 'Kapuas', 'Kapuas Hulu', 'Karanganyar', 'Karangasem', 'Karawang', 'Karimun', 'Karo', 'Katingan', 'Kaur',
        'Kebumen', 'Kediri', 'Keerom', 'Kendal', 'Kendari', 'Kepahiang', 'Kepulauan Anambas', 'Kepulauan Aru',
        'Kepulauan Mentawai', 'Kepulauan Meranti', 'Kepulauan Sangihe', 'Kepulauan Selayar', 'Kepulauan Seribu',
        'Kepulauan Sula', 'Kepulauan Talaud', 'Kepulauan Yapen', 'Kerinci', 'Ketapang', 'Klaten', 'Klungkung', 'Kolaka',
        'Kolaka Utara', 'Konawe', 'Konawe Selatan', 'Konawe Utara', 'Kotabaru', 'Kotamobagu', 'Kotawaringin Barat',
        'Kotawaringin Timur', 'Kuantan Singingi', 'Kubu Raya', 'Kudus', 'Kulon Progo', 'Kuningan', 'Kupang', 'Kutai Barat',
        'Kutai Kartanegara', 'Kutai Timur',
        'Labuhanbatu', 'Labuhanbatu Selatan', 'Labuhanbatu Utara', 'Lahat', 'Lamandau', 'Lamongan', 'Lampung Barat',
        'Lampung Selatan', 'Lampung Tengah', 'Lampung Timur', 'Lampung Utara', 'Landak', 'Langkat', 'Langsa', 'Lanny Jaya',
        'Lebak', 'Lebong', 'Lembata', 'Lhokseumawe', 'Lima Puluh Kota', 'Lingga', 'Lombok Barat', 'Lombok Tengah',
        'Lombok Timur', 'Lombok Utara', 'Lubuklinggau', 'Lumajang', 'Luwu', 'Luwu Timur', 'Luwu Utara',
        'Madiun', 'Magelang', 'Magetan', 'Majalengka', 'Majene', 'Makassar', 'Malang', 'Malinau', 'Maluku Barat Daya',
        'Maluku Tengah', 'Maluku Tenggara', 'Mamasa', 'Mamberamo Raya', 'Mamberamo Tengah', 'Mamuju', 'Mamuju Utara',
        'Manado', 'Mandailing Natal', 'Manggarai', 'Manggarai Barat', 'Manggarai Timur', 'Manokwari', 'Mappi', 'Maros',
        'Mataram', 'Maybrat', 'Medan', 'Melawi', 'Mempawah', 'Merangin', 'Merauke', 'Mesuji', 'Metro', 'Mimika',
        'Minahasa', 'Minahasa Selatan', 'Minahasa Tenggara', 'Minahasa Utara', 'Mojokerto', 'Morowali', 'Muara Enim',
        'Muaro Jambi', 'Mukomuko', 'Muna', 'Murung Raya', 'Musi Banyuasin', 'Musi Rawas',
        'Nabire', 'Nagan Raya', 'Nagekeo', 'Natuna', 'Nduga', 'Ngada', 'Nganjuk', 'Ngawi', 'Nias', 'Nias Barat',
        'Nias Selatan', 'Nias Utara', 'Nunukan',
        'Ogan Ilir', 'Ogan Komering Ilir', 'Ogan Komering Ulu',
        'Pacitan', 'Padang', 'Padang Lawas', 'Padang Lawas Utara', 'Padang Panjang', 'Padang Pariaman', 'Padangsidimpuan',
        'Pagar Alam', 'Painan', 'Pakpak Bharat', 'Palangka Raya', 'Palembang', 'Palopo', 'Palu', 'Pamekasan', 'Pandeglang',
        'Pangandaran', 'Pangkajene Dan Kepulauan', 'Pangkalpinang', 'Paniai', 'Parepare', 'Pariaman', 'Parigi Moutong',
        'Pasaman', 'Pasaman Barat', 'Paser', 'Pasuruan', 'Pati', 'Payakumbuh', 'Pegunungan Arfak', 'Pegunungan Bintang',
        'Pekalongan', 'Pekanbaru', 'Pelalawan', 'Pemalang', 'Pematangsiantar', 'Penajam Paser Utara', 'Pesawaran',
        'Pesisir Barat', 'Pesisir Selatan', 'Pidie', 'Pidie Jaya', 'Pinrang', 'Pohuwato', 'Polewali Mandar', 'Ponorogo',
        'Pontianak', 'Poso', 'Prabumulih', 'Pringsewu', 'Probolinggo', 'Pulang Pisau', 'Pulau Morotai', 'Puncak',
        'Puncak Jaya', 'Purbalingga', 'Purwakarta', 'Purworejo',
        'Raja Ampat', 'Rejang Lebong', 'Rembang', 'Riau', 'Rokan Hilir', 'Rokan Hulu', 'Rote Ndao',
        'Sabang', 'Salatiga', 'Samarinda', 'Sambas', 'Samosir', 'Sampang', 'Sanggau', 'Sarmi', 'Sarolangun', 'Sawahlunto',
        'Sekadau', 'Semarang', 'Seram Bagian Barat', 'Seram Bagian Timur', 'Serang', 'Serdang Bedagai', 'Seruyan',
        'Siak', 'Sibolga', 'Sidenreng Rappang', 'Sidoarjo', 'Sigi', 'Sijunjung', 'Sikka', 'Simalungun', 'Simeulue',
        'Singkawang', 'Sinjai', 'Sintang', 'Situbondo', 'Sleman', 'Solok', 'Solok Selatan', 'Soppeng', 'Sorong',
        'Sorong Selatan', 'Sragen', 'Subang', 'Subulussalam', 'Sukabumi', 'Sukamara', 'Sukoharjo', 'Sumbawa',
        'Sumbawa Barat', 'Sumba Barat', 'Sumba Barat Daya', 'Sumba Tengah', 'Sumba Timur', 'Sumedang', 'Sumenep',
        'Sungai Penuh', 'Supiori', 'Surabaya', 'Surakarta',
        'Tabalong', 'Tabanan', 'Takalar', 'Tambrauw', 'Tana Tidung', 'Tana Toraja', 'Tanah Bumbu', 'Tanah Datar',
        'Tanah Laut', 'Tangerang', 'Tangerang Selatan', 'Tanggamus', 'Tanjungbalai', 'Tanjung Jabung Barat',
        'Tanjung Jabung Timur', 'Tanjungpinang', 'Tapanuli Selatan', 'Tapanuli Tengah', 'Tapanuli Utara', 'Tapin',
        'Tarakan', 'Tasikmalaya', 'Tebing Tinggi', 'Tebo', 'Tegal', 'Teluk Bintuni', 'Teluk Wondama', 'Temanggung',
        'Ternate', 'Tidore Kepulauan', 'Timor Tengah Selatan', 'Timor Tengah Utara', 'Toba Samosir', 'Tojo Una-Una',
        'Tolikara', 'Toli-Toli', 'Tomohon', 'Toraja Utara', 'Trenggalek', 'Tuban', 'Tulang Bawang', 'Tulang Bawang Barat',
        'Tulungagung',
        'Wajo', 'Wakatobi', 'Waropen', 'Way Kanan', 'Wonogiri', 'Wonosobo',
        'Yahukimo', 'Yalimo', 'Yogyakarta',
    ];

    /**
     * Cari nama kota yang diawali prefix (case-insensitive).
     *
     * @return array<string>
     */
    public static function matchPrefix(string $prefix, int $limit = 3): array
    {
        $prefix = mb_strtolower(trim($prefix));
        if ($prefix === '') {
            return [];
        }

        $matches = [];
        foreach (self::NAMES as $name) {
            if (str_starts_with(mb_strtolower($name), $prefix)) {
                $matches[] = $name;
                if (count($matches) >= $limit) {
                    break;
                }
            }
        }

        return $matches;
    }
}
