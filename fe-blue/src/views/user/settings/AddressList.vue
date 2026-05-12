<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { axiosInstance } from '@/plugins/axios'
import { useToast } from 'vue-toastification'

const toast = useToast()
const addresses = ref([])
const loading = ref(true)

const fetchAddresses = async () => {
  loading.value = true
  try {
    const response = await axiosInstance.get('/address')
    addresses.value = response.data.data
  } catch (error) {
    console.error('Error fetching addresses:', error)
  } finally {
    loading.value = false
  }
}

const deleteAddress = async (id) => {
  if (!confirm('Are you sure you want to delete this address?')) return

  try {
    await axiosInstance.delete(`/address/${id}`)
    toast.success('Address deleted successfully')
    fetchAddresses()
  } catch {
    toast.error('Failed to delete address')
  }
}

onMounted(() => {
  fetchAddresses()
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex flex-col gap-1">
        <h1 class="font-bold text-2xl lg:text-3xl text-custom-black dark:text-white">Alamat Saya</h1>
        <p class="text-custom-grey dark:text-gray-400 font-medium text-sm">Kelola alamat pengirimanmu untuk checkout yang lebih cepat.</p>
      </div>
      <RouterLink
        :to="{ name: 'user.settings.address.create' }"
        class="flex items-center justify-center gap-2 px-5 py-3 bg-custom-blue text-white rounded-full font-bold text-sm hover:bg-blue-700 hover:shadow-lg hover:shadow-custom-blue/20 active:scale-[0.98] transition-all duration-300 shrink-0"
      >
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
        </svg>
        Tambah Alamat
      </RouterLink>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div v-for="i in 2" :key="i" class="flex flex-col gap-4 p-6 bg-white dark:bg-white/[0.02] rounded-2xl border border-gray-100 dark:border-white/10">
        <div class="flex items-center gap-3">
          <div class="w-20 h-5 bg-gray-100 dark:bg-white/10 rounded-full animate-pulse"></div>
          <div class="w-16 h-5 bg-gray-100 dark:bg-white/10 rounded-full animate-pulse"></div>
        </div>
        <div class="w-40 h-4 bg-gray-100 dark:bg-white/10 rounded-full animate-pulse"></div>
        <div class="w-full h-4 bg-gray-100 dark:bg-white/10 rounded-full animate-pulse"></div>
        <div class="w-3/4 h-4 bg-gray-100 dark:bg-white/10 rounded-full animate-pulse"></div>
        <div class="flex gap-3 mt-2">
          <div class="flex-1 h-10 bg-gray-100 dark:bg-white/10 rounded-xl animate-pulse"></div>
          <div class="flex-1 h-10 bg-gray-100 dark:bg-white/10 rounded-xl animate-pulse"></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="addresses.length === 0"
      class="flex flex-col items-center justify-center py-16 lg:py-20 bg-white dark:bg-white/[0.02] rounded-2xl border border-gray-100 dark:border-white/10"
    >
      <div class="size-20 bg-blue-50 dark:bg-custom-blue/10 rounded-full flex items-center justify-center mb-5">
        <svg class="size-9 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
      </div>
      <p class="font-bold text-lg text-custom-black dark:text-white mb-1">Belum Ada Alamat</p>
      <p class="text-custom-grey dark:text-gray-400 text-sm mb-6 text-center max-w-xs">Tambahkan alamat untuk mempercepat proses checkout kamu.</p>
      <RouterLink
        :to="{ name: 'user.settings.address.create' }"
        class="flex items-center gap-2 px-6 py-3 bg-custom-blue text-white rounded-full font-bold text-sm hover:bg-blue-700 hover:shadow-lg hover:shadow-custom-blue/20 active:scale-[0.98] transition-all duration-300"
      >
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
        </svg>
        Tambah Alamat Pertama
      </RouterLink>
    </div>

    <!-- Address Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div
        v-for="address in addresses"
        :key="address.id"
        class="relative flex flex-col bg-white dark:bg-white/[0.02] border border-gray-100 dark:border-white/10 rounded-2xl p-5 lg:p-6 hover:shadow-md hover:border-gray-200 dark:hover:border-white/20 transition-all duration-300 group"
      >
        <!-- Primary Badge -->
        <div
          v-if="address.is_primary"
          class="absolute top-4 right-4 px-3 py-1 bg-custom-blue/10 dark:bg-custom-blue/20 text-custom-blue text-xs font-bold rounded-full"
        >
          Utama
        </div>

        <!-- Address Info -->
        <div class="flex flex-col gap-1 mb-3">
          <div class="flex items-center gap-2">
            <p class="font-bold text-base text-custom-black dark:text-white">{{ address.label }}</p>
          </div>
          <p class="font-semibold text-sm text-custom-black dark:text-gray-200">{{ address.recipient_name }}</p>
          <p class="text-custom-grey dark:text-gray-400 text-sm">{{ address.phone }}</p>
        </div>

        <p class="text-sm text-custom-grey dark:text-gray-400 leading-relaxed line-clamp-3 mb-5 min-h-[50px]">
          {{ address.address }}, {{ address.city }}, {{ address.postal_code }}
        </p>

        <!-- Actions -->
        <div class="flex items-center gap-3 mt-auto">
          <RouterLink
            :to="{ name: 'user.settings.address.edit', params: { id: address.id } }"
            class="flex-1 py-2.5 text-center bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-xl text-sm font-bold text-custom-black dark:text-white hover:bg-gray-100 dark:hover:bg-white/10 hover:border-gray-200 dark:hover:border-white/20 transition-all"
          >
            Edit
          </RouterLink>
          <button
            class="flex-1 py-2.5 text-center bg-red-50 dark:bg-red-900/10 border border-red-100 dark:border-red-900/20 rounded-xl text-sm font-bold text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/20 hover:border-red-200 dark:hover:border-red-900/30 transition-all"
            @click="deleteAddress(address.id)"
          >
            Hapus
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
