<script setup>
import { Card } from '@/components/ui/card'
import { resolveIconUrl } from '@/helpers/iconHelper'

defineProps({
  title: { type: String, required: true },
  value: { type: [String, Number], required: true },
  icon: { type: String, default: 'wallet-2-blue-fill.svg' },
  // 'plain' = card putih + icon bulat; 'gradient' = card warna solid gradient
  variant: { type: String, default: 'plain' },
  // Dipakai saat variant='gradient'. Contoh: 'from-[#2563EB] to-blue-700'
  color: { type: String, default: 'from-[#2563EB] to-blue-700' },
  // { value: '12%', direction: 'up' | 'down' } — opsional, hanya render kalau ada data nyata
  trend: { type: Object, default: null }
})
</script>

<template>
  <Card
    v-if="variant === 'gradient'"
    :class="`relative overflow-hidden rounded-2xl p-6 border-transparent bg-gradient-to-br ${color} text-white shadow-lg`"
  >
    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-10 -mt-10"></div>
    <div class="relative flex flex-col gap-4">
      <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm">
        <img :src="resolveIconUrl(icon)" class="size-6 shrink-0 invert" alt="icon" />
      </div>
      <div>
        <p class="text-sm font-medium text-white/80">{{ title }}</p>
        <p class="text-2xl font-bold mt-1">{{ value }}</p>
      </div>
      <div v-if="trend" class="flex items-center gap-1 text-xs font-medium text-emerald-200">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="w-3.5 h-3.5"
          :class="{ 'rotate-180': trend.direction === 'down' }"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="2"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"
          />
        </svg>
        <span>{{ trend.value }}% dari minggu lalu</span>
      </div>
    </div>
  </Card>

  <Card
    v-else
    class="flex flex-col w-full rounded-[20px] p-5 gap-6 border-transparent hover:border-custom-blue/20 dark:hover:border-blue-400/50 transition-all shadow-[0_2px_10px_rgba(0,0,0,0.02)] bg-white dark:bg-surface-card"
  >
    <div class="flex flex-col gap-6">
      <div class="flex justify-between items-start">
        <div class="flex size-[56px] bg-custom-blue/10 dark:bg-blue-500/20 items-center justify-center rounded-2xl">
          <img :src="resolveIconUrl(icon)" class="flex size-6 shrink-0 dark:invert" alt="icon" />
        </div>
        <div
          v-if="trend"
          class="flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-full"
          :class="
            trend.direction === 'up'
              ? 'bg-green-100 dark:bg-green-500/20 text-custom-green dark:text-green-400'
              : 'bg-red-100 dark:bg-red-500/20 text-custom-red dark:text-red-400'
          "
        >
          <i class="fa-solid" :class="trend.direction === 'up' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'"></i>
          {{ trend.value }}%
        </div>
      </div>
      <div class="flex flex-col gap-[6px]">
        <p class="font-bold text-2xl md:text-3xl text-custom-black dark:text-white tracking-tight">{{ value }}</p>
        <p class="font-medium text-sm text-custom-grey dark:text-gray-400">{{ title }}</p>
      </div>
    </div>
  </Card>
</template>
