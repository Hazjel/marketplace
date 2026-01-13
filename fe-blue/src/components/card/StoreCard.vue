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
    <div class="card flex flex-col rounded-[20px] border border-custom-stroke overflow-hidden h-full group">
        <div class="thumbnail w-full h-[130px] md:h-[202px] overflow-hidden bg-custom-background items-center justify-center">
            <img :src="item.logo" class="size-full object-cover transition-transform duration-500 group-hover:scale-105" alt="thumbnail">
        </div>
        <div class="flex flex-col p-4 md:p-5 gap-4 md:gap-6 bg-white flex-1">
            <div class="flex flex-col gap-2 w-full min-w-0 overflow-hidden">
                <div class="flex items-center w-fit shrink-0 gap-1" v-if="item?.is_verified">
                    <img src="@/assets/images/icons/verify-star.svg" class="flex size-5 md:size-6 shrink-0" alt="icon">
                    <p class="font-bold text-custom-blue text-nowrap uppercase text-xs md:text-sm">Verified Store
                    </p>
                </div>
                <p class="font-bold text-base md:text-xl leading-tight w-full truncate">
                    {{ item.name }}
                </p>
            </div>
            <hr class="border-custom-stroke">
            <div class="grid grid-cols-2 gap-4 md:gap-6 mt-auto">
                <div class="flex flex-col gap-[6px]">
                    <p class="font-bold text-sm md:text-lg leading-tight truncate">{{ item.user?.name }}</p>
                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none text-xs md:text-base">
                        <img src="@/assets/images/icons/user-grey.svg" class="size-4 md:size-5" alt="icon">
                        Seller Name
                    </p>
                </div>
                <div class="flex flex-col gap-[6px]">
                    <p class="font-bold text-sm md:text-lg leading-tight truncate">{{ item.city }}</p>
                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none text-xs md:text-base">
                        <img src="@/assets/images/icons/location-grey.svg" class="size-4 md:size-5" alt="icon">
                        Location
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3 w-full">
                <RouterLink v-if="item?.username" :to="{ name: 'app.store-detail', params: { username: item.username } }" class="group/btn flex items-center justify-center h-10 md:h-14 w-full rounded-2xl p-2 md:p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                    <span class="font-semibold text-sm md:text-base text-custom-blue group-hover/btn:text-white transition-300">Visit Store</span>
                </RouterLink>
            </div>
        </div>
    </div>
</template>