<script setup>
import 'swiper/swiper-bundle.css'
import { onMounted } from 'vue';
import Banner from '@/components/App/home/Banner.vue';
import Categories from '@/components/App/home/Categories.vue';
import TopPicks from '@/components/App/home/TopPicks.vue';
import Stores from '@/components/App/home/Stores.vue';
import Chatbot from '@/components/App/Chatbot.vue';

import { useHead } from '@vueuse/head';

useHead({
    title: 'Blukios | #1 Gadget Marketplace',
    meta: [
        {
            name: 'description',
            content: 'Blukios is the best place to buy gadgets, electronics, and accessories. Official Stores, Quality Guaranteed.'
        }
    ]
})
</script>

<template>
    <Banner />
        <main class="flex flex-col gap-8 md:gap-[100px] w-full max-w-[1280px] px-4 mt-8 md:mt-[72px] mb-20 md:mb-[100px] mx-auto">
            <Categories />
            <TopPicks />
            <Stores />
            <Chatbot />
        </main>
</template>