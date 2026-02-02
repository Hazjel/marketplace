<script setup>
import { ref, onMounted, watch } from 'vue'
import Swiper from 'swiper/bundle'
import 'swiper/swiper-bundle.css'

const props = defineProps({
  images: {
    type: Array,
    required: true,
    default: () => []
  }
})

const activeImage = ref(null)
const showLightbox = ref(false)
const isZoomed = ref(false)
const zoomStyle = ref({})

const initSwiper = () => {
  // Small delay to ensure DOM is ready
  setTimeout(() => {
    new Swiper('.gallery-swiper', {
      loop: props.images.length > 1,
      slidesPerView: 1,
      spaceBetween: 0,
      pagination: {
        el: '.gallery-pagination',
        clickable: true,
        bulletActiveClass: '!bg-custom-blue !opacity-100 !w-6 !rounded-full',
        bulletClass:
          'swiper-pagination-bullet !w-1.5 !h-1.5 !bg-gray-300 !opacity-100 !rounded-full !transition-all !duration-300'
      }
    })
  }, 100)
}

const setActiveImage = (image) => {
  activeImage.value = image
}

// Mouseover Zoom Logic
const handleMouseMove = (e) => {
  if (!isZoomed.value) return

  const { left, top, width, height } = e.target.getBoundingClientRect()
  const x = ((e.clientX - left) / width) * 100
  const y = ((e.clientY - top) / height) * 100

  zoomStyle.value = {
    transformOrigin: `${x}% ${y}%`,
    transform: 'scale(2)'
  }
}

const toggleZoom = (val) => {
  isZoomed.value = val
  if (!val) {
    zoomStyle.value = { transform: 'scale(1)' }
  }
}

// Initialize active image when images are loaded
watch(
  () => props.images,
  (newImages) => {
    if (newImages && newImages.length > 0) {
      // Find thumbnail or first image
      const thumbnail = newImages.find((img) => img.is_thumbnail) || newImages[0]
      activeImage.value = thumbnail

      // Re-init swiper if needed
      initSwiper()
    }
  },
  { immediate: true }
)

onMounted(() => {
  initSwiper()
})
</script>

<template>
  <div class="flex flex-col w-full gap-4">
    <!-- Desktop View (Hidden on Mobile) -->
    <div class="hidden md:flex flex-row-reverse w-full gap-4 h-[450px]">
      <!-- Main Image Area -->
      <div
        class="relative flex-1 bg-custom-background rounded-2xl overflow-hidden cursor-zoom-in group"
        @mouseenter="toggleZoom(true)"
        @mouseleave="toggleZoom(false)"
        @mousemove="handleMouseMove"
        @click="showLightbox = true"
      >
        <img
          :src="activeImage?.image"
          class="size-full object-contain transition-transform duration-100 ease-out"
          :style="zoomStyle"
          alt="product detail"
        />

        <!-- Zoom Hint -->
        <div
          class="absolute bottom-4 right-4 bg-white/80 backdrop-blur-sm px-3 py-1.5 rounded-full text-xs font-bold text-custom-black opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none flex items-center gap-2"
        >
          <i class="fa-solid fa-magnifying-glass-plus"></i> Click to Expand
        </div>
      </div>

      <!-- Thumbnail Strip (Vertical) -->
      <div class="flex flex-col gap-3 w-[100px] h-full overflow-y-auto no-scrollbar py-1">
        <button
          v-for="image in images"
          :key="image.id"
          class="thumbnail-selector shrink-0 size-[100px] flex items-center justify-center rounded-xl bg-custom-background overflow-hidden border-2 border-transparent hover:border-custom-blue/50 transition-all duration-300 [&.active]:border-custom-blue"
          :class="{ active: image.image === activeImage?.image }"
          @click="setActiveImage(image)"
        >
          <img :src="image.image" class="size-full object-contain p-2" alt="thumbnail" />
        </button>
      </div>
    </div>

    <!-- Mobile View (Swiper) -->
    <div class="md:hidden w-full relative group">
      <div class="gallery-swiper w-full overflow-hidden aspect-square bg-custom-background">
        <div class="swiper-wrapper">
          <div
            v-for="image in images"
            :key="image.id"
            class="swiper-slide flex items-center justify-center"
            @click="showLightbox = true"
          >
            <img :src="image.image" class="size-full object-contain" alt="product image" />
          </div>
        </div>
      </div>
      <!-- Custom Pagination Container -->
      <div class="gallery-pagination flex justify-center gap-1 mt-4"></div>

      <div
        class="absolute bottom-3 right-3 bg-black/50 text-white text-xs px-2 py-1 rounded-md backdrop-blur-sm z-10 pointer-events-none"
      >
        {{ images.length }} Photos
      </div>
    </div>

    <!-- Lightbox Modal -->
    <div
      v-if="showLightbox"
      class="fixed inset-0 z-[100] bg-black/95 flex items-center justify-center animate-fade-in"
      @click.self="showLightbox = false"
    >
      <button
        class="absolute top-6 right-6 text-white text-4xl hover:text-gray-300 rotate-0 hover:rotate-90 transition-transform"
        @click="showLightbox = false"
      >
        &times;
      </button>

      <div class="w-full h-full max-w-5xl max-h-[90vh] p-4 flex items-center justify-center">
        <img
          :src="activeImage?.image"
          class="max-w-full max-h-full object-contain"
          alt="fullscreen"
        />
      </div>

      <!-- Lightbox Thumbnails -->
      <div
        class="absolute bottom-10 left-1/2 -translate-x-1/2 flex gap-4 overflow-x-auto max-w-[90vw] pb-2 hide-scrollbar"
      >
        <button
          v-for="image in images"
          :key="image.id"
          class="size-16 rounded-lg overflow-hidden border-2 transition-all opacity-50 hover:opacity-100"
          :class="
            image.image === activeImage?.image ? 'border-white opacity-100' : 'border-transparent'
          "
          @click.stop="setActiveImage(image)"
        >
          <img :src="image.image" class="size-full object-cover" />
        </button>
      </div>
    </div>
  </div>
</template>
