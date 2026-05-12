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
  title: 'Blukios — Belanja Semua Kebutuhanmu',
  meta: [
    {
      name: 'description',
      content:
        'Blukios adalah marketplace multi-vendor terpercaya. Belanja gadget, fashion, skincare, dan semua kebutuhanmu dari seller terpercaya.'
    }
  ]
})
</script>

<template>
  <!-- Hero Banner -->
  <Banner />

  <!-- Main Content -->
  <main class="flex flex-col w-full max-w-[1280px] px-4 lg:px-6 mx-auto mt-6 md:mt-10 mb-20 md:mb-24 gap-10 md:gap-14">
    <!-- Categories Section -->
    <Categories />

    <!-- Recommended Section -->
    <Recommended />

    <!-- Top Picks Section -->
    <TopPicks />

    <!-- Official Stores Section -->
    <Stores />
  </main>

  <!-- Chatbot -->
  <Chatbot />
</template>
