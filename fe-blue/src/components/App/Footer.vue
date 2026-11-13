<script setup>
import BrandLogo from '@/components/Atom/BrandLogo.vue'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)
</script>

<template>
  <footer
    class="border-t border-custom-stroke dark:border-white/10 bg-white dark:bg-surface-card py-8 px-5 md:p-[52px] pb-[120px] md:pb-[52px]"
  >
    <div class="flex flex-col w-full max-w-[1280px] md:px-[52px] mx-auto gap-8 md:gap-9">
      <div class="flex flex-col lg:flex-row gap-8 lg:gap-[72px]">
        <div class="flex flex-col gap-4 md:gap-6 w-full lg:w-[349px]">
          <RouterLink :to="{ name: 'app.home' }" class="flex shrink-0">
            <BrandLogo class="h-12" />
          </RouterLink>
          <p class="font-medium text-custom-grey dark:text-gray-400">
            Blukios adalah marketplace multi-vendor untuk gadget, elektronik, dan aksesoris berkualitas.
            Menghubungkan pembeli dan penjual dalam pengalaman belanja yang seamless.
          </p>
        </div>
        <div class="flex flex-col md:flex-row gap-8 lg:gap-[52px] w-full lg:w-fit">
          <div class="grid grid-cols-2 sm:grid-cols-3 md:flex md:gap-[52px] gap-8 w-full">
            <div class="flex flex-col gap-4">
              <p class="font-medium dark:text-white">Menu Utama</p>
              <nav
                class="flex flex-col gap-4 *:font-medium *:text-custom-grey dark:*:text-gray-400 *:hover:text-custom-blue dark:*:hover:text-blue-400"
              >
                <RouterLink :to="{ name: 'app.home' }">Beranda</RouterLink>
                <RouterLink :to="{ name: 'app.all-categories' }">Kategori</RouterLink>
                <RouterLink :to="{ name: 'app.all-products' }">Semua Produk</RouterLink>
                <RouterLink v-if="!user?.store" :to="{ name: 'auth.open-store' }"
                  >Jadi Seller
                </RouterLink>
                <RouterLink
                  v-if="user"
                  :to="{ name: 'user.my-transaction', params: { username: user.username } }"
                  >Lacak Pesanan Saya</RouterLink
                >
                <RouterLink v-else :to="{ name: 'auth.login' }">Lacak Pesanan Saya</RouterLink>
              </nav>
            </div>
            <div class="flex flex-col gap-4">
              <p class="font-medium dark:text-white">Perusahaan</p>
              <nav
                class="flex flex-col gap-4 *:font-medium *:text-custom-grey dark:*:text-gray-400 *:hover:text-custom-blue dark:*:hover:text-blue-400"
              >
                <RouterLink :to="{ name: 'app.about' }">Tentang Kami</RouterLink>
                <RouterLink :to="{ name: 'app.career' }">Karier</RouterLink>
                <RouterLink :to="{ name: 'app.privacy' }">Kebijakan Privasi</RouterLink>
                <RouterLink :to="{ name: 'app.terms' }">Syarat & Ketentuan</RouterLink>
              </nav>
            </div>
            <div class="flex flex-col gap-4 col-span-2 sm:col-span-1">
              <p class="font-medium dark:text-white">Bantuan</p>
              <nav
                class="flex flex-col gap-4 *:font-medium *:text-custom-grey dark:*:text-gray-400 *:hover:text-custom-blue dark:*:hover:text-blue-400"
              >
                <a href="mailto:support@blukios.store">Layanan Pelanggan</a>
                <RouterLink :to="{ name: 'app.terms', hash: '#refunds' }">Pengembalian Dana</RouterLink>
                <a href="mailto:support@blukios.store">Hubungi Kami</a>
              </nav>
            </div>
          </div>
        </div>
        <div class="flex flex-row md:flex-col gap-4 items-center md:items-start">
          <p class="font-medium hidden md:block dark:text-white">Unduh Aplikasi</p>
          <span class="cursor-default opacity-60">
            <img src="@/assets/images/logos/gplay.png" class="h-10 md:h-12" alt="Google Play" />
          </span>
          <span class="cursor-default opacity-60">
            <img src="@/assets/images/logos/appstore.png" class="h-10 md:h-12" alt="App Store" />
          </span>
        </div>
      </div>
      <hr class="border-custom-stroke dark:border-white/10" />
      <div class="flex flex-col-reverse md:flex-row items-center justify-between gap-6 md:gap-0">
        <p
          class="font-medium text-custom-grey dark:text-gray-400 text-center md:text-left text-sm md:text-base"
        >
          ©2026 Blukios. Hak Cipta Dilindungi.
        </p>
        <div class="flex items-center gap-5">
          <span class="cursor-default opacity-60">
            <img src="@/assets/images/logos/instagram.svg" class="flex size-6 shrink-0" alt="Instagram" />
          </span>
          <span class="cursor-default opacity-60">
            <img src="@/assets/images/logos/gmail.svg" class="flex size-6 shrink-0" alt="Gmail" />
          </span>
          <span class="cursor-default opacity-60">
            <img src="@/assets/images/logos/facebook.svg" class="flex size-6 shrink-0" alt="Facebook" />
          </span>
          <span class="cursor-default opacity-60">
            <img src="@/assets/images/logos/tiktok.svg" class="flex size-6 shrink-0" alt="TikTok" />
          </span>
          <span class="cursor-default opacity-60">
            <img src="@/assets/images/logos/youtube.svg" class="flex size-6 shrink-0" alt="YouTube" />
          </span>
          <span class="cursor-default opacity-60">
            <img src="@/assets/images/logos/x.svg" class="flex size-6 shrink-0" alt="X" />
          </span>
        </div>
      </div>
    </div>
  </footer>
</template>
