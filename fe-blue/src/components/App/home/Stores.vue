<script setup>
import Swiper from 'swiper/bundle';
import 'swiper/swiper-bundle.css'
import { onMounted, computed, nextTick } from 'vue';
import StoreCard from '@/components/card/StoreCard.vue';
import { useStoreStore } from '@/stores/store';
import { storeToRefs } from 'pinia';
import { chunk } from 'lodash';

const storeStore = useStoreStore()
const { stores, loading } = storeToRefs(storeStore)
const { fetchStores } = storeStore

const storeChunks = computed(() => {
    return chunk(stores.value, 3);
});

onMounted(async () => {
    await fetchStores({
        limit: 9,
        random: true
    })

    nextTick(() => {
        if (stores.value.length > 0) {
            new Swiper('.storeSwiper', {
                loop: stores.value.length > 3, // Only loop if enough slides
                slidesPerView: 1,
                spaceBetween: 24,
                pagination: {
                    el: ".store-pagination",
                    clickable: true,
                    bulletActiveClass: 'swiper-pagination-bullet-active !bg-blue-600',
                    renderBullet: function (index, className) {
                        return '<span class="flex shrink-0 w-[42px] h-1 rounded-full bg-custom-stroke ' + className + '"></span>';
                    },
                },
                navigation: {
                    nextEl: ".store-next",
                    prevEl: ".store-prev",
                },
            });
        }
    });
});
</script>

<template>
    <section id="Trusted-Seller" class="flex flex-col gap-6 md:gap-9 animate-fade-in-up delay-300">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-extrabold text-lg md:text-[32px] leading-tight">Trusted Sellers,<br class="hidden md:block"> Quality Guaranteed</h2>
            <RouterLink :to="{ name: 'app.all-stores' }"
                class="flex shrink-0 items-center h-10 md:h-14 rounded-[18px] py-2 px-3 md:py-4 md:px-6 gap-[10px] bg-custom-black">
                <span class="font-medium text-white hidden md:block">VIEW ALL</span>
                <img src="@/assets/images/icons/arrow-right-white.svg" class="flex size-5 md:size-6 shrink-0" alt="icon">
            </RouterLink>
        </div>

        <div class="flex flex-col gap-6 relative">
            <div class="storeSwiper w-full overflow-hidden">
                <div class="swiper-wrapper">
                    <div class="swiper-slide w-full !grid !grid-cols-1 sm:!grid-cols-3 !gap-3 md:!gap-6"
                        v-for="(storeChunk, index) in storeChunks" :key="index">
                        <StoreCard v-for="store in storeChunk" :item="store" :key="store.id" />
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="relative flex items-center justify-center gap-6 h-14 w-fit mx-auto">
                <button type="button"
                    class="store-prev flex shrink-0 items-center justify-center size-14 rounded-full border border-custom-stroke cursor-pointer">
                    <img src="@/assets/images/icons/arrow-right-black.svg" class="size-6 rotate-180" alt="icon">
                </button>
                <div class="store-pagination flex items-center gap-2"></div>
                <button type="button"
                    class="store-next flex shrink-0 items-center justify-center size-14 rounded-full border border-custom-stroke cursor-pointer">
                    <img src="@/assets/images/icons/arrow-right-black.svg" class="size-6" alt="icon">
                </button>
            </div>
        </div>
    </section>
</template>

