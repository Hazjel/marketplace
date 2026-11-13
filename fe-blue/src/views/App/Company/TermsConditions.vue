<script setup>
import { nextTick, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const activeSection = ref('agreement')

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

onMounted(async () => {
  window.scrollTo(0, 0)
  // Footer links like "Return & Refund" open /terms#refunds.
  const section = route.hash.slice(1)
  if (section && document.getElementById(section)) {
    await nextTick()
    scrollTo(section)
  }
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
        <h1 class="text-4xl md:text-5xl font-medium text-gray-900 dark:text-white mb-4">Syarat & Ketentuan</h1>
        <p class="text-gray-600 dark:text-gray-400 text-lg max-w-2xl">
          Silakan baca syarat dan ketentuan ini dengan seksama sebelum menggunakan layanan kami.
        </p>
        <div
          class="mt-8 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 px-4 py-2 rounded-full w-fit border border-gray-200 dark:border-white/10 shadow-sm"
        >
          <i class="fa-regular fa-clock"></i>
          <span>Terakhir Diperbarui: 10 Oktober 2026</span>
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
              activeSection === 'agreement'
                ? 'bg-blue-50 dark:bg-[#024ad8]/20 text-[#024ad8] font-medium border-[#024ad8]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('agreement')"
          >
            1. Persetujuan
          </button>
          <button
            :class="
              activeSection === 'representations'
                ? 'bg-blue-50 dark:bg-[#024ad8]/20 text-[#024ad8] font-medium border-[#024ad8]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('representations')"
          >
            2. Pernyataan Pengguna
          </button>
          <button
            :class="
              activeSection === 'products'
                ? 'bg-blue-50 dark:bg-[#024ad8]/20 text-[#024ad8] font-medium border-[#024ad8]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('products')"
          >
            3. Produk & Pembelian
          </button>
          <button
            :class="
              activeSection === 'refunds'
                ? 'bg-blue-50 dark:bg-[#024ad8]/20 text-[#024ad8] font-medium border-[#024ad8]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('refunds')"
          >
            4. Pengembalian Dana & Saldo Blukios
          </button>
          <button
            :class="
              activeSection === 'complaints'
                ? 'bg-blue-50 dark:bg-[#024ad8]/20 text-[#024ad8] font-medium border-[#024ad8]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('complaints')"
          >
            5. Komplain
          </button>
          <button
            :class="
              activeSection === 'prohibited'
                ? 'bg-blue-50 dark:bg-[#024ad8]/20 text-[#024ad8] font-medium border-[#024ad8]'
                : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-transparent'
            "
            class="text-left px-4 py-3 rounded-lg text-sm transition-all border-l-4"
            @click="scrollTo('prohibited')"
          >
            6. Aktivitas yang Dilarang
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
            7. Hubungi Kami
          </button>
        </div>
      </aside>

      <!-- Content -->
      <div class="w-full md:w-3/4 max-w-3xl">
        <div
          class="prose prose-lg prose-headings:text-gray-900 dark:prose-headings:text-white prose-p:text-gray-600 dark:prose-p:text-gray-400 prose-li:text-gray-600 dark:prose-li:text-gray-400 max-w-none flex flex-col gap-12"
        >
          <section id="agreement" class="scroll-mt-32">
            <h2 class="text-2xl font-medium mb-4 text-gray-900 dark:text-white">1. Persetujuan terhadap Ketentuan</h2>
            <p class="text-base leading-relaxed text-gray-600 dark:text-gray-400">
              Syarat dan Ketentuan ini merupakan perjanjian yang mengikat secara hukum antara Anda, baik secara pribadi maupun atas nama suatu badan ("Anda"), dan Blukios ("kami"), terkait akses dan penggunaan Anda atas situs web Blukios serta media, saluran media, situs web seluler, atau aplikasi seluler lain yang terkait, tertaut, atau terhubung dengannya (secara bersama-sama disebut "Situs").
            </p>
          </section>

          <section id="representations" class="scroll-mt-32">
            <h2 class="text-2xl font-medium mb-4 text-gray-900 dark:text-white">2. Pernyataan Pengguna</h2>
            <p class="text-base leading-relaxed mb-4 text-gray-600 dark:text-gray-400">
              Dengan menggunakan Situs, Anda menyatakan dan menjamin bahwa:
            </p>
            <div class="bg-gray-50 dark:bg-gray-900 p-6 rounded-2xl border border-gray-100 dark:border-white/10">
              <ul class="space-y-3 list-none p-0 m-0">
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    >Semua informasi pendaftaran yang Anda kirimkan benar, akurat, terkini, dan lengkap.</span
                  >
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    >Anda akan menjaga keakuratan informasi tersebut dan segera memperbarui informasi pendaftaran bila diperlukan.</span
                  >
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    >Anda memiliki kecakapan hukum dan setuju untuk mematuhi Syarat dan Ketentuan ini.</span
                  >
                </li>
              </ul>
            </div>
          </section>

          <section id="products" class="scroll-mt-32">
            <h2 class="text-2xl font-medium mb-4 text-gray-900 dark:text-white">3. Produk dan Pembelian</h2>
            <p class="text-base leading-relaxed text-gray-600 dark:text-gray-400">
              Kami berupaya semaksimal mungkin menampilkan warna, fitur, spesifikasi, dan detail produk yang tersedia di Situs seakurat mungkin. Namun, kami tidak menjamin bahwa warna, fitur, spesifikasi, dan detail produk tersebut akurat, lengkap, andal, terkini, atau bebas dari kesalahan lain.
            </p>
          </section>

          <section id="refunds" class="scroll-mt-32">
            <h2 class="text-2xl font-medium mb-4 text-gray-900 dark:text-white">4. Pengembalian Dana dan Saldo Blukios</h2>
            <p class="text-base leading-relaxed mb-4 text-gray-600 dark:text-gray-400">
              Jika penjual menolak atau membatalkan pesanan yang sudah Anda bayar, Anda menerima kembali seluruh jumlah yang Anda bayarkan untuk pesanan tersebut:
            </p>
            <div class="bg-gray-50 dark:bg-gray-900 p-6 rounded-2xl border border-gray-100 dark:border-white/10 mb-4">
              <ul class="space-y-3 list-none p-0 m-0">
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    >Dibayar dengan kartu kredit/debit, GoPay, ShopeePay, atau QRIS: dikembalikan ke metode pembayaran yang sama melalui penyedia pembayaran kami. Kapan dana masuk ke akun Anda bergantung pada bank atau penyedia dompet digital Anda.</span
                  >
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    >Dibayar dengan virtual account bank atau di gerai ritel: metode ini tidak dapat dikembalikan otomatis, sehingga jumlahnya langsung dikreditkan ke Saldo Blukios Anda.</span
                  >
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    >Dibayar sebagian dengan Saldo Blukios: bagian tersebut langsung kembali ke Saldo Blukios Anda, dan sisanya dikembalikan seperti dijelaskan di atas.</span
                  >
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    >Pembayaran yang masuk setelah pesanan kedaluwarsa dikembalikan otomatis dengan cara yang sama.</span
                  >
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    >Jika pengembalian otomatis tidak dapat dikonfirmasi, tim kami memeriksanya dengan penyedia pembayaran dan menyelesaikannya, ke Saldo Blukios Anda atau melalui transfer bank.</span
                  >
                </li>
              </ul>
            </div>
            <p class="text-base leading-relaxed text-gray-600 dark:text-gray-400">
              Saldo Blukios hanya dapat digunakan untuk membayar pembelian di Blukios, penuh maupun sebagian saat checkout. Saldo tidak dapat ditarik ke rekening bank, diisi ulang, atau ditransfer ke pengguna lain. Anda dapat melihat saldo dan riwayatnya di dashboard Anda.
            </p>
          </section>

          <section id="complaints" class="scroll-mt-32">
            <h2 class="text-2xl font-medium mb-4 text-gray-900 dark:text-white">5. Komplain</h2>
            <p class="text-base leading-relaxed mb-4 text-gray-600 dark:text-gray-400">
              Jika pesanan tidak sampai, tiba dalam keadaan rusak, atau tidak sesuai pesanan Anda, Anda dapat mengajukan komplain dari halaman pesanan:
            </p>
            <div class="bg-gray-50 dark:bg-gray-900 p-6 rounded-2xl border border-gray-100 dark:border-white/10">
              <ul class="space-y-3 list-none p-0 m-0">
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400">Komplain hanya dapat diajukan selama pesanan dalam pengiriman, sebelum Anda mengonfirmasi penerimaan atau pesanan selesai otomatis. Setiap pesanan hanya dapat memiliki satu komplain.</span>
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400">Komplain memuat alasan (barang tidak diterima, rusak, barang salah, atau lainnya), deskripsi, dan maksimal 3 foto.</span>
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400">Penjual memiliki 2 hari untuk menanggapi. Jika penjual menyetujui, komplain disetujui. Jika penjual menolak atau tidak menanggapi tepat waktu, tim admin kami meninjau dan memutuskannya.</span>
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400">Komplain yang disetujui dikembalikan dananya secara penuh seperti dijelaskan pada bagian 4. Anda tidak perlu mengembalikan barang.</span>
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400">Jika komplain ditolak, pesanan dilanjutkan dan Anda dapat mengonfirmasi penerimaan seperti biasa.</span>
                </li>
                <li class="flex gap-3">
                  <div class="w-1.5 h-1.5 rounded-full bg-[#024ad8] mt-2 shrink-0"></div>
                  <span class="text-sm text-gray-600 dark:text-gray-400">Selama komplain masih terbuka atau ditinjau admin, pesanan tidak dapat diselesaikan. Anda dapat menarik komplain kapan saja sebelum diputuskan; komplain yang sudah ditarik tidak dapat diajukan kembali.</span>
                </li>
              </ul>
            </div>
          </section>

          <section id="prohibited" class="scroll-mt-32">
            <h2 class="text-2xl font-medium mb-4 text-gray-900 dark:text-white">6. Aktivitas yang Dilarang</h2>
            <p class="text-base leading-relaxed mb-4 text-gray-600 dark:text-gray-400">
              Anda tidak boleh mengakses atau menggunakan Situs untuk tujuan selain yang kami sediakan. Sebagai pengguna Situs, Anda setuju untuk tidak:
            </p>
            <ul class="space-y-2 list-disc pl-5 text-gray-600 dark:text-gray-400">
              <li class="pl-2">
                Mengambil data atau konten lain dari Situs secara sistematis untuk membuat atau menyusun, secara langsung maupun tidak langsung, kumpulan, kompilasi, basis data, atau direktori tanpa izin tertulis dari kami.
              </li>
              <li class="pl-2">
                Menggunakan Situs secara tidak sah, termasuk mengumpulkan nama pengguna dan/atau alamat email pengguna lain secara elektronik atau dengan cara lain untuk mengirim email yang tidak diminta.
              </li>
            </ul>
          </section>

          <section id="contact" class="scroll-mt-32">
            <h2 class="text-2xl font-medium mb-4 text-gray-900 dark:text-white">7. Hubungi Kami</h2>
            <div
              class="bg-blue-50 dark:bg-[#024ad8]/10 border border-blue-100 dark:border-[#024ad8]/30 rounded-2xl p-6 flex flex-col md:flex-row items-center gap-6"
            >
              <div
                class="w-12 h-12 rounded-full bg-white dark:bg-gray-800 flex items-center justify-center text-[#024ad8] shadow-sm shrink-0"
              >
                <i class="fa-solid fa-scale-balanced"></i>
              </div>
              <div>
                <h3 class="font-medium text-gray-900 dark:text-white text-lg">Ada Masalah Hukum?</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Tim hukum kami siap membantu Anda.</p>
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
