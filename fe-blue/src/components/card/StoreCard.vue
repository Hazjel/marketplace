<script setup>
import { RouterLink } from 'vue-router';

defineProps({
    item: {
        type: Object,
        required: true
    }
})
</script>

<template>
    <div class="card relative flex flex-col bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)] transition-all duration-300 overflow-hidden h-full group">
        <!-- Banner -->
        <div class="h-20 w-full bg-gradient-to-r from-blue-400 to-indigo-500 relative overflow-hidden">
            <img v-if="item.banner" :src="item.banner" class="w-full h-full object-cover opacity-50" loading="lazy" alt="banner">
             <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
        </div>

        <!-- Floating Logo -->
        <div class="absolute top-10 left-1/2 -translate-x-1/2">
            <div class="size-[70px] rounded-full border-4 border-white bg-white shadow-md overflow-hidden">
                <img :src="item.logo" class="w-full h-full object-cover" loading="lazy" alt="logo">
            </div>
        </div>

        <!-- Content -->
        <div class="flex flex-col items-center pt-12 pb-5 px-4 flex-1 text-center">
            
            <!-- Store Name & Verified -->
            <div class="flex flex-col items-center gap-1 w-full">
                <h3 class="font-bold text-lg text-custom-black leading-tight line-clamp-1 w-full" :title="item.name">
                    {{ item.name }}
                </h3>
                <div v-if="item?.is_verified" class="inline-flex items-center gap-1 bg-blue-50 px-2 py-0.5 rounded-full border border-blue-100">
                    <img src="@/assets/images/icons/verify-star.svg" class="size-3.5 shrink-0" alt="icon">
                    <span class="text-[10px] font-bold text-custom-blue uppercase tracking-wide">Official</span>
                </div>
            </div>

            <!-- Stats (Location / User) -->
            <div class="mt-4 flex items-center justify-center gap-3 text-xs text-custom-grey w-full">
                <div class="flex items-center gap-1 truncate max-w-[45%]">
                     <i class="fa-solid fa-location-dot text-[10px]"></i>
                     <span class="truncate">{{ item.city || 'Jakarta' }}</span>
                </div>
                <div class="w-1 h-1 rounded-full bg-gray-300 shrink-0"></div>
                <div class="flex items-center gap-1">
                    <i class="fa-solid fa-star text-custom-orange text-[10px]"></i>
                    <span>4.9 ({{ Math.floor(Math.random() * 50) + 1 }}k)</span>
                </div>
            </div>

            <!-- Action Button -->
            <div class="mt-auto w-full pt-5">
                <RouterLink v-if="item?.username" :to="{ name: 'app.store-detail', params: { username: item.username } }" 
                    class="flex items-center justify-center h-10 w-full rounded-full border border-custom-stroke font-bold text-sm text-custom-black bg-white hover:bg-custom-black hover:text-white hover:border-custom-black transition-all duration-300">
                   Kunjungi Toko
                </RouterLink>
            </div>
        </div>
    </div>
</template>