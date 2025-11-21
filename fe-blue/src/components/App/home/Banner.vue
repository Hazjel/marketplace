<script setup>
import Swiper from 'swiper/bundle';
import 'swiper/swiper-bundle.css'
import { onMounted } from 'vue';

onMounted(() => {
    new Swiper('.swiper', {
        // Optional parameters
        loop: true,
        spaceBetween: 32,
        autoplay: {
            delay: 3000,
        },
        slidesOffsetBefore: 32,
        slidesOffsetAfter: 32,
        slidesPerView: 'auto',
    });
})
</script>

<template>
    <section id="Promo" class="w-full max-w-[1920px] mx-auto overflow-hidden bg-custom-background py-8">
        <div class="swiper">
            <div class="swiper-wrapper">
                <div class="swiper-slide !w-fit">
                    <a href="" class="flex ratio-[773/360] w-[773px] bg-custom-icon-background rounded-3xl overflow-hidden">
                        <img src="@/assets/images/thumbnails/Slide 1.png" class="size-full object-cover" alt="slide">
                    </a>
                </div>
                <div class="swiper-slide !w-fit">
                    <a href="" class="flex ratio-[773/360] w-[773px] bg-custom-icon-background rounded-3xl overflow-hidden">
                        <img src="@/assets/images/thumbnails/Slide 2.png" class="size-full object-cover" alt="slide">
                    </a>
                </div>
                <div class="swiper-slide !w-fit">
                    <a href="" class="flex ratio-[773/360] w-[773px] bg-custom-icon-background rounded-3xl overflow-hidden">
                        <img src="@/assets/images/thumbnails/Slide 1.png" class="size-full object-cover" alt="slide">
                    </a>
                </div>
                <div class="swiper-slide !w-fit">
                    <a href="" class="flex ratio-[773/360] w-[773px] bg-custom-icon-background rounded-3xl overflow-hidden">
                        <img src="@/assets/images/thumbnails/Slide 2.png" class="size-full object-cover" alt="slide">
                    </a>
                </div>
            </div>
        </div>
    </section>
</template>