<script setup>
import { useStoreStore } from '@/stores/store'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'

const toast = useToast()
const route = useRoute()

const store = ref({})

const storeStore = useStoreStore()
const { loading, success, error } = storeToRefs(storeStore)
const { fetchStoreById, approveStore } = storeStore

const fetchData = async () => {
  const response = await fetchStoreById(route.params.id)

  store.value = response
}

async function handleApprovedStore() {
  await approveStore(route.params.id)

  fetchData()
}

onMounted(fetchData)

watch(success, (value) => {
  if (value) {
    toast.success(value)
    storeStore.success = null
  }
})
watch(error, (value) => {
  if (value) {
    toast.error(value)
    storeStore.error = null
  }
})
</script>

<template>
  <div class="flex flex-col lg:flex-row gap-6">
    <div class="flex flex-col gap-6 w-full lg:w-3/5 animate-fade-in-up">
      <!-- Verified Store -->
      <section
        v-if="store.is_verified"
        class="flex flex-col w-full h-fit rounded-2xl p-6 gap-6 bg-white dark:bg-gray-900 border border-gray-100 dark:border-white/10 shadow-sm"
      >
        <h2 class="font-bold text-xl text-gray-900 dark:text-white">Detail Toko</h2>
        <div class="flex items-center w-full gap-5">
          <div class="flex items-center gap-4 w-full min-w-0">
            <div
              class="flex size-[80px] shrink-0 rounded-2xl bg-gray-100 dark:bg-gray-800 overflow-hidden border border-gray-200 dark:border-white/10"
            >
              <img :src="store.logo" class="size-full object-cover" alt="photo" />
            </div>
            <div class="flex flex-col gap-1.5 w-full overflow-hidden">
              <p class="font-bold text-lg leading-tight w-full truncate text-gray-900 dark:text-white">
                {{ store.name }}
              </p>
              <p class="flex items-center gap-1.5 font-medium text-gray-500 dark:text-gray-400 text-sm leading-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </svg>
                {{ store.user?.name }}
              </p>
            </div>
          </div>
          <div class="flex items-center w-fit shrink-0 gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/20 ring-1 ring-emerald-200 dark:ring-emerald-500/30">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="currentColor" viewBox="0 0 24 24">
              <path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.49 4.49 0 01-3.498-1.306 4.491 4.491 0 01-1.307-3.498A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
            </svg>
            <p class="font-bold text-emerald-700 dark:text-emerald-400 text-xs text-nowrap uppercase">Terverifikasi</p>
          </div>
        </div>

        <hr class="border-gray-100 dark:border-white/10" />

        <div class="flex flex-col gap-2">
          <h3 class="font-bold text-lg text-gray-900 dark:text-white">Tentang Toko</h3>
          <p class="font-medium text-gray-600 dark:text-gray-400 whitespace-pre-wrap text-sm leading-relaxed">{{ store.about }}</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="flex items-center gap-3 p-4 rounded-2xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-white/10">
            <div class="flex size-11 shrink-0 rounded-xl bg-[#2563EB]/10 dark:bg-[#2563EB]/20 items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-[#2563EB]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
              </svg>
            </div>
            <div class="flex flex-col">
              <p class="font-bold text-lg leading-none text-gray-900 dark:text-white">{{ store.transaction_count }}</p>
              <p class="font-medium text-gray-500 dark:text-gray-400 text-xs mt-0.5">Transaksi</p>
            </div>
          </div>
          <div class="flex items-center gap-3 p-4 rounded-2xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-white/10">
            <div class="flex size-11 shrink-0 rounded-xl bg-orange-500/10 dark:bg-orange-500/20 items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
              </svg>
            </div>
            <div class="flex flex-col">
              <p class="font-bold text-lg leading-none text-gray-900 dark:text-white">{{ store.product_count }}</p>
              <p class="font-medium text-gray-500 dark:text-gray-400 text-xs mt-0.5">Produk</p>
            </div>
          </div>
          <div class="flex items-center gap-3 p-4 rounded-2xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-white/10">
            <div class="flex size-11 shrink-0 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/20 items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="flex flex-col">
              <p class="font-bold text-sm leading-none text-gray-900 dark:text-white">Buka</p>
              <p class="font-medium text-gray-500 dark:text-gray-400 text-xs mt-0.5">Status Toko</p>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-col gap-3">
          <div class="flex items-center gap-3">
            <button
              class="flex items-center justify-center size-12 shrink-0 rounded-2xl bg-red-50 dark:bg-red-500/20 hover:bg-red-100 dark:hover:bg-red-500/30 transition-colors"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
              </svg>
            </button>
            <RouterLink
              :to="{ name: 'app.store-detail', params: { username: store.username } }"
              class="flex items-center justify-center h-12 w-full rounded-2xl gap-2 bg-gradient-to-r from-[#2563EB] to-blue-700 hover:shadow-lg hover:shadow-[#2563EB]/25 transition-all"
            >
              <span class="font-semibold text-white text-sm">Kunjungi Toko</span>
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
              </svg>
            </RouterLink>
          </div>
          <p class="flex items-center gap-2 font-medium text-gray-500 dark:text-gray-400 text-sm leading-none">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
            </svg>
            Dibuat pada 19/02/2020
          </p>
        </div>
      </section>

      <!-- Unverified Store -->
      <section
        v-if="!store.is_verified"
        class="flex flex-col w-full h-fit rounded-2xl p-6 gap-6 bg-white dark:bg-gray-900 border border-gray-100 dark:border-white/10 shadow-sm"
      >
        <h2 class="font-bold text-xl text-gray-900 dark:text-white">Detail Toko</h2>
        <div class="flex items-center w-full gap-5">
          <div class="flex items-center gap-4 w-full min-w-0">
            <div
              class="flex size-[80px] shrink-0 rounded-2xl bg-gray-100 dark:bg-gray-800 overflow-hidden border border-gray-200 dark:border-white/10"
            >
              <img :src="store.logo" class="size-full object-cover" alt="photo" />
            </div>
            <div class="flex flex-col gap-1.5 w-full overflow-hidden">
              <p class="font-bold text-lg leading-tight w-full truncate text-gray-900 dark:text-white">
                {{ store.name }}
              </p>
              <p class="flex items-center gap-1.5 font-medium text-gray-500 dark:text-gray-400 text-sm leading-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </svg>
                {{ store.user?.name }}
              </p>
            </div>
          </div>
          <div class="flex items-center w-fit shrink-0 gap-1.5 px-3 py-1.5 rounded-full bg-red-50 dark:bg-red-500/20 ring-1 ring-red-200 dark:ring-red-500/30">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <p class="font-bold text-red-600 dark:text-red-400 text-xs text-nowrap uppercase">Belum Aktif</p>
          </div>
        </div>

        <hr class="border-gray-100 dark:border-white/10" />

        <div class="flex flex-col gap-2">
          <h3 class="font-bold text-lg text-gray-900 dark:text-white">Tentang Toko</h3>
          <p class="font-medium text-gray-600 dark:text-gray-400 whitespace-pre-wrap text-sm leading-relaxed">{{ store.about }}</p>
        </div>

        <!-- Actions -->
        <div class="flex flex-col gap-3">
          <div class="flex items-center gap-3">
            <a
              class="flex items-center justify-center h-12 w-full rounded-2xl gap-2 bg-gradient-to-r from-[#2563EB] to-blue-700 hover:shadow-lg hover:shadow-[#2563EB]/25 transition-all cursor-pointer"
              @click="handleApprovedStore"
            >
              <span class="font-semibold text-white text-sm">Setujui Toko</span>
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </a>
          </div>
          <p class="flex items-center gap-2 font-medium text-gray-500 dark:text-gray-400 text-sm leading-none">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
            </svg>
            Dibuat pada 19/02/2020
          </p>
        </div>
      </section>
    </div>

    <!-- Sidebar: Address -->
    <section
      class="flex flex-col w-full lg:w-2/5 h-fit rounded-2xl p-6 gap-6 bg-white dark:bg-gray-900 border border-gray-100 dark:border-white/10 shadow-sm animate-fade-in-up"
    >
      <h2 class="font-bold text-xl text-gray-900 dark:text-white">Alamat Toko</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="flex items-center gap-3 p-4 rounded-2xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-white/10">
          <div class="flex size-11 shrink-0 rounded-xl bg-purple-500/10 dark:bg-purple-500/20 items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
            </svg>
          </div>
          <div class="flex flex-col">
            <p class="font-bold text-sm leading-none text-gray-900 dark:text-white">{{ store.city }}</p>
            <p class="font-medium text-gray-500 dark:text-gray-400 text-xs mt-0.5">Kota</p>
          </div>
        </div>
        <div class="flex items-center gap-3 p-4 rounded-2xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-white/10">
          <div class="flex size-11 shrink-0 rounded-xl bg-teal-500/10 dark:bg-teal-500/20 items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-teal-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
            </svg>
          </div>
          <div class="flex flex-col">
            <p class="font-bold text-sm leading-none text-gray-900 dark:text-white">{{ store.postal_code }}</p>
            <p class="font-medium text-gray-500 dark:text-gray-400 text-xs mt-0.5">Kode Pos</p>
          </div>
        </div>
      </div>

      <!-- Map -->
      <div class="flex flex-col gap-3">
        <div class="w-full h-[260px] overflow-hidden rounded-2xl border border-gray-100 dark:border-white/10">
          <div id="g-mapdisplay" class="size-full">
            <iframe
              class="size-full border-none"
              frameborder="0"
              src="https://www.google.com/maps/embed/v1/place?q=Malang&key=AIzaSyBFw0Qbyq9zTFTd-tUY6dZWTgaQzuU17R8"
            >
            </iframe>
          </div>
        </div>
        <p class="font-medium text-gray-700 dark:text-gray-300 text-sm leading-relaxed">
          {{ store.address }}
        </p>
      </div>
    </section>
  </div>
</template>
