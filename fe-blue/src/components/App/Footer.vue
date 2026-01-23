<script setup>
import { useAuthStore } from '@/stores/auth';
import { storeToRefs } from 'pinia';

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)
</script>

<template>
    <footer class="border-t border-custom-stroke py-8 px-5 md:p-[52px] pb-[120px] md:pb-[52px]">
        <div class="flex flex-col w-full max-w-[1280px] md:px-[52px] mx-auto gap-8 md:gap-9">
            <div class="flex flex-col lg:flex-row gap-8 lg:gap-[72px]">
                <div class="flex flex-col gap-4 md:gap-6 w-full lg:w-[349px]">
                    <RouterLink :to="{ name: 'app.home' }" class="flex shrink-0">
                        <img src="@/assets/images/logos/blukios_logo.png" class="h-12" alt="logo">
                    </RouterLink>
                    <p class="font-medium text-custom-grey">
                        Blukios is your one-stop marketplace for quality products.
                        We connect buyers and sellers to create a seamless shopping experience.
                    </p>
                </div>
                <div class="flex flex-col md:flex-row gap-8 lg:gap-[52px] w-full lg:w-fit">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:flex md:gap-[52px] gap-8 w-full">
                        <div class="flex flex-col gap-4">
                            <p class="font-semibold">Main Menu</p>
                            <nav class="flex flex-col gap-4 *:font-medium *:text-custom-grey">
                                <RouterLink :to="{ name: 'app.home' }">Home</RouterLink>
                                <RouterLink :to="{ name: 'app.all-categories' }">Categories</RouterLink>
                                <RouterLink :to="{ name: 'app.all-products' }">All Products</RouterLink>
                                <RouterLink v-if="!user?.store" :to="{ name: 'auth.open-store' }">Become Seller
                                </RouterLink>
                                <RouterLink :to="{ name: 'app.my-transactions' }">Track My Order</RouterLink>
                            </nav>
                        </div>
                        <div class="flex flex-col gap-4">
                            <p class="font-semibold">Company</p>
                            <nav class="flex flex-col gap-4 *:font-medium *:text-custom-grey">
                                <RouterLink :to="{ name: 'app.about' }">About Us</RouterLink>
                                <RouterLink :to="{ name: 'app.career' }">Career</RouterLink>
                                <RouterLink :to="{ name: 'app.privacy' }">Privacy Policy</RouterLink>
                                <RouterLink :to="{ name: 'app.terms' }">Terms & Conditions</RouterLink>
                            </nav>
                        </div>
                        <div class="flex flex-col gap-4 col-span-2 sm:col-span-1">
                            <p class="font-semibold">Support</p>
                            <nav class="flex flex-col gap-4 *:font-medium *:text-custom-grey">
                                <a href="#" @click.prevent>Customer Services</a>
                                <a href="#" @click.prevent>Return & Refund</a>
                                <a href="#" @click.prevent>Shipping Info</a>
                                <a href="#" @click.prevent>Contact Us</a>
                            </nav>
                        </div>
                    </div>
                </div>
                <div class="flex flex-row md:flex-col gap-4 items-center md:items-start">
                    <p class="font-semibold hidden md:block">Download App</p>
                    <a href="#" @click.prevent>
                        <img src="@/assets/images/logos/gplay.png" class="h-10 md:h-12" alt="logo">
                    </a>
                    <a href="#" @click.prevent>
                        <img src="@/assets/images/logos/appstore.png" class="h-10 md:h-12" alt="logo">
                    </a>
                </div>
            </div>
            <hr class="border-custom-stroke">
            <div class="flex flex-col-reverse md:flex-row items-center justify-between gap-6 md:gap-0">
                <p class="font-medium text-custom-grey text-center md:text-left text-sm md:text-base">
                    ©2026 Blukios. All Rights Reserved
                </p>
                <div class="flex items-center gap-5">
                    <a href="#" @click.prevent>
                        <img src="@/assets/images/logos/instagram.svg" class="flex size-6 shrink-0" alt="logo">
                    </a>
                    <a href="#" @click.prevent>
                        <img src="@/assets/images/logos/gmail.svg" class="flex size-6 shrink-0" alt="logo">
                    </a>
                    <a href="#" @click.prevent>
                        <img src="@/assets/images/logos/facebook.svg" class="flex size-6 shrink-0" alt="logo">
                    </a>
                    <a href="#" @click.prevent>
                        <img src="@/assets/images/logos/tiktok.svg" class="flex size-6 shrink-0" alt="logo">
                    </a>
                    <a href="#" @click.prevent>
                        <img src="@/assets/images/logos/youtube.svg" class="flex size-6 shrink-0" alt="logo">
                    </a>
                    <a href="#" @click.prevent>
                        <img src="@/assets/images/logos/x.svg" class="flex size-6 shrink-0" alt="logo">
                    </a>
                </div>
            </div>
        </div>
    </footer>
</template>