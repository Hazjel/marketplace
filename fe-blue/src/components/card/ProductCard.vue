<script setup>
import { formatRupiah } from '@/helpers/format';
import _ from 'lodash';

defineProps({
    item: {
        type: Object,
        required: true
    }
})
</script>

<template>
    <div class="card flex flex-col rounded-t-[20px] overflow-hidden" >
        <RouterLink :to="{ name: 'app.product-detail', params: { slug: item.slug } }">
            <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                <img :src="item?.product_images?.find(image => image.is_thumbnail)?.image" 
                class="size-full object-contain" alt="thumbnail">
            </div>
        </RouterLink>
        <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 p-5 gap-6 bg-white">
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-3">
                    <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                        <span class="font-bold text-custom-blue">{{_.truncate( item?.product_category?.name, { length: 12 }) }}</span>
                    </div>
                    <p class="font-semibold text-custom-red">120 Sold</p>
                </div>
                <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                    <RouterLink :to="{ name: 'app.product-detail', params: { slug: item.slug } }">
                        <p class="font-bold text-xl w-full truncate">{{ item?.name }}</p>
                    </RouterLink>
                    <p class="font-bold text-xl text-custom-blue">{{ formatRupiah(item?.price) }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 w-full">
                <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                    <div class="relative size-6">
                        <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                        <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                    </div>
                </button>
                <RouterLink :to="{ name: 'app.product-detail', params: { slug: item.slug } }" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                    <div class="flex size-6 shrink-0 relative">
                        <img src="@/assets/images/icons/shopping-cart-blue.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                        <img src="@/assets/images/icons/shopping-cart-white.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                    </div>
                    <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Add to Cart</span>
                </RouterLink>
            </div>
        </div>
    </div>
</template>