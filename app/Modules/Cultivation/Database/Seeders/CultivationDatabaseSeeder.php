<?php

namespace App\Modules\Cultivation\Database\Seeders;

use App\Modules\Cultivation\Models\CultivationEra;
use App\Modules\Cultivation\Models\CultivationRealm;
use App\Modules\Cultivation\Models\CultivationStage;
use Illuminate\Database\Seeder;

/**
 * Isi 5 Era, 20 Realm, dan generate 200 Stage otomatis (bukan ditulis
 * manual satu-satu — lihat dokumentasi ERD poin 14 "Stage Generation").
 * Aman dijalankan berulang (pakai updateOrCreate).
 */
class CultivationDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $eras = [
            ['name' => 'Mortal Era', 'resource_name' => 'Essence', 'resource_slug' => 'essence', 'sort_order' => 1],
            ['name' => 'Astral Era', 'resource_name' => 'Astrum', 'resource_slug' => 'astrum', 'sort_order' => 2],
            ['name' => 'Cosmic Era', 'resource_name' => 'Cosmos', 'resource_slug' => 'cosmos', 'sort_order' => 3],
            ['name' => 'World Era', 'resource_name' => 'Worldforce', 'resource_slug' => 'worldforce', 'sort_order' => 4],
            ['name' => 'Transcendent Era', 'resource_name' => 'Origin', 'resource_slug' => 'origin', 'sort_order' => 5],
        ];

        $eraIds = [];
        foreach ($eras as $era) {
            $eraIds[$era['sort_order']] = CultivationEra::updateOrCreate(
                ['sort_order' => $era['sort_order']],
                $era
            )->id;
        }

        // Lore/description tiap Realm, keyed by nama Realm (unik per Era).
        // Nowdoc (<<<'TXT') supaya newline & tanda kutip di teks tidak
        // perlu di-escape.
        $descriptions = [
            'Awakened' => <<<'TXT'
Setiap perjalanan dimulai dengan sebuah kesadaran.

Di Realm Awakened, seorang cultivator mulai merasakan energi yang sebelumnya tersembunyi di dalam tubuhnya. Mereka belajar mengendalikan Essence, memperkuat tubuh, dan membuka jalur energi pertama mereka.

Ini adalah langkah pertama untuk memasuki dunia kultivasi.
TXT,
            'Gatherer' => <<<'TXT'
Setelah menyadari keberadaan energi, langkah berikutnya adalah mengumpulkannya.

Cultivator mulai menarik Essence dari lingkungan dan menyimpannya di dalam tubuh. Semakin banyak energi yang dapat dikumpulkan dan dikendalikan, semakin kuat fondasi kultivasinya.
TXT,
            'Core' => <<<'TXT'
Essence yang telah dikumpulkan mulai dipadatkan menjadi sebuah Core.

Core menjadi pusat kekuatan seorang cultivator. Energi tidak lagi sekadar mengalir melalui tubuh, tetapi mulai memiliki bentuk dan kestabilan.

Dengan terbentuknya Core, seorang cultivator meninggalkan tahap dasar kultivasi.
TXT,
            'Astral' => <<<'TXT'
Tubuh mulai menjadi wadah bagi energi yang jauh lebih besar.

Pada tahap Astral, cultivator memperluas jalur energinya dan membangun Astral Vessel—fondasi yang memungkinkan tubuh menampung kekuatan yang melampaui batas manusia biasa.

Langit mulai terasa lebih dekat.
TXT,
            'Starforged' => <<<'TXT'
Cultivator kini mulai menyentuh kekuatan bintang.

Astrum mulai menggantikan Essence sebagai sumber energi utama. Energi bintang ditempa ke dalam tubuh dan Core, membuat kekuatan seorang cultivator semakin padat dan tahan terhadap tekanan kosmik.

Untuk pertama kalinya, seorang cultivator mulai menempa dirinya dengan kekuatan langit.
TXT,
            'Soul' => <<<'TXT'
Kekuatan tidak lagi hanya berada di tubuh.

Pada Realm Soul, kesadaran dan jiwa mulai beresonansi dengan Astrum. Cultivator belajar mempertahankan eksistensinya bahkan ketika tubuh tidak lagi menjadi satu-satunya sumber kekuatan.

Jiwa menjadi pusat baru dari perjalanan kultivasi.
TXT,
            'Star Lord' => <<<'TXT'
Seorang cultivator tidak lagi sekadar menggunakan kekuatan bintang.

Mereka mulai mampu mengendalikan dan memerintah energi astral di sekitarnya. Kehadiran mereka menjadi cukup kuat untuk memengaruhi medan energi di sekitar mereka.

Mereka telah mengambil langkah pertama menuju penguasaan kosmik.
TXT,
            'Nebula' => <<<'TXT'
Energi di dalam tubuh mulai menyerupai sebuah nebula.

Astrum berputar, menyebar, dan berkumpul membentuk lautan energi yang jauh lebih luas. Kekuatan seorang cultivator tidak lagi terasa seperti sebuah inti tunggal, tetapi seperti sebuah sistem kosmik kecil.

Batas antara cultivator dan langit mulai menghilang.
TXT,
            'Galaxy' => <<<'TXT'
Tubuh dan jiwa mulai berkembang menjadi sebuah sistem yang menyerupai galaksi.

Cosmos menjadi sumber energi baru. Energi dapat bergerak dalam pola yang kompleks, menciptakan kekuatan yang jauh melampaui Astral Era.

Seorang cultivator pada tahap ini telah memasuki wilayah kekuatan kosmik yang sebenarnya.
TXT,
            'Celestial' => <<<'TXT'
Cultivator mulai menyentuh hukum energi yang lebih tinggi.

Kekuatan Cosmos tidak lagi hanya digunakan untuk memperkuat tubuh. Ia mulai membentuk hubungan dengan hukum dan fenomena kosmik di sekitarnya.

Mencapai Celestial berarti berdiri di ambang dunia yang jauh lebih besar.
TXT,
            'Domain' => <<<'TXT'
Kekuatan kini dapat membentuk sebuah Domain.

Di dalam Domain miliknya, cultivator mampu memengaruhi energi dan aturan tertentu di sekitarnya. Dunia tidak lagi hanya menjadi tempat bagi cultivator untuk bertarung—dunia mulai merespons keberadaan mereka.
TXT,
            'Worldheart' => <<<'TXT'
Cultivator menemukan pusat kekuatan yang lebih besar dari sekadar Domain.

Worldheart adalah inti yang mampu menopang energi dalam skala dunia. Pada tahap ini, seorang cultivator mulai memahami hubungan antara kehidupan, energi, dan struktur sebuah dunia.

Satu langkah lagi, dan dunia itu sendiri dapat menjadi bagian dari kekuatannya.
TXT,
            'World Lord' => <<<'TXT'
Cultivator kini mampu memengaruhi kekuatan sebuah dunia.

Worldforce menjadi sumber energi utama, memungkinkan mereka membangun pengaruh yang jauh melampaui satu tubuh atau satu Domain.

Mereka bukan lagi sekadar penghuni dunia.

Mereka telah menjadi penguasa kekuatan dunia.
TXT,
            'Void' => <<<'TXT'
Untuk melampaui dunia, seorang cultivator harus memahami kekosongan.

Pada Realm Void, mereka mulai berinteraksi dengan ruang kosong di antara dunia dan memahami bahwa tidak semua kekuatan berasal dari sesuatu yang terlihat.

Ketiadaan pun memiliki kekuatan.
TXT,
            'Dimensional' => <<<'TXT'
Ruang bukan lagi sebuah batas.

Cultivator mulai memahami dan memengaruhi struktur dimensi. Jarak, ruang, dan batas antar-dunia menjadi sesuatu yang dapat mereka pahami dan manipulasi.

Mereka mulai berdiri di luar aturan ruang biasa.
TXT,
            'Eternal' => <<<'TXT'
Waktu mulai kehilangan maknanya.

Pada Realm Eternal, cultivator memperkuat eksistensinya hingga mampu bertahan terhadap perubahan yang menghancurkan makhluk biasa.

Mereka belum benar-benar abadi, tetapi keberadaan mereka telah mulai melampaui batas kehidupan normal.
TXT,
            'Origin' => <<<'TXT'
Setelah melampaui dunia, cultivator mulai mencari sumber dari segala sesuatu.

Origin adalah energi primordial yang berada di balik penciptaan. Cultivator mulai memahami bahwa dunia, energi, dan kehidupan hanyalah bagian dari sesuatu yang jauh lebih fundamental.

Di sinilah perjalanan menuju Transcendence benar-benar dimulai.
TXT,
            'Sovereign' => <<<'TXT'
Cultivator telah memperoleh otoritas atas kekuatan yang melampaui dunia.

Mereka mampu memanfaatkan Origin untuk membentuk, mengubah, dan mengendalikan kekuatan dalam skala kosmik.

Namun kekuasaan bukanlah akhir dari perjalanan.

Di atas seorang Sovereign masih terdapat batas terakhir yang harus ditembus.
TXT,
            'Transcendent' => <<<'TXT'
Batas terakhir mulai runtuh.

Cultivator tidak lagi sepenuhnya terikat oleh aturan dunia, ruang, waktu, ataupun bentuk eksistensi biasa.

Transcendence bukan sekadar menjadi lebih kuat.

Ini adalah proses meninggalkan batas yang selama ini mendefinisikan keberadaan mereka.
TXT,
            'Cosmic' => <<<'TXT'
Puncak dari Cosmic Cultivation.

Seorang cultivator yang mencapai Cosmic telah melewati seluruh perjalanan—dari merasakan energi pertama hingga memahami Origin yang berada di balik keberadaan.

Tidak ada Realm setelah Cosmic.

Tidak ada Stage setelah Stage 10.

Ini adalah akhir dari jalur kultivasi.

Dan mungkin...

awal dari sesuatu yang bahkan belum memiliki nama.
TXT,
        ];

        // [era_sort_order, name, full_name, level_start, level_end, realm_sort_order]
        // stage_required & realm_total_required dihitung dari formula,
        // bukan ditulis manual — konsisten dengan ERD poin 2 & 15.
        // Lore lengkap tiap realm ada di $descriptions di atas, di-map by name.
        $realms = [
            [1, 'Awakened', 'Awakening Realm', 1, 10, 1],
            [1, 'Gatherer', 'Energy Gathering Realm', 11, 20, 2],
            [1, 'Core', 'Energy Core Realm', 21, 30, 3],
            [1, 'Astral', 'Astral Vessel Realm', 31, 40, 4],

            [2, 'Starforged', 'Starforged Realm', 41, 50, 5],
            [2, 'Soul', 'Astral Soul Realm', 51, 60, 6],
            [2, 'Star Lord', 'Star Lord Realm', 61, 70, 7],
            [2, 'Nebula', 'Nebula Realm', 71, 80, 8],

            [3, 'Galaxy', 'Galaxy Realm', 81, 90, 9],
            [3, 'Celestial', 'Celestial Realm', 91, 100, 10],
            [3, 'Domain', 'Cosmic Domain Realm', 101, 110, 11],
            [3, 'Worldheart', 'Worldheart Realm', 111, 120, 12],

            [4, 'World Lord', 'World Lord Realm', 121, 130, 13],
            [4, 'Void', 'Void Realm', 131, 140, 14],
            [4, 'Dimensional', 'Dimensional Realm', 141, 150, 15],
            [4, 'Eternal', 'Eternal Realm', 151, 160, 16],

            [5, 'Origin', 'Origin Realm', 161, 170, 17],
            [5, 'Sovereign', 'Cosmic Sovereign Realm', 171, 180, 18],
            [5, 'Transcendent', 'Transcendent Realm', 181, 190, 19],
            [5, 'Cosmic', 'Cosmic Realm', 191, 200, 20],
        ];

        $this->command?->info('Seeding '.count($realms).' cultivation realms...');

        foreach ($realms as [$eraSortOrder, $name, $fullName, $levelStart, $levelEnd, $realmSortOrder]) {
            // stage_required = 100 * 2^(realm_id - 1) — realm_id di sini
            // adalah sort_order (1-20), sesuai formula ERD poin 2 & 15.
            $stageRequired = 100 * (2 ** ($realmSortOrder - 1));
            $realmTotalRequired = $stageRequired * 10;

            $realm = CultivationRealm::updateOrCreate(
                ['sort_order' => $realmSortOrder],
                [
                    'era_id' => $eraIds[$eraSortOrder],
                    'name' => $name,
                    'full_name' => $fullName,
                    'description' => $descriptions[$name] ?? null,
                    'level_start' => $levelStart,
                    'level_end' => $levelEnd,
                    'stage_required' => $stageRequired,
                    'realm_total_required' => $realmTotalRequired,
                ]
            );

            // Generate 10 Stage otomatis per Realm (ERD poin 14).
            for ($stage = 1; $stage <= 10; $stage++) {
                $level = $realm->level_start + $stage - 1;

                CultivationStage::updateOrCreate(
                    ['realm_id' => $realm->id, 'stage' => $stage],
                    [
                        'level' => $level,
                        'start_progress' => 0,
                        'end_progress' => $stageRequired,
                    ]
                );
            }
        }

        $this->command?->info('Seeding cultivation selesai — 5 era, 20 realm, 200 stage.');
    }
}
