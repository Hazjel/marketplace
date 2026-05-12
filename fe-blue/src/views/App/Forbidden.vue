<template>
  <main class="flex flex-col items-center justify-center w-full min-h-screen px-6 bg-gradient-to-br from-blue-50 via-white to-purple-50 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950">
    <div class="flex flex-col items-center justify-center text-center gap-8 max-w-lg -mt-20">
      <!-- Fun Illustration -->
      <div class="relative">
        <div class="absolute -inset-6 bg-gradient-to-br from-[#2563EB]/20 to-purple-500/20 rounded-full blur-2xl"></div>
        <div class="relative flex items-center justify-center w-[140px] h-[140px] rounded-full bg-white dark:bg-gray-800 shadow-xl border border-gray-100 dark:border-white/10">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="w-16 h-16 text-[#2563EB]"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.5"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"
            />
          </svg>
        </div>
      </div>

      <!-- Title -->
      <div class="flex flex-col gap-3">
        <h1 class="text-7xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-[#2563EB] to-purple-600 font-['Plus_Jakarta_Sans']">403</h1>
        <h2 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Akses Ditolak</h2>
      </div>

      <!-- Description -->
      <p class="text-gray-600 dark:text-gray-400 text-lg max-w-md leading-relaxed">
        Maaf, kamu tidak memiliki izin untuk mengakses halaman ini. Hubungi administrator jika kamu merasa ini adalah kesalahan.
      </p>

      <!-- Actions -->
      <div class="flex items-center gap-4 mt-4">
        <button
          @click="$router.go(-1)"
          class="px-6 py-3 rounded-2xl border-2 border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 font-semibold hover:bg-gray-50 dark:hover:bg-white/5 transition-all duration-200"
        >
          Kembali
        </button>
        <button
          @click="$router.push({ name: 'admin.dashboard' })"
          class="px-6 py-3 rounded-2xl bg-gradient-to-r from-[#2563EB] to-blue-700 text-white font-semibold hover:shadow-lg hover:shadow-[#2563EB]/25 transition-all duration-200"
        >
          Ke Dashboard
        </button>
      </div>
    </div>
  </main>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()
</script>
