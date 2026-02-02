<script setup>
import Swiper from 'swiper'
import { Navigation, Autoplay, Pagination } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/autoplay'
import 'swiper/css/pagination'
import { onMounted } from 'vue'

onMounted(() => {
  new Swiper('.swiper', {
    modules: [Navigation, Autoplay, Pagination],
    loop: true,
    spaceBetween: 0,
    autoplay: {
      delay: 3000,
      disableOnInteraction: false
    },
    speed: 800,
    slidesPerView: 1,
    navigation: {
      nextEl: '.swiper-button-next-custom',
      prevEl: '.swiper-button-prev-custom'
    },
    pagination: {
      el: '.swiper-pagination',
      clickable: true
    }
  })
})
</script>

<template>
  <section
    id="Promo"
    class="group relative w-full max-w-[1208px] mx-auto px-4 md:px-0 mt-6 md:mt-8 animate-fade-in-up"
  >
    <div class="swiper w-full rounded-xl md:rounded-3xl overflow-hidden shadow-sm">
      <!-- Slides with Responsive Aspect Ratio -->
      <div class="swiper-wrapper">
        <div class="swiper-slide w-full">
          <a
            href=""
            class="block w-full aspect-4/5 md:aspect-21/9 relative bg-custom-icon-background overflow-hidden group/slide"
          >
            <img
              src="@/assets/images/banners/banner_mega_sale.png"
              class="size-full object-cover group-hover/slide:scale-105 transition-transform duration-700"
              alt="Mega Sale"
            />
            <!-- Cinematic Overlay -->
            <div
              class="absolute inset-0 bg-linear-to-t from-black/60 via-transparent to-transparent opacity-60"
            ></div>
          </a>
        </div>
        <div class="swiper-slide w-full">
          <a
            href=""
            class="block w-full aspect-4/5 md:aspect-21/9 relative bg-custom-icon-background overflow-hidden group/slide"
          >
            <img
              src="@/assets/images/banners/banner_gadgets.png"
              class="size-full object-cover group-hover/slide:scale-105 transition-transform duration-700"
              alt="Gadgets"
            />
            <div
              class="absolute inset-0 bg-linear-to-t from-black/60 via-transparent to-transparent opacity-60"
            ></div>
          </a>
        </div>
        <div class="swiper-slide w-full">
          <a
            href=""
            class="block w-full aspect-4/5 md:aspect-21/9 relative bg-custom-icon-background overflow-hidden group/slide"
          >
            <img
              src="@/assets/images/banners/banner_mega_sale.png"
              class="size-full object-cover group-hover/slide:scale-105 transition-transform duration-700"
              alt="Special Offer"
            />
            <div
              class="absolute inset-0 bg-linear-to-t from-black/60 via-transparent to-transparent opacity-60"
            ></div>
          </a>
        </div>
      </div>
      <!-- Pagination -->
      <div class="swiper-pagination !bottom-4 !left-8 !w-fit"></div>
    </div>

    <!-- Custom Navigation Buttons -->
    <!-- Prev Button -->
    <button
      class="swiper-button-prev-custom absolute left-6 top-1/2 -translate-y-1/2 z-20 flex size-8 md:size-10 items-center justify-center rounded-full bg-white/90 dark:bg-surface-card shadow-lg text-custom-black dark:text-white opacity-0 group-hover:opacity-100 transition-all duration-300 hover:scale-110 hover:bg-white dark:hover:bg-custom-blue disabled:opacity-0 cursor-pointer"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="2.5"
        stroke="currentColor"
        class="size-4 md:size-5"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
      </svg>
    </button>
    <!-- Next Button -->
    <button
      class="swiper-button-next-custom absolute right-6 top-1/2 -translate-y-1/2 z-20 flex size-8 md:size-10 items-center justify-center rounded-full bg-white/90 dark:bg-surface-card shadow-lg text-custom-black dark:text-white opacity-0 group-hover:opacity-100 transition-all duration-300 hover:scale-110 hover:bg-white dark:hover:bg-custom-blue disabled:opacity-0 cursor-pointer"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="2.5"
        stroke="currentColor"
        class="size-4 md:size-5"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
      </svg>
    </button>
  </section>
</template>

<style>
.swiper-pagination-bullet {
  background-color: rgba(255, 255, 255, 0.5);
  opacity: 1;
  width: 0.5rem;
  /* size-2 */
  height: 0.5rem;
  /* size-2 */
  transition: all 300ms;
}

.swiper-pagination-bullet-active {
  background-color: #ffffff;
  width: 1rem;
  /* w-4 */
  border-radius: 9999px;
  /* rounded-full */
}
</style>
