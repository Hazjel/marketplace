<script setup>
import { ref, onMounted, watch } from 'vue';
import Swiper from 'swiper/bundle';
import 'swiper/swiper-bundle.css';

const props = defineProps({
    images: {
        type: Array,
        required: true,
        default: () => []
    }
});

const activeImage = ref(null);


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
                bulletActiveClass: '!bg-custom-blue !opacity-100',
            },
        });
    }, 100);
};


const setActiveImage = (image) => {
    activeImage.value = image;
};

// Initialize active image when images are loaded
watch(() => props.images, (newImages) => {
    if (newImages && newImages.length > 0) {
        // Find thumbnail or first image
        const thumbnail = newImages.find(img => img.is_thumbnail) || newImages[0];
        activeImage.value = thumbnail;
        
        // Re-init swiper if needed
        initSwiper();
    }
}, { immediate: true });


onMounted(() => {
    initSwiper();
});
</script>

<template>
    <div class="flex flex-col w-full gap-4">
        <!-- Desktop View (Hidden on Mobile) -->
        <div class="hidden md:flex flex-col gap-3">
            <div class="flex w-full h-[365px] bg-custom-background rounded-2xl items-center justify-center overflow-hidden">
                <img :src="activeImage?.image" class="size-full object-contain" alt="thumbnail">
            </div>
            <div class="grid grid-cols-4 gap-3">
                <button
                    class="thumbnail-selector flex items-center justify-center rounded-2xl bg-custom-background overflow-hidden h-[124px] border-2 border-custom-background hover:border-custom-blue transition-300 [&.active]:border-custom-blue"
                    v-for="image in images" :key="image.id"
                    :class="{ 'active': image.image === activeImage?.image }" @click="setActiveImage(image)">
                    <img :src="image.image" class="size-full object-contain" alt="thumbnail">
                </button>
            </div>
        </div>

        <!-- Mobile View (Swiper) -->
        <div class="md:hidden w-full relative">
            <div class="gallery-swiper w-full overflow-hidden rounded-2xl aspect-square bg-custom-background">
                <div class="swiper-wrapper">
                    <div class="swiper-slide flex items-center justify-center" v-for="image in images" :key="image.id">
                        <img :src="image.image" class="size-full object-contain" alt="product image">
                    </div>
                </div>
            </div>
            <!-- Custom Pagination Container -->
            <div class="gallery-pagination flex justify-center gap-2 mt-4"></div>
        </div>
    </div>
</template>

<style>
.gallery-pagination .swiper-pagination-bullet {
    background-color: #E0E0E0;
    opacity: 1;
    width: 8px;
    height: 8px;
    transition: all 0.3s;
}
</style>
