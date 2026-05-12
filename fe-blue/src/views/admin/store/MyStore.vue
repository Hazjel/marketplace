<script setup>
import { formatDate } from '@/helpers/format'
import { useStoreStore } from '@/stores/store'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import { onMounted, ref, computed } from 'vue'
import { RouterLink } from 'vue-router'

const store = ref({})

const storeStore = useStoreStore()
const { loading } = storeToRefs(storeStore)

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const getEditStoreRoute = () => {
  if (user.value?.role === 'admin') {
    return { name: 'admin.edit-store' }
  }
  return {
    name: 'user.edit-store',
    params: { username: user.value?.username }
  }
}
const { fetchStoreByUser } = storeStore

const fetchStore = async () => {
  const response = await fetchStoreByUser()

  store.value = response
}

onMounted(fetchStore)

const fullAddress = computed(() => {
  if (!store.value) return ''
  return [store.value.address, store.value.city, store.value.postal_code].filter(Boolean).join(', ')
})

const mapSrc = computed(() => {
  const query = fullAddress.value || 'Malang'
  return `https://www.google.com/maps/embed/v1/place?q=${encodeURIComponent(query)}&key=AIzaSyBFw0Qbyq9zTFTd-tUY6dZWTgaQzuU17R8`
})
</script>

<template>
  <div class="flex flex-col lg:flex-row flex-1 gap-5">
    <!-- Main Store Section -->
    <section
      v-if="store"
      class="flex flex-col w-full lg:flex-1 h-fit rounded-2xl overflow-hidden bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 shadow-sm">
      <!-- Gradient Header -->
      <div class="relative bg-gradient-to-br from-blue-600 to-blue-700 p-6 pb-16">
        <p class="font-bold text-lg text-white">Toko Saya</p>
        <p class="text-blue-100 text-sm mt-0.5">Kelola informasi toko Anda</p>
      </div>

      <!-- Store Profile Card (overlapping header) -->
      <div class="px-5 -mt-10">
        <div class="flex flex-col md:flex-row items-start md:items-center w-full gap-4 bg-white dark:bg-surface-card rounded-2xl p-4 border border-gray-100 dark:border-white/10 shadow-sm">
          <div class="flex items-center gap-3 w-full min-w-0">
            <div class="flex size-16 shrink-0 rounded-xl bg-gray-50 dark:bg-white/5 overflow-hidden border border-gray-100 dark:border-white/10">
              <img :src="store?.logo" class="size-full object-cover" alt="photo" />
            </div>
            <div class="flex flex-col gap-1 w-full overflow-hidden">
              <p class="font-bold text-base leading-tight w-full truncate dark:text-white">
                {{ store.name }}
              </p>
              <p class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                {{ store.user?.name }}
              </p>
            </div>
          </div>
          <div class="flex items-center w-fit shrink-0 gap-1.5 ml-[76px] md:ml-0">
            <svg v-if="store?.is_verified" xmlns="http://www.w3.org/2000/svg" class="size-5 text-blue-600" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <span v-if="store?.is_verified" class="font-semibold text-sm text-blue-600 text-nowrap">TERVERIFIKASI</span>
            <span v-else class="font-semibold text-sm text-gray-500 text-nowrap">BELUM VERIFIKASI</span>
          </div>
        </div>
      </div>

      <!-- Store Content -->
      <div class="p-5 flex flex-col gap-4">
        <div class="flex flex-col gap-2">
          <p class="font-bold text-base dark:text-white">Tentang Toko</p>
          <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap leading-relaxed">{{ store?.about }}</p>
        </div>
        <div class="flex flex-col gap-3 pt-2">
          <RouterLink
            :to="getEditStoreRoute()"
            class="flex items-center justify-center h-11 w-full rounded-xl gap-2 bg-gray-900 dark:bg-white dark:hover:bg-gray-100 hover:bg-gray-800 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-white dark:text-gray-900" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            <span class="font-semibold text-sm text-white dark:text-gray-900">Edit Toko</span>
          </RouterLink>
          <p class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            Dibuat {{ formatDate(store?.created_at) }}
          </p>
        </div>
      </div>
    </section>

    <!-- Address Sidebar -->
    <section
      v-if="store"
      class="flex flex-col w-full lg:w-[380px] shrink-0 h-fit rounded-2xl p-5 gap-5 bg-white dark:bg-surface-card border border-gray-100 dark:border-white/10 shadow-sm">
      <p class="font-bold text-base dark:text-white">Alamat Toko</p>
      <div class="flex flex-col rounded-2xl border border-gray-100 dark:border-white/10 p-4 gap-4">
        <div class="flex items-center gap-3 w-full">
          <div class="flex size-10 shrink-0 rounded-xl bg-gray-50 dark:bg-white/5 items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          </div>
          <div class="flex flex-col gap-0.5">
            <p class="font-bold text-sm leading-none dark:text-white">{{ store?.city }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Kota</p>
          </div>
        </div>
        <hr class="border-gray-100 dark:border-white/10" />
        <div class="flex items-center gap-3 w-full">
          <div class="flex size-10 shrink-0 rounded-xl bg-gray-50 dark:bg-white/5 items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
          </div>
          <div class="flex flex-col gap-0.5">
            <p class="font-bold text-sm leading-none dark:text-white">{{ store?.postal_code }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Kode Pos</p>
          </div>
        </div>
      </div>
      <div class="flex flex-col gap-3">
        <div class="w-full h-[250px] overflow-hidden rounded-2xl border border-gray-100 dark:border-white/10">
          <div id="g-mapdisplay" class="size-full">
            <iframe class="size-full border-none" frameborder="0" :src="mapSrc"> </iframe>
          </div>
        </div>
        <p v-if="fullAddress" class="text-sm font-medium leading-relaxed dark:text-white">{{ fullAddress }}</p>
        <p v-else class="text-sm font-medium text-gray-500 dark:text-gray-400">Alamat belum diatur</p>
      </div>
    </section>

    <!-- Empty State -->
    <div v-else id="Empty-State-Store" class="flex flex-1 bg-white rounded-2xl p-5 dark:bg-surface-card border border-gray-100 dark:border-white/10 shadow-sm">
      <div class="flex flex-col flex-1 items-center justify-center gap-4 rounded-2xl border-2 border-dashed border-gray-200 dark:border-white/10 p-8">
        <div class="flex size-16 items-center justify-center rounded-2xl bg-gray-50 dark:bg-white/5">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-8 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="flex flex-col gap-1 items-center text-center">
          <p class="font-semibold text-gray-700 dark:text-gray-300">Belum ada profil toko</p>
          <p class="text-sm text-gray-500">Buat toko Anda sekarang untuk mulai berjualan</p>
        </div>
        <RouterLink
          :to="{ name: 'admin.create-store' }"
          class="flex h-10 items-center rounded-xl px-5 bg-blue-600 gap-2 hover:bg-blue-700 transition-colors">
          <span class="font-semibold text-sm text-white">Buat Toko</span>
          <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
        </RouterLink>
      </div>
    </div>
  </div>
</template>
