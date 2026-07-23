<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: String, required: true } // pending | processing | delivering | completed
})

const config = {
  pending: {
    gradient: 'from-amber-400 to-amber-500',
    shadow: 'shadow-amber-500/20',
    title: 'Menunggu Konfirmasi',
    subtitle: 'Pesanan sedang direview oleh penjual',
    icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'
  },
  processing: {
    gradient: 'from-blue-500 to-blue-600',
    shadow: 'shadow-blue-500/20',
    title: 'Sedang Diproses',
    subtitle: 'Penjual sedang menyiapkan pesanan untuk dikirim',
    icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'
  },
  delivering: {
    gradient: 'from-orange-500 to-orange-600',
    shadow: 'shadow-orange-500/20',
    title: 'Sedang Dikirim',
    subtitle: 'Pesanan dalam perjalanan ke alamat kamu',
    icon: 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12'
  },
  completed: {
    gradient: 'from-green-500 to-emerald-600',
    shadow: 'shadow-green-500/20',
    title: 'Pesanan Selesai',
    subtitle: 'Pesanan telah diterima dengan baik',
    icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
  }
}

const current = computed(() => config[props.status])
</script>

<template>
  <div
    v-if="current"
    class="relative w-full rounded-2xl overflow-hidden bg-gradient-to-r shadow-lg"
    :class="[current.gradient, current.shadow]"
  >
    <div class="absolute inset-0 opacity-10">
      <div class="absolute -top-10 -right-10 size-40 bg-white/30 rounded-full blur-2xl"></div>
    </div>
    <div class="relative flex items-center min-h-[72px] gap-3 p-5">
      <div class="size-10 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
        <svg class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" :d="current.icon" />
        </svg>
      </div>
      <div>
        <p class="font-medium text-white text-base">{{ current.title }}</p>
        <p class="text-white/80 text-sm">{{ current.subtitle }}</p>
      </div>
    </div>
  </div>
</template>
