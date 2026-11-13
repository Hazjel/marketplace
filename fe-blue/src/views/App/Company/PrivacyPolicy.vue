<script setup>
import { onMounted, ref } from 'vue'

const activeSection = ref('introduction')

const scrollTo = (id) => {
  activeSection.value = id
  const element = document.getElementById(id)
  if (element) {
    const offset = 100 // Header height + padding
    const bodyRect = document.body.getBoundingClientRect().top
    const elementRect = element.getBoundingClientRect().top
    const elementPosition = elementRect - bodyRect
    const offsetPosition = elementPosition - offset

    window.scrollTo({
      top: offsetPosition,
      behavior: 'smooth'
    })
  }
}

onMounted(() => {
  window.scrollTo(0, 0)
})
</script>

<template>
  <main class="w-full bg-white dark:bg-gray-950 pb-20">
    <!-- Header -->
    <header class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-white/10 py-16 md:py-20 px-5 md:px-[52px]">
      <div class="max-w-[1280px] mx-auto">
        <span class="text-[#024ad8] font-medium tracking-widest uppercase text-sm mb-4 block"
          >Legal</span
        >
        <h1 class="text-4xl md:text-5xl font-medium text-gray-900 dark:text-white mb-4">Kebijakan Privasi</h1>
        <p class="text-gray-600 dark:text-gray-400 text-lg max-w-2xl">
          Kami berkomitmen melindungi privasi Anda dan memastikan data Anda ditangani dengan transparansi dan kepedulian.
        </p>
        <div
          class="mt-8 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 px-4 py-2 rounded-full w-fit border border-gray-200 dark:border-white/10 shadow-sm"
        >
          <i class="fa-regular fa-clock"></i>
          <span>Terakhir Diperbarui: 01 Januari 2026</span>
        </div>
      </div>
    </header>

    <div
      class="max-w-[1280px] mx-auto px-5 md:px-[52px] py-12 flex flex-col md:flex-row gap-12 relative"
    >
      <!-- Sidebar Navigation -->
      <aside class="w-full md:w-1/4 hidden md:block">
        <div class="sticky top-32 flex flex-col gap-2">
          <p class="font-medium text-gray-900 dark:text-white mb-2 px-4 uppercase text-xs tracking-wider">
            Daftar Isi
          </p>
          <button
            :class="
              activeSection === 'introduction'
                ? 'bg-blue-50 dark:bg-[#024ad8]/20 text-[#024ad8] font-medium border-[#024ad8]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('introduction')"
          >
            1. Pendahuluan
          </button>
          <button
            :class="
              activeSection === 'collection'
                ? 'bg-blue-50 dark:bg-[#024ad8]/20 text-[#024ad8] font-medium border-[#024ad8]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('collection')"
          >
            2. Data yang Kami Kumpulkan
          </button>
          <button
            :class="
              activeSection === 'usage'
                ? 'bg-blue-50 dark:bg-[#024ad8]/20 text-[#024ad8] font-medium border-[#024ad8]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('usage')"
          >
            3. Cara Kami Menggunakan Data
          </button>
          <button
            :class="
              activeSection === 'security'
                ? 'bg-blue-50 dark:bg-[#024ad8]/20 text-[#024ad8] font-medium border-[#024ad8]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('security')"
          >
            4. Keamanan Data
          </button>
          <button
            :class="
              activeSection === 'contact'
                ? 'bg-blue-50 dark:bg-[#024ad8]/20 text-[#024ad8] font-medium border-[#024ad8]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('contact')"
          >
            5. Hubungi Kami
          </button>
        </div>
      </aside>

      <!-- Content -->
      <div class="w-full md:w-3/4 max-w-3xl">
        <div
          class="prose prose-lg prose-headings:text-gray-900 dark:prose-headings:text-white prose-p:text-gray-600 dark:prose-p:text-gray-400 prose-li:text-gray-600 dark:prose-li:text-gray-400 max-w-none flex flex-col gap-12"
        >
          <section id="introduction" class="scroll-mt-32">
            <h2 class="text-2xl font-medium mb-4 text-gray-900 dark:text-white">1. Pendahuluan</h2>
            <p class="text-base leading-relaxed text-gray-600 dark:text-gray-400">
              Selamat datang di Blukios. Kami menghormati privasi Anda dan berkomitmen melindungi data pribadi Anda. Kebijakan privasi ini menjelaskan bagaimana kami menjaga data pribadi Anda saat Anda mengunjungi situs web kami (dari mana pun Anda mengaksesnya), serta hak privasi Anda dan bagaimana hukum melindungi Anda.
            </p>
          </section>

          <section id="collection" class="scroll-mt-32">
            <h2 class="text-2xl font-medium mb-4 text-gray-900 dark:text-white">2. Data yang Kami Kumpulkan</h2>
            <p class="text-base leading-relaxed mb-4 text-gray-600 dark:text-gray-400">
              Kami dapat mengumpulkan, menggunakan, menyimpan, dan mentransfer berbagai jenis data pribadi tentang Anda, yang kami kelompokkan sebagai berikut:
            </p>
            <div class="bg-gray-50 dark:bg-gray-900 p-6 rounded-2xl border border-gray-100 dark:border-white/10">
              <ul class="space-y-3 list-none p-0 m-0">
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    ><strong class="text-gray-900 dark:text-white">Data Identitas:</strong> Nama depan, nama gadis, nama belakang, nama pengguna, atau pengenal serupa.</span
                  >
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    ><strong class="text-gray-900 dark:text-white">Data Kontak:</strong> Alamat penagihan, alamat pengiriman, alamat email, dan nomor telepon.</span
                  >
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    ><strong class="text-gray-900 dark:text-white">Data Keuangan:</strong> Rincian rekening bank dan kartu pembayaran (diproses dengan aman).</span
                  >
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    ><strong class="text-gray-900 dark:text-white">Data Transaksi:</strong> Rincian pembayaran yang Anda lakukan dan terima.</span
                  >
                </li>
              </ul>
            </div>
          </section>

          <section id="usage" class="scroll-mt-32">
            <h2 class="text-2xl font-medium mb-4 text-gray-900 dark:text-white">3. Cara Kami Menggunakan Data Anda</h2>
            <p class="text-base leading-relaxed mb-4 text-gray-600 dark:text-gray-400">
              Kami hanya menggunakan data pribadi Anda jika diizinkan oleh hukum. Umumnya, kami menggunakan data pribadi Anda dalam keadaan berikut:
            </p>
            <ul class="space-y-2 list-disc pl-5 text-gray-600 dark:text-gray-400">
              <li class="pl-2">
                Saat kami perlu menjalankan kontrak yang akan atau telah kami buat dengan Anda.
              </li>
              <li class="pl-2">
                Saat diperlukan untuk kepentingan sah kami (atau pihak ketiga) dan kepentingan serta hak dasar Anda tidak melampaui kepentingan tersebut.
              </li>
              <li class="pl-2">Saat kami perlu memenuhi kewajiban hukum atau peraturan.</li>
            </ul>
          </section>

          <section id="security" class="scroll-mt-32">
            <h2 class="text-2xl font-medium mb-4 text-gray-900 dark:text-white">4. Keamanan Data</h2>
            <p class="text-base leading-relaxed text-gray-600 dark:text-gray-400">
              Kami telah menerapkan langkah keamanan yang memadai untuk mencegah data pribadi Anda hilang secara tidak sengaja, digunakan atau diakses secara tidak sah, diubah, atau diungkapkan. Selain itu, kami membatasi akses ke data pribadi Anda hanya bagi karyawan, agen, kontraktor, dan pihak ketiga lain yang perlu mengetahui data tersebut untuk keperluan bisnis.
            </p>
          </section>

          <section id="contact" class="scroll-mt-32">
            <h2 class="text-2xl font-medium mb-4 text-gray-900 dark:text-white">5. Hubungi Kami</h2>
            <div
              class="bg-blue-50 dark:bg-[#024ad8]/10 border border-blue-100 dark:border-[#024ad8]/30 rounded-2xl p-6 flex flex-col md:flex-row items-center gap-6"
            >
              <div
                class="w-12 h-12 rounded-full bg-white dark:bg-gray-800 flex items-center justify-center text-[#024ad8] shadow-sm shrink-0"
              >
                <i class="fa-solid fa-envelope"></i>
              </div>
              <div>
                <h3 class="font-medium text-gray-900 dark:text-white text-lg">Ada pertanyaan?</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                  Petugas perlindungan data kami siap membantu Anda.
                </p>
                <a
                  href="mailto:support@blukios.store"
                  class="text-[#024ad8] font-medium hover:underline"
                  >support@blukios.store</a
                >
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  </main>
</template>
