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
    <div class="card flex flex-col rounded-[20px] border border-custom-stroke overflow-hidden">
        <div class="thumbnail w-full h-[202px] overflow-hidden bg-custom-background items-center justify-center">
            <img :src="item.logo" class="size-full object-cover" alt="thumbnail">
        </div>
        <div class="flex flex-col p-5 gap-6 bg-white">
            <div class="flex flex-col gap-2 w-full min-w-0 overflow-hidden">
                <div class="flex items-center w-fit shrink-0 gap-1">
                    <img src="@/assets/images/icons/verify-star.svg" class="flex size-6 shrink-0" alt="icon">
                    <p class="font-bold text-custom-blue text-nowrap uppercase">Verified Store
                    </p>
                </div>
                <p class="font-bold text-xl leading-tight w-full truncate">
                    {{ item.name }}
                </p>
            </div>
            <hr class="border-custom-stroke">
            <div class="grid grid-cols-2 gap-6">
                <div class="flex flex-col gap-[6px]">
                    <p class="font-bold text-lg leading-tight truncate">{{ item.user?.name }}</p>
                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                        <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon">
                        Seller Name
                    </p>
                </div>
                <div class="flex flex-col gap-[6px]">
                    <p class="font-bold text-lg leading-tight">{{ item.city }}</p>
                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                        <img src="@/assets/images/icons/location-grey.svg" class="size-5" alt="icon">
                        Store Location
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3 w-full">
                <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                    <div class="relative size-6">
                        <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                        <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                    </div>
                </button>
                <RouterLink v-if="item?.username" :to="{ name: 'app.store-detail', params: { username: item.username } }" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                    <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Visit Store</span>
                </RouterLink>
            </div>
        </div>
    </div>
</template>