<script setup>
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart'; // Import cart store
import { storeToRefs } from 'pinia';
import { onMounted, ref } from 'vue';

const showDropdownProfile = ref(false);

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)
const { checkAuth, logout } = authStore

// Import cart store
const cartStore = useCartStore()
const { totalItems } = storeToRefs(cartStore) // Get total items dari cart

onMounted(checkAuth)
</script>

<template>
    <section id="Navbar-Wrapper" class="flex h-[168px] w-full mx-auto relative">
        <div class="fixed top-0 w-full bg-white min-h-[168px] border-b border-custom-stroke py-8 z-30">
            <div class="w-full max-w-[1920px] flex flex-col gap-6 px-7 mx-auto">
                <div class="flex items-center gap-6 w-full">
                    <RouterLink :to="{ name: 'app.home' }" class="flex shrink-0">
                        <img src="@/assets/images/logos/logo.svg" class="h-8" alt="logo">
                    </RouterLink>
                    <form action="#" class="w-full">
                        <label class="flex items-center w-full h-14 rounded-[18px] p-4 px-6 gap-2 bg-white border-[1.5px] border-custom-stroke focus-within:border-custom-black transition-300">
                            <img src="@/assets/images/icons/search-normal-grey.svg" class="flex size-6 shrink-0" alt="icon">
                            <input type="text" class="appearance-none w-full placeholder:text-custom-grey font-semibold focus:outline-none" placeholder="Search any products">
                        </label>
                    </form>
                    <div class="flex items-center gap-3 shrink-0">
                        <a href="#">
                            <div class="flex size-14 rounded-full bg-custom-icon-background items-center justify-center overflow-hidden">
                                <img src="@/assets/images/icons/notification-black.svg" class="size-6" alt="icon">
                            </div>
                        </a>
                        
                        <!-- Cart Icon with Badge -->
                        <div class="relative">
                            <RouterLink :to="{ name: 'app.cart' }">
                                <div class="flex size-14 rounded-full bg-custom-icon-background items-center justify-center overflow-hidden">
                                    <img src="@/assets/images/icons/shopping-cart-black.svg" class="size-6" alt="icon">
                                </div>
                            </RouterLink>
                            <!-- Badge Indicator -->
                            <div v-if="totalItems > 0" class="absolute top-0 right-0 flex items-center justify-center min-w-[20px] min-h-[20px] rounded-full bg-custom-red border-2 border-white px-1">
                                <span class="text-white text-[10px] font-bold leading-none">{{ totalItems > 99 ? '99+' : totalItems }}</span>
                            </div>
                        </div>
                        
                        <RouterLink :to="{ name: 'auth.login' }" class="flex shrink-0 h-14 rounded-[18px] py-4 px-8 bg-custom-blue" v-if="!user">
                            <p class="font-medium text-white">Sign In/Register</p>
                        </RouterLink>
                        <div class="relative" v-if="user">
                            <button @click="showDropdownProfile = !showDropdownProfile"
                                class="flex size-14 rounded-full bg-custom-icon-background items-center justify-center overflow-hidden">
                                <img :src="user.profile_picture" class="size-full object-cover" alt="icon">
                            </button>
                            <div id="Profile-Dropdown"
                                class="absolute transform top-[calc(100%+12px)] right-0 z-30" v-if="showDropdownProfile">
                                <nav
                                    class="flex flex-col w-[201px] rounded-[20px] rounded-tr-none py-6 px-4 gap-[18px] bg-white shadow-[0px_6px_30px_0px_#00000017]">
                                    <a href="buyer/overview.html" class="flex w-full items-center justify-between">
                                        <span class="font-medium text-custom-grey">My Profile</span>
                                        <img src="@/assets/images/icons/profile-circle-grey.svg"
                                            class="flex size-6 shrink-0" alt="icon">
                                    </a>
                                    <RouterLink :to="{ name: 'admin.my-transaction' }"
                                        class="flex w-full items-center justify-between">
                                        <span class="font-medium text-custom-grey">My Transactions</span>
                                        <img src="@/assets/images/icons/stickynote-grey.svg" class="flex size-6 shrink-0"
                                            alt="icon">
                                    </RouterLink>
                                    <a href="#" class="flex w-full items-center justify-between">
                                        <span class="font-medium text-custom-grey">Settings</span>
                                        <img src="@/assets/images/icons/setting-2-grey.svg" class="flex size-6 shrink-0"
                                            alt="icon">
                                    </a>
                                    <hr class="border-custom-stroke">
                                    <button @click="logout" class="flex w-full items-center justify-between">
                                        <span class="font-medium text-custom-red">Log Out</span>
                                        <img src="@/assets/images/icons/logout.svg" class="flex size-6 shrink-0"
                                            alt="icon">
                                    </button>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-8 flex-wrap">
                    <RouterLink :to="{ name: 'app.home' }" class="group flex items-center gap-2 active">
                        <img src="@/assets/images/icons/home-blue-fill.svg" class="hidden size-6 shrink-0 group-[&.active]:flex" alt="icon">
                        <img src="@/assets/images/icons/home-grey.svg" class="flex size-6 shrink-0 group-[&.active]:hidden" alt="icon">
                        <span class="font-semibold text-custom-grey group-[&.active]:text-custom-blue">Homepage</span>
                    </RouterLink>
                    <a href="#" class="group flex items-center gap-2">
                        <img src="@/assets/images/icons/flash-grey.svg" class="flex size-6 shrink-0" alt="icon">
                        <span class="font-semibold text-custom-grey">Flash Deals</span>
                    </a>
                    <a href="#" class="group flex items-center gap-2">
                        <img src="@/assets/images/icons/box-search-grey.svg" class="flex size-6 shrink-0" alt="icon">
                        <span class="font-semibold text-custom-grey">Track Order</span>
                    </a>
                    <a href="#" class="group flex items-center gap-2">
                        <img src="@/assets/images/icons/note-grey.svg" class="flex size-6 shrink-0" alt="icon">
                        <span class="font-semibold text-custom-grey">Return & Refund</span>
                    </a>
                    <a href="#" class="group flex items-center gap-2">
                        <img src="@/assets/images/icons/car-delivery-grey.svg" class="flex size-6 shrink-0" alt="icon">
                        <span class="font-semibold text-custom-grey">Shipping Info</span>
                    </a>
                    <a href="#" class="group flex items-center gap-2">
                        <img src="@/assets/images/icons/buildings-grey.svg" class="flex size-6 shrink-0" alt="icon">
                        <span class="font-semibold text-custom-grey">About Us</span>
                    </a>
                    <a href="#" class="group flex items-center gap-2">
                        <img src="@/assets/images/icons/callcenter-grey.svg" class="flex size-6 shrink-0" alt="icon">
                        <span class="font-semibold text-custom-grey">Customer Services</span>
                    </a>
                </div>
            </div>
        </div>
    </section>
</template>