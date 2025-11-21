<script setup>
import Swiper from 'swiper/bundle';
import 'swiper/swiper-bundle.css'
import { onMounted } from 'vue';
import StoreCard from '@/components/card/StoreCard.vue';

import { useStoreStore } from '@/stores/store';
import { storeToRefs } from 'pinia';
import { computed } from 'vue';
import { nextTick } from 'vue';
import { chunk } from 'lodash';
import { random } from 'lodash';

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
                // Optional parameters
                loop: true,
                slidesPerView: 'auto',
                spaceBetween: 24,
                pagination: {
                    el: ".swiper-pagination",
                    clickable: true,
                    renderBullet: function (index, className) {
                        return '<span class="flex shrink-0 !w-[42px] !h-1 !rounded-full has-[&.swiper-pagination-bullet-active]:!bg-custom-blue ' + className + '"></span>';
                    },
                },
                navigation: {
                    nextEl: ".swiper-button-next",
                    prevEl: ".swiper-button-prev",
                },
            });
        }
    });
});
</script>

<template>
    <section id="Trusted-Seller" class="flex flex-col gap-9">
        <div class="flex items-center justify-between">
            <h2 class="font-extrabold text-[32px]">Trusted Sellers,<br>Quality Guaranteed</h2>
            <a href="#" class="flex items-center h-14 rounded-[18px] py-4 px-6 gap-[10px] bg-custom-black">
                <span class="font-medium text-white">VIEW ALL</span>
                <img src="@/assets/images/icons/arrow-right-white.svg" class="flex size-6 shrink-0" alt="icon">
            </a>
        </div>
        <div class="flex flex-col gap-6 relative">
            <div class="storeSwiper w-full overflow-hidden">
                <div class="swiper-wrapper">
                    <div class="swiper-slide w-full !grid !grid-cols-3 !gap-6" v-for="(chunk, index) in storeChunks" :key="index">
                        <StoreCard v-for="store in chunk" :item="store" :key="store.id" />
                    </div>
                </div>
            </div>
            <div class="relative flex items-center justify-center gap-6 h-14 w-fit mx-auto">
                <button class="swiper-button-prev !relative after:!content-[''] !mt-0 !top-0 !flex !shrink-0 !items-center !justify-center !size-14 !rounded-full !border !border-custom-stroke">
                    <img src="@/assets/images/icons/arrow-right-black.svg" class="size-6 rotate-180" alt="icon">
                </button>
                <div class="swiper-pagination !relative !flex !items-center !gap-2 !top-0 !bottom-0"></div>
                <button class="swiper-button-next !relative after:!content-[''] !mt-0 !top-0 !flex !shrink-0 !items-center !justify-center !size-14 !rounded-full !border !border-custom-stroke">
                    <img src="@/assets/images/icons/arrow-right-black.svg" class="size-6" alt="icon">
                </button>
            </div>
        </div>
    </section>
</template>