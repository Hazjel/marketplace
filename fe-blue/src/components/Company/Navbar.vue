<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'

const isScrolled = ref(false)

const handleScroll = () => {
  isScrolled.value = window.scrollY > 20
}

onMounted(() => {
  window.addEventListener('scroll', handleScroll)
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
})
</script>

<template>
  <nav
    :class="[
      'fixed top-0 w-full z-50 transition-all duration-300 border-b',
      isScrolled
        ? 'bg-white/80 backdrop-blur-md border-gray-200 py-4 shadow-sm'
        : 'bg-transparent border-transparent py-6'
    ]"
  >
    <div class="max-w-[1280px] mx-auto px-5 md:px-[52px] flex items-center justify-between">
      <!-- Logo -->
      <RouterLink :to="{ name: 'app.home' }" class="flex items-center gap-2">
        <img src="@/assets/images/logos/blukios_logo.png" class="h-10" alt="Blukios Logo" />
        <span
          :class="[
            'font-bold text-xl tracking-tight hidden md:block',
            isScrolled ? 'text-custom-black' : 'text-white mix-blend-difference'
          ]"
        >
          Company
        </span>
      </RouterLink>

      <!-- Links -->
      <div class="hidden md:flex items-center gap-8">
        <RouterLink
          :to="{ name: 'app.about' }"
          active-class="text-custom-blue font-semibold"
          :class="
            isScrolled
              ? 'text-custom-grey hover:text-custom-blue'
              : 'text-white/80 hover:text-white mix-blend-difference'
          "
        >
          About Us
        </RouterLink>
        <RouterLink
          :to="{ name: 'app.career' }"
          active-class="text-custom-blue font-semibold"
          :class="
            isScrolled
              ? 'text-custom-grey hover:text-custom-blue'
              : 'text-white/80 hover:text-white mix-blend-difference'
          "
        >
          Career
        </RouterLink>
        <RouterLink
          :to="{ name: 'app.privacy' }"
          active-class="text-custom-blue font-semibold"
          :class="
            isScrolled
              ? 'text-custom-grey hover:text-custom-blue'
              : 'text-white/80 hover:text-white mix-blend-difference'
          "
        >
          Privacy
        </RouterLink>
      </div>

      <!-- CTA -->
      <div class="flex items-center gap-4">
        <RouterLink
          :to="{ name: 'app.home' }"
          :class="[
            'px-6 py-2.5 rounded-full font-bold text-sm transition-all',
            isScrolled
              ? 'bg-custom-black text-white hover:bg-gray-800'
              : 'bg-white text-custom-black hover:bg-gray-100'
          ]"
        >
          Go to Marketplace
        </RouterLink>
      </div>
    </div>
  </nav>
</template>
