<script setup>
import { RouterView, useRouter } from 'vue-router';
import Swiper from 'swiper/bundle';
import 'swiper/swiper-bundle.css'
import { onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore()
const router = useRouter()

onMounted(() => {
    if (authStore.token) {
        router.replace({ name: 'admin.dashboard' })
    }

    new Swiper('.swiper', {
        direction: 'horizontal',
        loop: true,
        autoplay: {
            delay: 5000,
        },
        effect: 'fade',
        fadeEffect: {
            crossFade: true
        },

        // Navigation arrows
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
    });
})
</script>

<template>
    <main class="flex flex-1 min-h-screen items-center min-w-0 bg-custom-background">
        <div class="h-screen max-w-[660px] w-[calc(100%-620px)] shrink-0 overflow-hidden relative">
            <div class="swiper !fixed top-0 flex max-w-[660px] w-[calc(100%-620px)] h-screen overflow-hidden">
                <div class="swiper-wrapper">
                    <div class="swiper-slide relative flex w-full h-full overflow-hidden">
                        <img src="@/assets/images/backgrounds/login-slide-1.webp"
                            class="w-full h-full object-cover object-top" alt="thumbnail">
                        <div
                            class="absolute bottom-0 flex flex-col w-full h-fit px-[30px] pt-10 pb-[35px] text-white gradient-black-transparent">
                            <div class="flex items-center gap-0.5">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                            </div>
                            <p class="font-medium text-[26px] mt-3">“Moving my business online truly made 🎯 growth and
                                daily management much simpler, more efficient, and hassle-free.”</p>
                            <div class="w-fit mt-[30px]">
                                <p class="font-semibold text-xl leading-[30px]">Jasmine Putri</p>
                                <p class="mt-1">Business Owner</p>
                            </div>
                        </div>
                    </div>
                    <div class="swiper-slide relative flex w-full h-full overflow-hidden">
                        <img src="@/assets/images/backgrounds/login-slide-2.webp"
                            class="w-full h-full object-cover object-top" alt="thumbnail">
                        <div
                            class="absolute bottom-0 flex flex-col w-full h-fit px-[30px] pt-10 pb-[35px] text-white gradient-black-transparent">
                            <div class="flex items-center gap-0.5">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                            </div>
                            <p class="font-medium text-[26px] mt-3">“Since taking my business online, everything—from
                                growth to daily operations—has become smoother, faster, and way easier to manage.”</p>
                            <div class="w-fit mt-[30px]">
                                <p class="font-semibold text-xl leading-[30px]">Daisy Hakura</p>
                                <p class="mt-1">Happy Seller</p>
                            </div>
                        </div>
                    </div>
                    <div class="swiper-slide relative flex w-full h-full overflow-hidden">
                        <img src="@/assets/images/backgrounds/login-slide-3.webp"
                            class="w-full h-full object-cover object-top" alt="thumbnail">
                        <div
                            class="absolute bottom-0 flex flex-col w-full h-fit px-[30px] pt-10 pb-[35px] text-white gradient-black-transparent">
                            <div class="flex items-center gap-0.5">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                            </div>
                            <p class="font-medium text-[26px] mt-3">"Ever since I started shopping online at Bluee,
                                everything’s been way easier—finding stuff, checking prices, even buying it’s super
                                quick!"</p>
                            <div class="w-fit mt-[30px]">
                                <p class="font-semibold text-xl leading-[30px]">Daisy Hakura</p>
                                <p class="mt-1">Happy Seller</p>
                            </div>
                        </div>
                    </div>
                    <div class="swiper-slide relative flex w-full h-full overflow-hidden">
                        <img src="@/assets/images/backgrounds/login-slide-4.webp"
                            class="w-full h-full object-cover object-top" alt="thumbnail">
                        <div
                            class="absolute bottom-0 flex flex-col w-full h-fit px-[30px] pt-10 pb-[35px] text-white gradient-black-transparent">
                            <div class="flex items-center gap-0.5">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-8 shrink-0 p-1"
                                    alt="star">
                            </div>
                            <p class="font-medium text-[26px] mt-3">“Going digital with my business was a game changer
                                🎯 It simplified growth, streamlined daily tasks, and cut out the chaos.”</p>
                            <div class="w-fit mt-[30px]">
                                <p class="font-semibold text-xl leading-[30px]">Robert Lupin</p>
                                <p class="mt-1">Business Owner</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center absolute w-[78px] h-9 gap-[6px] bottom-10 left-auto right-[30px]">
                    <div
                        class="swiper-button-prev after:!content-[''] flex !size-9 shrink-0 !relative !left-auto !top-auto !right-auto">
                        <img src="@/assets/images/icons/arrow-circle-left-white.svg" class="flex size-9" alt="icon">
                    </div>
                    <div
                        class="swiper-button-next after:!content-[''] flex !size-9 shrink-0 !relative !left-auto !top-auto !right-auto">
                        <img src="@/assets/images/icons/arrow-circle-right-white.svg" class="flex size-9" alt="icon">
                    </div>
                </div>
            </div>
        </div>
        <div class="flex w-full min-h-screen p-[30px] items-center justify-center">
            <RouterView />
        </div>
    </main>
</template>