<script setup>
import 'swiper/swiper-bundle.css'

import Banner from '@/components/App/home/Banner.vue'
import Categories from '@/components/App/home/Categories.vue'
import TopPicks from '@/components/App/home/TopPicks.vue'
import Stores from '@/components/App/home/Stores.vue'
import Recommended from '@/components/App/home/Recommended.vue'
import Chatbot from '@/components/App/Chatbot.vue'

import { useHead } from '@vueuse/head'

useHead({
  title: 'Blukios | #1 Gadget Marketplace',
  meta: [
    {
      name: 'description',
      content:
        'Blukios is the best place to buy gadgets, electronics, and accessories. Official Stores, Quality Guaranteed.'
    }
  ]
})
</script>

<template>
  <Banner />
  <main
    class="flex flex-col gap-8 md:gap-12 w-full max-w-[1208px] px-4 md:px-0 mt-8 md:mt-12 mb-20 md:mb-24 mx-auto"
  >
    <Recommended />
    <Categories />
    <TopPicks />
    <Stores />
    <Chatbot />
  </main>
</template>
