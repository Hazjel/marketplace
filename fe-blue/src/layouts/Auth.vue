<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Swiper from 'swiper'
import { Autoplay, Navigation, EffectFade } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/effect-fade'

const authStore = useAuthStore()
const router = useRouter()
const swiperInstance = ref(null)

onMounted(() => {
  if (authStore.token) {
    router.replace({ name: 'admin.dashboard' })
  }

  const swiper = new Swiper('.swiper', {
    modules: [Autoplay, Navigation, EffectFade],
    direction: 'horizontal',
    loop: true,
    autoplay: {
      delay: 5000,
      disableOnInteraction: false
    },
    effect: 'fade',
    fadeEffect: {
      crossFade: true
    }
  })

  swiperInstance.value = swiper
})
</script>

<template>
  <main
    class="flex min-h-screen w-full font-sans text-custom-black bg-white dark:bg-surface dark:text-white"
  >
    <!-- Left Side: Cinematic Slider (Fixed Width or 50%) -->
    <div class="hidden lg:block w-[50%] h-screen relative overflow-hidden bg-gray-900 sticky top-0">
      <div class="swiper w-full h-full">
        <div class="swiper-wrapper">
          <!-- Slide 1 -->
          <div class="swiper-slide relative flex w-full h-full overflow-hidden group">
            <img
              src="@/assets/images/backgrounds/login-slide-1.webp"
              class="w-full h-full object-cover object-top ken-burns"
              alt="thumbnail"
            />

            <!-- Glassmorphism Testimonial Card -->
            <div
              class="absolute bottom-12 left-12 right-12 flex flex-col p-8 rounded-3xl border border-white/20 bg-white/10 backdrop-blur-md text-white shadow-2xl"
            >
              <!-- Stars -->
              <div class="flex items-center gap-1 mb-6">
                <img
                  src="@/assets/images/icons/Star-rounded.svg"
                  class="size-6 shrink-0"
                  alt="star"
                />
                <img
                  src="@/assets/images/icons/Star-rounded.svg"
                  class="size-6 shrink-0"
                  alt="star"
                />
                <img
                  src="@/assets/images/icons/Star-rounded.svg"
                  class="size-6 shrink-0"
                  alt="star"
                />
                <img
                  src="@/assets/images/icons/Star-rounded.svg"
                  class="size-6 shrink-0"
                  alt="star"
                />
                <img
                  src="@/assets/images/icons/Star-rounded.svg"
                  class="size-6 shrink-0"
                  alt="star"
                />
              </div>

              <!-- Quote -->
              <p class="font-serif text-2xl lg:text-3xl leading-relaxed italic opacity-95 mb-8">
                “Since joining Blue Marketplace, managing my inventory and connecting with buyers
                has been seamless and efficient.”
              </p>

              <!-- Footer: Author & Arrows -->
              <div class="flex items-end justify-between">
                <!-- Author Info -->
                <div class="flex items-center gap-4">
                  <div class="w-12 h-0.5 bg-white/70 rounded-full"></div>
                  <div>
                    <p class="font-semibold text-xl leading-tight">Daisy Hakura</p>
                    <p class="text-sm opacity-80 mt-1">Happy Seller</p>
                  </div>
                </div>

                <!-- Navigation Arrows (Inside Card) -->
                <div class="flex items-center gap-3">
                  <div
                    class="flex size-10 cursor-pointer hover:bg-white/20 rounded-full items-center justify-center transition-all bg-white/10 border border-white/20 backdrop-blur-sm shadow-md active:scale-95"
                    @click="swiperInstance?.slidePrev()"
                  >
                    <img
                      src="@/assets/images/icons/arrow-right-white.svg"
                      class="size-5 rotate-180"
                      alt="prev"
                    />
                  </div>
                  <div
                    class="flex size-10 cursor-pointer hover:bg-white/20 rounded-full items-center justify-center transition-all bg-white/10 border border-white/20 backdrop-blur-sm shadow-md active:scale-95"
                    @click="swiperInstance?.slideNext()"
                  >
                    <img
                      src="@/assets/images/icons/arrow-right-white.svg"
                      class="size-5"
                      alt="next"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Slide 2 -->
          <div class="swiper-slide relative flex w-full h-full overflow-hidden group">
            <img
              src="@/assets/images/backgrounds/login-slide-2.webp"
              class="w-full h-full object-cover object-top ken-burns"
              alt="thumbnail"
            />
            <div
              class="absolute bottom-12 left-12 right-12 flex flex-col p-8 rounded-3xl border border-white/20 bg-white/10 backdrop-blur-md text-white shadow-2xl"
            >
              <div class="flex items-center gap-1 mb-6">
                <img
                  src="@/assets/images/icons/Star-rounded.svg"
                  class="size-6 shrink-0"
                  alt="star"
                />
                <img
                  src="@/assets/images/icons/Star-rounded.svg"
                  class="size-6 shrink-0"
                  alt="star"
                />
                <img
                  src="@/assets/images/icons/Star-rounded.svg"
                  class="size-6 shrink-0"
                  alt="star"
                />
                <img
                  src="@/assets/images/icons/Star-rounded.svg"
                  class="size-6 shrink-0"
                  alt="star"
                />
                <img
                  src="@/assets/images/icons/Star-rounded.svg"
                  class="size-6 shrink-0"
                  alt="star"
                />
              </div>
              <p class="font-serif text-2xl lg:text-3xl leading-relaxed italic opacity-95 mb-8">
                “Going digital was a game changer 🎯 It simplified growth and cut out the chaos.”
              </p>
              <div class="flex items-end justify-between">
                <div class="flex items-center gap-4">
                  <div class="w-12 h-0.5 bg-white/70 rounded-full"></div>
                  <div>
                    <p class="font-semibold text-xl leading-tight">Robert Lupin</p>
                    <p class="text-sm opacity-80 mt-1">Business Owner</p>
                  </div>
                </div>
                <!-- Navigation Arrows Placeholder (Controlled by Swiper outside usually, but here we duplicate for visual consistentcy if logic allows. NOTE: Swiper nav elements must be unique or scoped. The default layout shared arrows. I'll put shared arrows hidden or rely on these buttons if I can scope them?) -->
                <!-- Use same class names 'custom-prev' 'custom-next' works if they are distinct or if swiper binds to all. Swiper typically binds to one set of selectors. -->
                <!-- BETTER APPROACH: Keep logic simple. Put ONE set of arrows ABSOLUTE positioned on top of the swiper, but inside the bounds of where the card WOULD be? No, card moves with slide. -->
                <!-- If arrows are inside the slide, they move with it. Swiper navigation needs static elements usually, OR it can handle dynamic elements if configured correctly. -->
                <!-- Safest bet: Absolute positioned arrows that overlay the card area. -->
                <!-- BUT the request is "move card section like that". -->
                <!-- If I put arrows inside every slide, I need to make sure click events bubble up or Swiper detects them. -->
                <!-- Actually, Swiper `navigation` option takes a selector. If multiple elements have that class, it might work or might not. -->
                <!-- Alternative: Put the card container STATICALLY on top of the swiper, and only text changes? No, image changes too. -->
                <!-- I will keep the arrows inside the slide for Visuals. -->
                <div class="flex items-center gap-3">
                  <div
                    class="flex size-10 cursor-pointer hover:bg-white/20 rounded-full items-center justify-center transition-all bg-white/10 border border-white/20 backdrop-blur-sm shadow-md active:scale-95"
                    @click="swiperInstance?.slidePrev()"
                  >
                    <img
                      src="@/assets/images/icons/arrow-right-white.svg"
                      class="size-5 rotate-180"
                      alt="prev"
                    />
                  </div>
                  <div
                    class="flex size-10 cursor-pointer hover:bg-white/20 rounded-full items-center justify-center transition-all bg-white/10 border border-white/20 backdrop-blur-sm shadow-md active:scale-95"
                    @click="swiperInstance?.slideNext()"
                  >
                    <img
                      src="@/assets/images/icons/arrow-right-white.svg"
                      class="size-5"
                      alt="next"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Side: Form Container -->
    <div
      class="flex w-full lg:w-[50%] min-h-screen items-center justify-center p-6 lg:p-24 relative overflow-y-auto bg-white dark:bg-surface"
    >
      <div class="w-full max-w-[480px]">
        <RouterView />
      </div>
    </div>
  </main>
</template>
