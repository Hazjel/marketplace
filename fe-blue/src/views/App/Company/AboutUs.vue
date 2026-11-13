<script setup>
import { onMounted } from 'vue'

// Scroll to top on mount
onMounted(() => {
  window.scrollTo(0, 0)
})
</script>

<template>
  <main class="w-full bg-white dark:bg-gray-950 pb-24 md:pb-32 overflow-hidden">
    <!-- Hero Section -->
    <section
      class="relative w-full min-h-[600px] flex items-center justify-center overflow-hidden pb-20 md:pb-0"
    >
      <!-- Background Image -->
      <div class="absolute inset-0">
        <img
          src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?q=80&w=2071&auto=format&fit=crop"
          class="w-full h-full object-cover scale-105 animate-slow-zoom"
          alt="Banner Tentang Kami"
        />
        <div
          class="absolute inset-0 bg-gradient-to-r from-gray-950/90 via-gray-950/70 to-transparent"
        ></div>
      </div>

      <!-- Content -->
      <div class="relative z-10 w-full max-w-[1280px] px-5 md:px-[52px] pt-20 pb-20 md:pb-32">
        <div class="max-w-3xl flex flex-col gap-6 animate-fade-in-up">
          <span
            class="inline-block px-4 py-2 bg-white/10 backdrop-blur-md rounded-full text-blue-400 font-medium text-sm tracking-widest border border-white/20 w-fit"
          >
            SEJAK 2024
          </span>
          <h1 class="text-5xl md:text-7xl font-medium text-white leading-tight">
            Menghubungkan
            <span
              class="text-transparent bg-clip-text bg-gradient-to-r from-[#024ad8] to-teal-400"
              >Ambisi</span
            >,<br />
            Menghadirkan
            <span
              class="text-transparent bg-clip-text bg-gradient-to-r from-orange-500 to-red-400"
              >Kepercayaan</span
            >.
          </h1>
          <p
            class="text-xl text-gray-300 leading-relaxed max-w-2xl border-l-4 border-[#024ad8] pl-6"
          >
            Kami lebih dari sekadar marketplace. Kami adalah jembatan antara kerajinan lokal dan peluang global.
          </p>
        </div>
      </div>
    </section>

    <!-- Stats Section (Floating) -->
    <section class="relative z-20 -mt-16 px-5 md:px-[52px]">
      <div
        class="max-w-[1280px] mx-auto bg-white dark:bg-gray-900 rounded-2xl shadow-xl border border-gray-100 dark:border-white/10 p-8 md:p-12 grid grid-cols-2 lg:grid-cols-4 gap-8"
      >
        <div class="flex flex-col items-center text-center gap-2 group">
          <span
            class="text-4xl md:text-5xl font-medium text-gray-900 dark:text-white group-hover:text-[#024ad8] transition-colors duration-300"
            >10K+</span
          >
          <span class="text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wider text-xs"
            >Seller Aktif</span
          >
        </div>
        <div class="flex flex-col items-center text-center gap-2 group">
          <span
            class="text-4xl md:text-5xl font-medium text-gray-900 dark:text-white group-hover:text-orange-500 transition-colors duration-300"
            >500K+</span
          >
          <span class="text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wider text-xs"
            >Pelanggan Puas</span
          >
        </div>
        <div class="flex flex-col items-center text-center gap-2 group">
          <span
            class="text-4xl md:text-5xl font-medium text-gray-900 dark:text-white group-hover:text-emerald-500 transition-colors duration-300"
            >2M+</span
          >
          <span class="text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wider text-xs"
            >Transaksi</span
          >
        </div>
        <div class="flex flex-col items-center text-center gap-2 group">
          <span
            class="text-4xl md:text-5xl font-medium text-gray-900 dark:text-white group-hover:text-purple-500 transition-colors duration-300"
            >99%</span
          >
          <span class="text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wider text-xs"
            >Tingkat Kepuasan</span
          >
        </div>
      </div>
    </section>

    <!-- Our Story (Zig Zag) -->
    <section class="max-w-[1280px] mx-auto px-5 md:px-[52px] py-24 flex flex-col gap-24">
      <!-- Row 1 -->
      <div class="flex flex-col lg:flex-row gap-12 lg:gap-20 items-center">
        <div class="w-full lg:w-1/2 relative group">
          <div
            class="absolute -inset-4 bg-[#024ad8]/20 rounded-[40px] -rotate-3 transition-transform group-hover:rotate-0 duration-500"
          ></div>
          <img
            src="https://images.unsplash.com/photo-1556761175-5973dc0f32e7?q=80&w=1932&auto=format&fit=crop"
            class="relative rounded-2xl shadow-2xl w-full h-auto object-cover aspect-[4/3] rotate-2 transition-transform group-hover:rotate-0 duration-500"
            alt="Tim"
          />
        </div>
        <div class="w-full lg:w-1/2 flex flex-col gap-6">
          <span class="text-[#024ad8] font-medium tracking-widest uppercase text-sm"
            >Awal Mula Kami</span
          >
          <h2 class="text-3xl md:text-5xl font-medium text-gray-900 dark:text-white leading-tight">
            Dibangun untuk Komunitas,<br />oleh Komunitas.
          </h2>
          <p class="text-gray-600 dark:text-gray-400 text-lg leading-relaxed">
            Blukios tidak lahir di ruang rapat. Semuanya bermula dari sebuah garasi kecil di Kendal, saat kami melihat sulitnya penjual lokal menghadapi platform digital yang rumit.
          </p>
          <p class="text-gray-600 dark:text-gray-400 text-lg leading-relaxed">
            Kami mengajukan satu pertanyaan sederhana:
            <span class="text-gray-900 dark:text-white font-medium italic"
              >"Mengapa berjualan online harus sesulit ini?"</span
            >
            Rasa ingin tahu itu kami jadikan bahan bakar inovasi.
          </p>
        </div>
      </div>

      <!-- Row 2 -->
      <div class="flex flex-col-reverse lg:flex-row gap-12 lg:gap-20 items-center">
        <div class="w-full lg:w-1/2 flex flex-col gap-6 lg:pl-10">
          <span class="text-orange-500 font-medium tracking-widest uppercase text-sm"
            >Visi Kami</span
          >
          <h2 class="text-3xl md:text-5xl font-medium text-gray-900 dark:text-white leading-tight">
            Teknologi dengan <br />Sentuhan Manusia.
          </h2>
          <p class="text-gray-600 dark:text-gray-400 text-lg leading-relaxed">
            Kami percaya teknologi seharusnya memberdayakan, bukan merumitkan. Setiap fitur kami rancang dengan empati kepada pengguna. Dari checkout yang mulus hingga dashboard seller yang intuitif, kesederhanaan adalah kecanggihan tertinggi kami.
          </p>
          <ul class="flex flex-col gap-3 mt-4">
            <li class="flex items-center gap-3">
              <div
                class="w-6 h-6 rounded-full bg-emerald-100 dark:bg-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-xs"
              >
                <i class="fa-solid fa-check"></i>
              </div>
              <span class="font-medium text-gray-900 dark:text-white">Tanpa hambatan pengetahuan teknis</span>
            </li>
            <li class="flex items-center gap-3">
              <div
                class="w-6 h-6 rounded-full bg-emerald-100 dark:bg-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-xs"
              >
                <i class="fa-solid fa-check"></i>
              </div>
              <span class="font-medium text-gray-900 dark:text-white">Model harga yang transparan</span>
            </li>
            <li class="flex items-center gap-3">
              <div
                class="w-6 h-6 rounded-full bg-emerald-100 dark:bg-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-xs"
              >
                <i class="fa-solid fa-check"></i>
              </div>
              <span class="font-medium text-gray-900 dark:text-white">Pengembangan yang digerakkan komunitas</span>
            </li>
          </ul>
        </div>
        <div class="w-full lg:w-1/2 relative group">
          <div
            class="absolute -inset-4 bg-orange-500/20 rounded-[40px] rotate-3 transition-transform group-hover:rotate-0 duration-500"
          ></div>
          <img
            src="https://images.unsplash.com/photo-1531482615713-2afd69097998?q=80&w=2070&auto=format&fit=crop"
            class="relative rounded-2xl shadow-2xl w-full h-auto object-cover aspect-[4/3] -rotate-2 transition-transform group-hover:rotate-0 duration-500"
            alt="Teknologi"
          />
        </div>
      </div>
    </section>

    <!-- Values Cards -->
    <section class="bg-gradient-to-b from-gray-50 dark:from-gray-900 to-white dark:to-gray-950 py-24">
      <div class="max-w-[1280px] mx-auto px-5 md:px-[52px]">
        <div class="text-center max-w-2xl mx-auto mb-20">
          <h2 class="text-4xl font-medium text-gray-900 dark:text-white mb-6">Nilai-Nilai Kami</h2>
          <p class="text-gray-600 dark:text-gray-400 text-lg">Prinsip yang memandu setiap keputusan kami.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <!-- Value 1 -->
          <div
            class="bg-white dark:bg-gray-900 p-10 rounded-2xl shadow-sm border border-gray-100 dark:border-white/10 hover:shadow-xl hover:-translate-y-2 transition-all duration-300 group"
          >
            <div
              class="w-16 h-16 bg-blue-50 dark:bg-[#024ad8]/20 rounded-2xl flex items-center justify-center text-[#024ad8] text-3xl mb-8 group-hover:scale-110 transition-transform"
            >
              <i class="fa-solid fa-users"></i>
            </div>
            <h3
              class="text-2xl font-medium text-gray-900 dark:text-white mb-4 group-hover:text-[#024ad8] transition-colors"
            >
              Fokus pada Pelanggan
            </h3>
            <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
              Kami memulai dari pelanggan lalu bekerja mundur. Kami bekerja keras untuk meraih dan menjaga kepercayaan pelanggan.
            </p>
          </div>

          <!-- Value 2 -->
          <div
            class="bg-white dark:bg-gray-900 p-10 rounded-2xl shadow-sm border border-gray-100 dark:border-white/10 hover:shadow-xl hover:-translate-y-2 transition-all duration-300 group"
          >
            <div
              class="w-16 h-16 bg-orange-50 dark:bg-orange-500/20 rounded-2xl flex items-center justify-center text-orange-500 text-3xl mb-8 group-hover:scale-110 transition-transform"
            >
              <i class="fa-solid fa-lightbulb"></i>
            </div>
            <h3
              class="text-2xl font-medium text-gray-900 dark:text-white mb-4 group-hover:text-orange-500 transition-colors"
            >
              Berinovasi dan Menyederhanakan
            </h3>
            <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
              Kami menuntut inovasi dari setiap tim dan selalu mencari cara untuk menyederhanakan.
            </p>
          </div>

          <!-- Value 3 -->
          <div
            class="bg-white dark:bg-gray-900 p-10 rounded-2xl shadow-sm border border-gray-100 dark:border-white/10 hover:shadow-xl hover:-translate-y-2 transition-all duration-300 group"
          >
            <div
              class="w-16 h-16 bg-emerald-50 dark:bg-emerald-500/20 rounded-2xl flex items-center justify-center text-emerald-500 text-3xl mb-8 group-hover:scale-110 transition-transform"
            >
              <i class="fa-solid fa-leaf"></i>
            </div>
            <h3
              class="text-2xl font-medium text-gray-900 dark:text-white mb-4 group-hover:text-emerald-500 transition-colors"
            >
              Keberlanjutan
            </h3>
            <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
              Kami berkomitmen membangun bisnis yang berkelanjutan bagi karyawan, pelanggan, dan komunitas yang kami layani.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="max-w-[1280px] mx-auto px-5 md:px-[52px] py-10 md:py-24">
      <div
        class="bg-gray-950 dark:bg-gray-900 rounded-2xl p-10 md:p-24 text-center relative overflow-hidden group border border-transparent dark:border-white/10"
      >
        <!-- Background Image Overlay -->
        <div
          class="absolute inset-0 opacity-20 group-hover:scale-105 transition-transform duration-700"
        >
          <img
            src="https://images.unsplash.com/photo-1557804506-669a67965ba0?q=80&w=2074&auto=format&fit=crop"
            class="w-full h-full object-cover grayscale"
            alt="Latar belakang"
          />
        </div>

        <div class="relative z-10 flex flex-col items-center gap-8">
          <h2 class="text-4xl md:text-6xl font-medium text-white tracking-tight">
            Jadilah Bagian dari <br /><span
              class="text-transparent bg-clip-text bg-gradient-to-r from-[#024ad8] to-teal-400"
              >Revolusi.</span
            >
          </h2>
          <p class="text-gray-400 text-lg max-w-xl">
            Entah kamu membeli barang pertamamu atau membuka toko pertamamu, kami siap mendukungmu.
          </p>
          <div class="flex flex-col sm:flex-row gap-4">
            <RouterLink
              :to="{ name: 'app.all-products' }"
              class="px-8 py-4 bg-white text-gray-900 rounded-full font-medium text-lg hover:bg-gray-100 transition-all"
            >
              Mulai Belanja
            </RouterLink>
            <RouterLink
              :to="{ name: 'auth.open-store' }"
              class="px-8 py-4 bg-transparent border border-white/30 text-white rounded-full font-medium text-lg hover:bg-white/10 transition-all backdrop-blur-sm"
            >
              Buka Toko
            </RouterLink>
          </div>
        </div>
      </div>
    </section>
  </main>
</template>

<style scoped>
.animate-slow-zoom {
  animation: zoom 20s infinite alternate;
}

@keyframes zoom {
  0% {
    transform: scale(1);
  }

  100% {
    transform: scale(1.1);
  }
}
</style>
