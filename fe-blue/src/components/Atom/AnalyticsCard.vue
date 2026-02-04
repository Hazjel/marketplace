<script setup>
import { Card } from '@/components/ui/card'

defineProps({
  title: { type: String, required: true },
  value: { type: [String, Number], required: true },
  icon: { type: String, default: 'wallet-2-blue-fill.svg' },
  trend: { type: Object, default: null } // { value: '12%', direction: 'up' | 'down' }
})
</script>

<template>
  <Card
    class="flex flex-col w-full rounded-[20px] p-5 gap-6 border-transparent hover:border-custom-blue/20 dark:hover:border-blue-400/50 transition-all shadow-[0_2px_10px_rgba(0,0,0,0.02)] bg-white dark:bg-surface-card">
    <div class="flex flex-col gap-6">
      <div class="flex justify-between items-start">
        <div class="flex size-[56px] bg-custom-blue/10 dark:bg-blue-500/20 items-center justify-center rounded-2xl">
          <img :src="`/src/assets/images/icons/${icon}`" class="flex size-6 shrink-0 dark:invert" alt="icon" />
        </div>

        <div v-if="trend" class="flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-full" :class="trend.direction === 'up'
            ? 'bg-green-100 dark:bg-green-500/20 text-custom-green dark:text-green-400'
            : 'bg-red-100 dark:bg-red-500/20 text-custom-red dark:text-red-400'
          ">
          <i class="fa-solid" :class="trend.direction === 'up' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'"></i>
          {{ trend.value }}
        </div>
      </div>

      <div class="flex flex-col gap-[6px]">
        <p class="font-bold text-2xl md:text-3xl text-custom-black dark:text-white tracking-tight">{{ value }}</p>
        <p class="font-medium text-sm text-custom-grey dark:text-gray-400">{{ title }}</p>
      </div>
    </div>
  </Card>
</template>
