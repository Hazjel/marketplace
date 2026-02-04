<script setup>
import { RouterLink } from 'vue-router'
import { Card, CardContent } from '@/components/ui/card'

defineProps({
  item: {
    type: Object,
    required: true
  }
})
</script>

<template>
  <Card
    class="relative flex flex-col overflow-hidden h-full group hover-glow-blue border-gray-100 dark:border-white/5 rounded-2xl">
    <!-- Banner -->
    <div class="h-20 w-full bg-linear-to-r from-blue-400 to-indigo-500 relative overflow-hidden">
      <img v-if="item.banner" :src="item.banner" class="w-full h-full object-cover opacity-50" loading="lazy"
        alt="banner" />
      <div class="absolute inset-0 bg-linear-to-t from-black/20 to-transparent"></div>
    </div>

    <!-- Floating Logo -->
    <div class="absolute top-10 left-1/2 -translate-x-1/2">
      <div
        class="size-[70px] rounded-full border-4 border-white dark:border-surface-card bg-white dark:bg-surface-card shadow-md overflow-hidden">
        <img :src="item.logo" class="w-full h-full object-cover" loading="lazy" alt="logo" />
      </div>
    </div>

    <!-- Content -->
    <CardContent class="flex flex-col items-center pt-12 pb-5 px-4 flex-1 text-center">
      <!-- Store Name & Verified -->
      <div class="flex flex-col items-center gap-1 w-full">
        <h3 class="font-bold text-lg text-custom-black dark:text-white leading-tight line-clamp-1 w-full"
          :title="item.name">
          {{ item.name }}
        </h3>
        <div v-if="item?.is_verified"
          class="inline-flex items-center gap-1 bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 rounded-full border border-blue-100 dark:border-blue-800">
          <img src="@/assets/images/icons/verify-star.svg" class="size-3.5 shrink-0 dark:brightness-0 dark:invert"
            alt="icon" />
          <span class="text-xs font-bold text-custom-blue uppercase tracking-wide">Official</span>
        </div>
      </div>

      <!-- Stats (Location / User) -->
      <div class="mt-4 flex items-center justify-center gap-3 text-xs text-custom-grey w-full">
        <div class="flex items-center gap-1 truncate max-w-[45%]">
          <i class="fa-solid fa-location-dot text-xs"></i>
          <span class="truncate">{{ item.city || 'Jakarta' }}</span>
        </div>
        <div class="w-1 h-1 rounded-full bg-gray-300 shrink-0"></div>
        <div class="flex items-center gap-1">
          <i class="fa-solid fa-star text-custom-orange text-xs"></i>
          <span>4.9 ({{ Math.floor(Math.random() * 50) + 1 }}k)</span>
        </div>
      </div>

      <!-- Action Button -->
      <div class="mt-auto w-full pt-5">
        <RouterLink v-if="item?.username" :to="{ name: 'app.store-detail', params: { username: item.username } }"
          class="flex items-center justify-center h-10 w-full rounded-full border border-custom-stroke dark:border-white/20 font-bold text-sm text-custom-black dark:text-white bg-white dark:bg-transparent hover:bg-custom-black hover:text-white dark:hover:bg-white dark:hover:text-black hover:border-custom-black dark:hover:border-white transition-all duration-300">
          Kunjungi Toko
        </RouterLink>
      </div>
    </CardContent>
  </Card>
</template>
