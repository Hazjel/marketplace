<script setup>
import { RouterView, useRouter } from 'vue-router';
import Swiper from 'swiper';
import { Autoplay, Navigation, EffectFade } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/effect-fade';
import { onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore()
const router = useRouter()

onMounted(() => {
    if (authStore.token) {
        router.replace({ name: 'admin.dashboard' })
    }

    new Swiper('.swiper', {
        modules: [Autoplay, Navigation, EffectFade],
        direction: 'horizontal',
        loop: true,
        autoplay: {
            delay: 5000,
            disableOnInteraction: false,
        },
        effect: 'fade',
        fadeEffect: {
            crossFade: true
        },

        // Navigation arrows
        navigation: {
            nextEl: '.custom-next',
            prevEl: '.custom-prev',
        },
    });
});
</script>

<template>
    <main class="flex min-h-screen w-full font-sans text-custom-black bg-white">
        
        <!-- Left Side: Cinematic Slider (Fixed Width or 50%) -->
        <div class="hidden lg:block w-[50%] h-screen relative overflow-hidden bg-gray-900 sticky top-0">
            <div class="swiper w-full h-full">
                <div class="swiper-wrapper">
                    <div class="swiper-slide relative flex w-full h-full overflow-hidden group">
                        <img src="@/assets/images/backgrounds/login-slide-1.webp"
                            class="w-full h-full object-cover object-top ken-burns" alt="thumbnail">
                        <!-- Glassmorphism Testimonial -->
                        <div
                            class="absolute bottom-12 left-12 right-12 flex flex-col p-8 rounded-3xl border border-white/20 bg-white/10 backdrop-blur-md text-white shadow-2xl">
                            <div class="flex items-center gap-1 mb-4">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                            </div>
                            <p class="font-serif text-2xl lg:text-3xl leading-relaxed italic opacity-90">“Moving my business online truly made 🎯 growth and daily management much simpler.”</p>
                            <div class="flex items-center gap-4 mt-8">
                                <div class="w-12 h-0.5 bg-white/50 rounded-full"></div>
                                <div>
                                    <p class="font-semibold text-xl leading-tight">Jasmine Putri</p>
                                    <p class="text-sm opacity-80 mt-1">Business Owner</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Slide 2 -->
                    <div class="swiper-slide relative flex w-full h-full overflow-hidden group">
                        <img src="@/assets/images/backgrounds/login-slide-2.webp"
                            class="w-full h-full object-cover object-top ken-burns" alt="thumbnail">
                        <div
                            class="absolute bottom-12 left-12 right-12 flex flex-col p-8 rounded-3xl border border-white/20 bg-white/10 backdrop-blur-md text-white shadow-2xl">
                            <div class="flex items-center gap-1 mb-4">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                            </div>
                            <p class="font-serif text-2xl lg:text-3xl leading-relaxed italic opacity-90">“Since taking my business online, everything—from growth to daily operations—has become smoother.”</p>
                            <div class="flex items-center gap-4 mt-8">
                                <div class="w-12 h-0.5 bg-white/50 rounded-full"></div>
                                <div>
                                    <p class="font-semibold text-xl leading-tight">Daisy Hakura</p>
                                    <p class="text-sm opacity-80 mt-1">Happy Seller</p>
                                </div>
                            </div>
                        </div>
                    </div>
                     <!-- Slide 3 -->
                    <div class="swiper-slide relative flex w-full h-full overflow-hidden group">
                         <img src="@/assets/images/backgrounds/login-slide-3.webp"
                            class="w-full h-full object-cover object-top ken-burns" alt="thumbnail">
                        <div
                            class="absolute bottom-12 left-12 right-12 flex flex-col p-8 rounded-3xl border border-white/20 bg-white/10 backdrop-blur-md text-white shadow-2xl">
                           <div class="flex items-center gap-1 mb-4">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                            </div>
                            <p class="font-serif text-2xl lg:text-3xl leading-relaxed italic opacity-90">"Ever since I started shopping at Bluee, finding stuff and checking prices is super quick!"</p>
                            <div class="flex items-center gap-4 mt-8">
                                <div class="w-12 h-0.5 bg-white/50 rounded-full"></div>
                                <div>
                                    <p class="font-semibold text-xl leading-tight">Sarah Jenkins</p>
                                    <p class="text-sm opacity-80 mt-1">Loyal Customer</p>
                                </div>
                            </div>
                        </div>
                    </div>
                     <!-- Slide 4 -->
                    <div class="swiper-slide relative flex w-full h-full overflow-hidden group">
                        <img src="@/assets/images/backgrounds/login-slide-4.webp"
                            class="w-full h-full object-cover object-top ken-burns" alt="thumbnail">
                        <div
                            class="absolute bottom-12 left-12 right-12 flex flex-col p-8 rounded-3xl border border-white/20 bg-white/10 backdrop-blur-md text-white shadow-2xl">
                            <div class="flex items-center gap-1 mb-4">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                                <img src="@/assets/images/icons/Star-rounded.svg" class="flex size-6 shrink-0" alt="star">
                            </div>
                            <p class="font-serif text-2xl lg:text-3xl leading-relaxed italic opacity-90">“Going digital was a game changer 🎯 It simplified growth and cut out the chaos.”</p>
                            <div class="flex items-center gap-4 mt-8">
                                <div class="w-12 h-0.5 bg-white/50 rounded-full"></div>
                                <div>
                                    <p class="font-semibold text-xl leading-tight">Robert Lupin</p>
                                    <p class="text-sm opacity-80 mt-1">Business Owner</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Nav Arrows -->
                 <div class="flex items-center absolute w-fit gap-4 bottom-14 right-20 z-10">
                    <div class="custom-prev flex !size-12 shrink-0 relative cursor-pointer hover:scale-110 transition-transform bg-white/10 backdrop-blur-md border border-white/30 rounded-full items-center justify-center hover:bg-white/20">
                        <img src="@/assets/images/icons/arrow-circle-left-white.svg" class="flex size-6" alt="icon">
                    </div>
                    <div class="custom-next flex !size-12 shrink-0 relative cursor-pointer hover:scale-110 transition-transform bg-white/10 backdrop-blur-md border border-white/30 rounded-full items-center justify-center hover:bg-white/20">
                        <img src="@/assets/images/icons/arrow-circle-right-white.svg" class="flex size-6" alt="icon">
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Form Container -->
        <div class="flex w-full lg:w-[50%] min-h-screen items-center justify-center p-8 lg:p-24 relative overflow-y-auto">
             <div class="w-full max-w-[480px]">
                <RouterView />
             </div>
        </div>
    </main>
</template>