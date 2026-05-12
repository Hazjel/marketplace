<script setup>
import Swiper from 'swiper'
import { Navigation, Autoplay, Pagination } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/autoplay'
import 'swiper/css/pagination'
import { onMounted } from 'vue'

onMounted(() => {
  new Swiper('.heroSwiper', {
    modules: [Navigation, Autoplay, Pagination],
    loop: true,
    spaceBetween: 0,
    autoplay: {
      delay: 4000,
      disableOnInteraction: false
    },
    speed: 600,
    slidesPerView: 1,
    navigation: {
      nextEl: '.hero-next',
      prevEl: '.hero-prev'
    },
    pagination: {
      el: '.hero-pagination',
      clickable: true
    }
  })
})
</script>

<template>
  <section class="group relative w-full max-w-[1280px] mx-auto px-4 lg:px-6 mt-4 md:mt-6">
    <div class="heroSwiper w-full rounded-2xl overflow-hidden">
      <div class="swiper-wrapper">
        <!-- Slide 1 -->
        <div class="swiper-slide">
          <div class="relative w-full aspect-[2.5/1] md:aspect-[3/1] bg-gradient-to-r from-blue-600 to-indigo-700 overflow-hidden">
            <img
              src="@/assets/images/banners/banner_mega_sale.png"
              class="w-full h-full object-cover"
              alt="Promo"
            />
          </div>
        </div>
        <!-- Slide 2 -->
        <div class="swiper-slide">
          <div class="relative w-full aspect-[2.5/1] md:aspect-[3/1] bg-gradient-to-r from-purple-600 to-pink-600 overflow-hidden">
            <img
              src="@/assets/images/banners/banner_gadgets.png"
              class="w-full h-full object-cover"
              alt="Gadgets"
            />
          </div>
        </div>
        <!-- Slide 3 -->
        <div class="swiper-slide">
          <div class="relative w-full aspect-[2.5/1] md:aspect-[3/1] bg-gradient-to-r from-emerald-600 to-teal-600 overflow-hidden">
            <img
              src="@/assets/images/banners/banner_mega_sale.png"
              class="w-full h-full object-cover"
              alt="Sale"
            />
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div class="hero-pagination absolute bottom-3 left-1/2 -translate-x-1/2 z-10 flex gap-1.5"></div>
    </div>

    <!-- Navigation Arrows (hover reveal) -->
    <button
      class="hero-prev absolute left-6 lg:left-8 top-1/2 -translate-y-1/2 z-20 size-9 md:size-10 flex items-center justify-center rounded-full bg-white/90 dark:bg-gray-800/90 shadow-md opacity-0 group-hover:opacity-100 transition-all hover:bg-white hover:scale-110 cursor-pointer"
    >
      <svg class="size-4 md:size-5 text-gray-700 dark:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
      </svg>
    </button>
    <button
      class="hero-next absolute right-6 lg:right-8 top-1/2 -translate-y-1/2 z-20 size-9 md:size-10 flex items-center justify-center rounded-full bg-white/90 dark:bg-gray-800/90 shadow-md opacity-0 group-hover:opacity-100 transition-all hover:bg-white hover:scale-110 cursor-pointer"
    >
      <svg class="size-4 md:size-5 text-gray-700 dark:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
      </svg>
    </button>
  </section>
</template>
