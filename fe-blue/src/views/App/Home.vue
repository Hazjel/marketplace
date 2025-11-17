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

    new Swiper('.storeSwiper', {
            // Optional parameters
            loop: true,
            slidesPerView: 'auto',
            spaceBetween: 24,
            pagination: {
                el: ".swiper-pagination",
                clickable: true,
                renderBullet: function (index, className) {
                    return '<span class="flex shrink-0 !w-[42px] !h-1 !rounded-full has-[&.swiper-pagination-bullet-active]:!bg-custom-blue ' + className + '"></span>';
                },
            },
            navigation: {
                nextEl: ".swiper-button-next",
                prevEl: ".swiper-button-prev",
            },
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
        <main class="flex flex-col gap-[100px] w-full max-w-[1280px] px-[52px] mt-[72px] mb-[100px] mx-auto">
            <section id="Categories" class="flex flex-col gap-9">
                <div class="flex items-center justify-between">
                    <h2 class="font-extrabold text-[32px]">Explore High Quality<br>Products by Categories</h2>
                    <a href="#" class="flex items-center h-14 rounded-[18px] py-4 px-6 gap-[10px] bg-custom-black">
                        <span class="font-medium text-white">VIEW ALL</span>
                        <img src="@/assets/images/icons/arrow-right-white.svg" class="flex size-6 shrink-0" alt="icon">
                    </a>
                </div>
                <div class="grid grid-cols-6 gap-6">
                    <a href="browse-category.html" class="group card">
                        <div class="flex flex-col rounded-[20px] ring-1 ring-custom-stroke py-8 px-6 items-center gap-6 group-hover:ring-2 group-hover:ring-custom-blue group-hover:bg-custom-blue/5 transition-300">
                            <img src="@/assets/images/icons/mobile-blue-transparent.svg" class="size-9" alt="icon">
                            <div class="flex flex-col items-center gap-1">
                                <p class="font-bold text-xl capitalize">Gadget</p>
                                <p class="font-medium text-custom-grey leading-none">1,294 items</p>
                            </div>
                        </div>
                    </a>
                    <a href="browse-category.html" class="group card">
                        <div class="flex flex-col rounded-[20px] ring-1 ring-custom-stroke py-8 px-6 items-center gap-6 group-hover:ring-2 group-hover:ring-custom-blue group-hover:bg-custom-blue/5 transition-300">
                            <img src="@/assets/images/icons/weight-blue-transparent.svg" class="size-9" alt="icon">
                            <div class="flex flex-col items-center gap-1">
                                <p class="font-bold text-xl capitalize">Fitness</p>
                                <p class="font-medium text-custom-grey leading-none">1,294 items</p>
                            </div>
                        </div>
                    </a>
                    <a href="browse-category.html" class="group card">
                        <div class="flex flex-col rounded-[20px] ring-1 ring-custom-stroke py-8 px-6 items-center gap-6 group-hover:ring-2 group-hover:ring-custom-blue group-hover:bg-custom-blue/5 transition-300">
                            <img src="@/assets/images/icons/devices-blue-transparent.svg" class="size-9" alt="icon">
                            <div class="flex flex-col items-center gap-1">
                                <p class="font-bold text-xl capitalize">Hardware</p>
                                <p class="font-medium text-custom-grey leading-none">1,294 items</p>
                            </div>
                        </div>
                    </a>
                    <a href="browse-category.html" class="group card">
                        <div class="flex flex-col rounded-[20px] ring-1 ring-custom-stroke py-8 px-6 items-center gap-6 group-hover:ring-2 group-hover:ring-custom-blue group-hover:bg-custom-blue/5 transition-300">
                            <img src="@/assets/images/icons/tree-blue-transparent.svg" class="size-9" alt="icon">
                            <div class="flex flex-col items-center gap-1">
                                <p class="font-bold text-xl capitalize">Furniture</p>
                                <p class="font-medium text-custom-grey leading-none">1,294 items</p>
                            </div>
                        </div>
                    </a>
                    <a href="browse-category.html" class="group card">
                        <div class="flex flex-col rounded-[20px] ring-1 ring-custom-stroke py-8 px-6 items-center gap-6 group-hover:ring-2 group-hover:ring-custom-blue group-hover:bg-custom-blue/5 transition-300">
                            <img src="@/assets/images/icons/lamp-blue-transparent.svg" class="size-9" alt="icon">
                            <div class="flex flex-col items-center gap-1">
                                <p class="font-bold text-xl capitalize">electronic</p>
                                <p class="font-medium text-custom-grey leading-none">1,294 items</p>
                            </div>
                        </div>
                    </a>
                    <a href="browse-category.html" class="group card">
                        <div class="flex flex-col rounded-[20px] ring-1 ring-custom-stroke py-8 px-6 items-center gap-6 group-hover:ring-2 group-hover:ring-custom-blue group-hover:bg-custom-blue/5 transition-300">
                            <img src="@/assets/images/icons/coffee-blue-transparent.svg" class="size-9" alt="icon">
                            <div class="flex flex-col items-center gap-1">
                                <p class="font-bold text-xl capitalize">Kitchen</p>
                                <p class="font-medium text-custom-grey leading-none">1,294 items</p>
                            </div>
                        </div>
                    </a>
                </div>
            </section>
            <section id="Top-Picks" class="flex flex-col gap-9">
                <div class="flex items-center justify-between">
                    <h2 class="font-extrabold text-[32px]">Shop Quality Picks<br>from Top Sellers</h2>
                    <a href="#" class="flex items-center h-14 rounded-[18px] py-4 px-6 gap-[10px] bg-custom-black">
                        <span class="font-medium text-white">VIEW ALL</span>
                        <img src="@/assets/images/icons/arrow-right-white.svg" class="flex size-6 shrink-0" alt="icon">
                    </a>
                </div>
                <div class="grid grid-cols-4 gap-6">
                    <div class="card flex flex-col rounded-t-[20px] overflow-hidden">
                        <a href="product-details.html">
                            <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                                <img src="@/assets/images/thumbnails/headphone-white.png" class="size-full object-contain" alt="thumbnail">
                            </div>
                        </a>
                        <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 p-5 gap-6 bg-white">
                            <div class="flex flex-col gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                                        <span class="font-bold text-custom-blue">Gadget</span>
                                    </div>
                                    <p class="font-semibold text-custom-red">120 Sold</p>
                                </div>
                                <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                                    <a href="product-details.html">
                                        <p class="font-bold text-xl w-full truncate">SonicWhirl Wireless Headphone</p>
                                    </a>
                                    <p class="font-bold text-xl text-custom-blue">Rp 3.500.500</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 w-full">
                                <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                    <div class="relative size-6">
                                        <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                        <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                    </div>
                                </button>
                                <a href="product-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                    <div class="flex size-6 shrink-0 relative">
                                        <img src="@/assets/images/icons/shopping-cart-blue.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                        <img src="@/assets/images/icons/shopping-cart-white.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                    </div>
                                    <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Add to Cart</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card flex flex-col rounded-t-[20px] overflow-hidden">
                        <a href="product-details.html">
                            <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                                <img src="@/assets/images/thumbnails/headphone-pink.png" class="size-full object-contain" alt="thumbnail">
                            </div>
                        </a>
                        <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 p-5 gap-6 bg-white">
                            <div class="flex flex-col gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                                        <span class="font-bold text-custom-blue">Gadget</span>
                                    </div>
                                    <p class="font-semibold text-custom-red">120 Sold</p>
                                </div>
                                <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                                    <a href="product-details.html">
                                        <p class="font-bold text-xl w-full truncate">EchoBeats Pink Edition</p>
                                    </a>
                                    <p class="font-bold text-xl text-custom-blue">Rp 18.500.500</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 w-full">
                                <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                    <div class="relative size-6">
                                        <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                        <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                    </div>
                                </button>
                                <a href="product-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                    <div class="flex size-6 shrink-0 relative">
                                        <img src="@/assets/images/icons/shopping-cart-blue.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                        <img src="@/assets/images/icons/shopping-cart-white.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                    </div>
                                    <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Add to Cart</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card flex flex-col rounded-t-[20px] overflow-hidden">
                        <a href="product-details.html">
                            <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                                <img src="@/assets/images/thumbnails/headphone-gold.png" class="size-full object-contain" alt="thumbnail">
                            </div>
                        </a>
                        <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 p-5 gap-6 bg-white">
                            <div class="flex flex-col gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                                        <span class="font-bold text-custom-blue">Gadget</span>
                                    </div>
                                    <p class="font-semibold text-custom-red">120 Sold</p>
                                </div>
                                <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                                    <a href="product-details.html">
                                        <p class="font-bold text-xl w-full truncate">ToneFlex Noise Cancelling</p>
                                    </a>
                                    <p class="font-bold text-xl text-custom-blue">Rp 3.500.500</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 w-full">
                                <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                    <div class="relative size-6">
                                        <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                        <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                    </div>
                                </button>
                                <a href="product-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                    <div class="flex size-6 shrink-0 relative">
                                        <img src="@/assets/images/icons/shopping-cart-blue.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                        <img src="@/assets/images/icons/shopping-cart-white.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                    </div>
                                    <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Add to Cart</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card flex flex-col rounded-t-[20px] overflow-hidden">
                        <a href="product-details.html">
                            <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                                <img src="@/assets/images/thumbnails/headphone-beige.png" class="size-full object-contain" alt="thumbnail">
                            </div>
                        </a>
                        <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 p-5 gap-6 bg-white">
                            <div class="flex flex-col gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                                        <span class="font-bold text-custom-blue">Gadget</span>
                                    </div>
                                    <p class="font-semibold text-custom-red">120 Sold</p>
                                </div>
                                <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                                    <a href="product-details.html">
                                        <p class="font-bold text-xl w-full truncate">BassLoop Studio Headset</p>
                                    </a>
                                    <p class="font-bold text-xl text-custom-blue">Rp 3.500.500</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 w-full">
                                <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                    <div class="relative size-6">
                                        <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                        <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                    </div>
                                </button>
                                <a href="product-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                    <div class="flex size-6 shrink-0 relative">
                                        <img src="@/assets/images/icons/shopping-cart-blue.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                        <img src="@/assets/images/icons/shopping-cart-white.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                    </div>
                                    <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Add to Cart</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card flex flex-col rounded-t-[20px] overflow-hidden">
                        <a href="product-details.html">
                            <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                                <img src="@/assets/images/thumbnails/backpack-2.png" class="size-full object-contain" alt="thumbnail">
                            </div>
                        </a>
                        <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 p-5 gap-6 bg-white">
                            <div class="flex flex-col gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                                        <span class="font-bold text-custom-blue">Fashion</span>
                                    </div>
                                    <p class="font-semibold text-custom-red">120 Sold</p>
                                </div>
                                <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                                    <a href="product-details.html">
                                        <p class="font-bold text-xl w-full truncate">RovoPack Daily Backpack</p>
                                    </a>
                                    <p class="font-bold text-xl text-custom-blue">Rp 3.500.500</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 w-full">
                                <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                    <div class="relative size-6">
                                        <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                        <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                    </div>
                                </button>
                                <a href="product-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                    <div class="flex size-6 shrink-0 relative">
                                        <img src="@/assets/images/icons/shopping-cart-blue.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                        <img src="@/assets/images/icons/shopping-cart-white.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                    </div>
                                    <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Add to Cart</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card flex flex-col rounded-t-[20px] overflow-hidden">
                        <a href="product-details.html">
                            <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                                <img src="@/assets/images/thumbnails/backpack-1.png" class="size-full object-contain" alt="thumbnail">
                            </div>
                        </a>
                        <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 p-5 gap-6 bg-white">
                            <div class="flex flex-col gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                                        <span class="font-bold text-custom-blue">Fashion</span>
                                    </div>
                                    <p class="font-semibold text-custom-red">120 Sold</p>
                                </div>
                                <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                                    <a href="product-details.html">
                                        <p class="font-bold text-xl w-full truncate">TrailCore Adventure Bag</p>
                                    </a>
                                    <p class="font-bold text-xl text-custom-blue">Rp 3.500.500</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 w-full">
                                <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                    <div class="relative size-6">
                                        <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                        <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                    </div>
                                </button>
                                <a href="product-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                    <div class="flex size-6 shrink-0 relative">
                                        <img src="@/assets/images/icons/shopping-cart-blue.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                        <img src="@/assets/images/icons/shopping-cart-white.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                    </div>
                                    <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Add to Cart</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card flex flex-col rounded-t-[20px] overflow-hidden">
                        <a href="product-details.html">
                            <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                                <img src="@/assets/images/thumbnails/backpack-3.png" class="size-full object-contain" alt="thumbnail">
                            </div>
                        </a>
                        <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 p-5 gap-6 bg-white">
                            <div class="flex flex-col gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                                        <span class="font-bold text-custom-blue">Fashion</span>
                                    </div>
                                    <p class="font-semibold text-custom-red">120 Sold</p>
                                </div>
                                <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                                    <a href="product-details.html">
                                        <p class="font-bold text-xl w-full truncate">Nomar Urban Backpack</p>
                                    </a>
                                    <p class="font-bold text-xl text-custom-blue">Rp 3.500.500</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 w-full">
                                <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                    <div class="relative size-6">
                                        <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                        <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                    </div>
                                </button>
                                <a href="product-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                    <div class="flex size-6 shrink-0 relative">
                                        <img src="@/assets/images/icons/shopping-cart-blue.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                        <img src="@/assets/images/icons/shopping-cart-white.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                    </div>
                                    <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Add to Cart</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card flex flex-col rounded-t-[20px] overflow-hidden">
                        <a href="product-details.html">
                            <div class="thumbnail w-full h-[192px] overflow-hidden bg-custom-background items-center justify-center">
                                <img src="@/assets/images/thumbnails/backpack-4.png" class="size-full object-contain" alt="thumbnail">
                            </div>
                        </a>
                        <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 p-5 gap-6 bg-white">
                            <div class="flex flex-col gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-[4px] p-2 bg-custom-blue/10 flex items-center justify-center">
                                        <span class="font-bold text-custom-blue">Fashion</span>
                                    </div>
                                    <p class="font-semibold text-custom-red">120 Sold</p>
                                </div>
                                <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                                    <a href="product-details.html">
                                        <p class="font-bold text-xl w-full truncate">Kavva Canvas Rolltop</p>
                                    </a>
                                    <p class="font-bold text-xl text-custom-blue">Rp 3.500.500</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 w-full">
                                <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                    <div class="relative size-6">
                                        <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                        <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                    </div>
                                </button>
                                <a href="product-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                    <div class="flex size-6 shrink-0 relative">
                                        <img src="@/assets/images/icons/shopping-cart-blue.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                        <img src="@/assets/images/icons/shopping-cart-white.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                    </div>
                                    <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Add to Cart</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <section id="Trusted-Seller" class="flex flex-col gap-9">
                <div class="flex items-center justify-between">
                    <h2 class="font-extrabold text-[32px]">Trusted Sellers,<br>Quality Guaranteed</h2>
                    <a href="#" class="flex items-center h-14 rounded-[18px] py-4 px-6 gap-[10px] bg-custom-black">
                        <span class="font-medium text-white">VIEW ALL</span>
                        <img src="@/assets/images/icons/arrow-right-white.svg" class="flex size-6 shrink-0" alt="icon">
                    </a>
                </div>
                <div class="flex flex-col gap-6 relative">
                    <div class="storeSwiper w-full overflow-hidden">
                        <div class="swiper-wrapper">
                            <div class="swiper-slide w-full !grid !grid-cols-3 !gap-6">
                                <div class="card flex flex-col rounded-[20px] border border-custom-stroke overflow-hidden">
                                    <div class="thumbnail w-full h-[202px] overflow-hidden bg-custom-background items-center justify-center">
                                        <img src="@/assets/images/thumbnails/xiaomi.png" class="size-full object-cover" alt="thumbnail">
                                    </div>
                                    <div class="flex flex-col p-5 gap-6 bg-white">
                                        <div class="flex flex-col gap-2 w-full min-w-0 overflow-hidden">
                                            <div class="flex items-center w-fit shrink-0 gap-1">
                                                <img src="@/assets/images/icons/verify-star.svg" class="flex size-6 shrink-0" alt="icon">
                                                <p class="font-bold text-custom-blue text-nowrap uppercase">Verified Store</p>
                                            </div>
                                            <p class="font-bold text-xl leading-tight w-full truncate">Xiaomi Store Indonesia</p>
                                        </div>
                                        <hr class="border-custom-stroke">
                                        <div class="grid grid-cols-2 gap-6">
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Xiaomi</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon"> 
                                                    Seller Name
                                                </p>
                                            </div>
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Bandung</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/location-grey.svg" class="size-5" alt="icon"> 
                                                    Store Location
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 w-full">
                                            <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                                <div class="relative size-6">
                                                    <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                                    <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                                </div>
                                            </button>
                                            <a href="store-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                                <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Visit Store</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card flex flex-col rounded-[20px] border border-custom-stroke overflow-hidden">
                                    <div class="thumbnail w-full h-[202px] overflow-hidden bg-custom-background items-center justify-center">
                                        <img src="@/assets/images/thumbnails/samsung.png" class="size-full object-cover" alt="thumbnail">
                                    </div>
                                    <div class="flex flex-col p-5 gap-6 bg-white">
                                        <div class="flex flex-col gap-2 w-full min-w-0 overflow-hidden">
                                            <div class="flex items-center w-fit shrink-0 gap-1">
                                                <img src="@/assets/images/icons/verify-star.svg" class="flex size-6 shrink-0" alt="icon">
                                                <p class="font-bold text-custom-blue text-nowrap uppercase">Verified Store</p>
                                            </div>
                                            <p class="font-bold text-xl leading-tight w-full truncate">Samsung Store Indonesia</p>
                                        </div>
                                        <hr class="border-custom-stroke">
                                        <div class="grid grid-cols-2 gap-6">
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Samsung</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon"> 
                                                    Seller Name
                                                </p>
                                            </div>
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Buitenzorg</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/location-grey.svg" class="size-5" alt="icon"> 
                                                    Store Location
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 w-full">
                                            <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                                <div class="relative size-6">
                                                    <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                                    <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                                </div>
                                            </button>
                                            <a href="store-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                                <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Visit Store</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card flex flex-col rounded-[20px] border border-custom-stroke overflow-hidden">
                                    <div class="thumbnail w-full h-[202px] overflow-hidden bg-custom-background items-center justify-center">
                                        <img src="@/assets/images/thumbnails/apple.png" class="size-full object-cover" alt="thumbnail">
                                    </div>
                                    <div class="flex flex-col p-5 gap-6 bg-white">
                                        <div class="flex flex-col gap-2 w-full min-w-0 overflow-hidden">
                                            <div class="flex items-center w-fit shrink-0 gap-1">
                                                <img src="@/assets/images/icons/verify-star.svg" class="flex size-6 shrink-0" alt="icon">
                                                <p class="font-bold text-custom-blue text-nowrap uppercase">Verified Store</p>
                                            </div>
                                            <p class="font-bold text-xl leading-tight w-full truncate">Apple Store Indonesia</p>
                                        </div>
                                        <hr class="border-custom-stroke">
                                        <div class="grid grid-cols-2 gap-6">
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Jakarta</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon"> 
                                                    Seller Name
                                                </p>
                                            </div>
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Buitenzorg</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/location-grey.svg" class="size-5" alt="icon"> 
                                                    Store Location
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 w-full">
                                            <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                                <div class="relative size-6">
                                                    <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                                    <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                                </div>
                                            </button>
                                            <a href="store-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                                <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Visit Store</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="swiper-slide w-full !grid !grid-cols-3 !gap-6">
                                <div class="card flex flex-col rounded-[20px] border border-custom-stroke overflow-hidden">
                                    <div class="thumbnail w-full h-[202px] overflow-hidden bg-custom-background items-center justify-center">
                                        <img src="@/assets/images/thumbnails/xiaomi.png" class="size-full object-cover" alt="thumbnail">
                                    </div>
                                    <div class="flex flex-col p-5 gap-6 bg-white">
                                        <div class="flex flex-col gap-2 w-full min-w-0 overflow-hidden">
                                            <div class="flex items-center w-fit shrink-0 gap-1">
                                                <img src="@/assets/images/icons/verify-star.svg" class="flex size-6 shrink-0" alt="icon">
                                                <p class="font-bold text-custom-blue text-nowrap uppercase">Verified Store</p>
                                            </div>
                                            <p class="font-bold text-xl leading-tight w-full truncate">Xiaomi Store Indonesia</p>
                                        </div>
                                        <hr class="border-custom-stroke">
                                        <div class="grid grid-cols-2 gap-6">
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Xiaomi</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon"> 
                                                    Seller Name
                                                </p>
                                            </div>
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Bandung</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/location-grey.svg" class="size-5" alt="icon"> 
                                                    Store Location
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 w-full">
                                            <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                                <div class="relative size-6">
                                                    <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                                    <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                                </div>
                                            </button>
                                            <a href="store-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                                <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Visit Store</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card flex flex-col rounded-[20px] border border-custom-stroke overflow-hidden">
                                    <div class="thumbnail w-full h-[202px] overflow-hidden bg-custom-background items-center justify-center">
                                        <img src="@/assets/images/thumbnails/samsung.png" class="size-full object-cover" alt="thumbnail">
                                    </div>
                                    <div class="flex flex-col p-5 gap-6 bg-white">
                                        <div class="flex flex-col gap-2 w-full min-w-0 overflow-hidden">
                                            <div class="flex items-center w-fit shrink-0 gap-1">
                                                <img src="@/assets/images/icons/verify-star.svg" class="flex size-6 shrink-0" alt="icon">
                                                <p class="font-bold text-custom-blue text-nowrap uppercase">Verified Store</p>
                                            </div>
                                            <p class="font-bold text-xl leading-tight w-full truncate">Samsung Store Indonesia</p>
                                        </div>
                                        <hr class="border-custom-stroke">
                                        <div class="grid grid-cols-2 gap-6">
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Samsung</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon"> 
                                                    Seller Name
                                                </p>
                                            </div>
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Buitenzorg</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/location-grey.svg" class="size-5" alt="icon"> 
                                                    Store Location
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 w-full">
                                            <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                                <div class="relative size-6">
                                                    <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                                    <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                                </div>
                                            </button>
                                            <a href="store-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                                <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Visit Store</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card flex flex-col rounded-[20px] border border-custom-stroke overflow-hidden">
                                    <div class="thumbnail w-full h-[202px] overflow-hidden bg-custom-background items-center justify-center">
                                        <img src="@/assets/images/thumbnails/apple.png" class="size-full object-cover" alt="thumbnail">
                                    </div>
                                    <div class="flex flex-col p-5 gap-6 bg-white">
                                        <div class="flex flex-col gap-2 w-full min-w-0 overflow-hidden">
                                            <div class="flex items-center w-fit shrink-0 gap-1">
                                                <img src="@/assets/images/icons/verify-star.svg" class="flex size-6 shrink-0" alt="icon">
                                                <p class="font-bold text-custom-blue text-nowrap uppercase">Verified Store</p>
                                            </div>
                                            <p class="font-bold text-xl leading-tight w-full truncate">Apple Store Indonesia</p>
                                        </div>
                                        <hr class="border-custom-stroke">
                                        <div class="grid grid-cols-2 gap-6">
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Jakarta</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon"> 
                                                    Seller Name
                                                </p>
                                            </div>
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Buitenzorg</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/location-grey.svg" class="size-5" alt="icon"> 
                                                    Store Location
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 w-full">
                                            <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                                <div class="relative size-6">
                                                    <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                                    <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                                </div>
                                            </button>
                                            <a href="store-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                                <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Visit Store</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="swiper-slide w-full !grid !grid-cols-3 !gap-6">
                                <div class="card flex flex-col rounded-[20px] border border-custom-stroke overflow-hidden">
                                    <div class="thumbnail w-full h-[202px] overflow-hidden bg-custom-background items-center justify-center">
                                        <img src="@/assets/images/thumbnails/xiaomi.png" class="size-full object-cover" alt="thumbnail">
                                    </div>
                                    <div class="flex flex-col p-5 gap-6 bg-white">
                                        <div class="flex flex-col gap-2 w-full min-w-0 overflow-hidden">
                                            <div class="flex items-center w-fit shrink-0 gap-1">
                                                <img src="@/assets/images/icons/verify-star.svg" class="flex size-6 shrink-0" alt="icon">
                                                <p class="font-bold text-custom-blue text-nowrap uppercase">Verified Store</p>
                                            </div>
                                            <p class="font-bold text-xl leading-tight w-full truncate">Xiaomi Store Indonesia</p>
                                        </div>
                                        <hr class="border-custom-stroke">
                                        <div class="grid grid-cols-2 gap-6">
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Xiaomi</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon"> 
                                                    Seller Name
                                                </p>
                                            </div>
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Bandung</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/location-grey.svg" class="size-5" alt="icon"> 
                                                    Store Location
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 w-full">
                                            <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                                <div class="relative size-6">
                                                    <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                                    <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                                </div>
                                            </button>
                                            <a href="store-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                                <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Visit Store</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card flex flex-col rounded-[20px] border border-custom-stroke overflow-hidden">
                                    <div class="thumbnail w-full h-[202px] overflow-hidden bg-custom-background items-center justify-center">
                                        <img src="@/assets/images/thumbnails/samsung.png" class="size-full object-cover" alt="thumbnail">
                                    </div>
                                    <div class="flex flex-col p-5 gap-6 bg-white">
                                        <div class="flex flex-col gap-2 w-full min-w-0 overflow-hidden">
                                            <div class="flex items-center w-fit shrink-0 gap-1">
                                                <img src="@/assets/images/icons/verify-star.svg" class="flex size-6 shrink-0" alt="icon">
                                                <p class="font-bold text-custom-blue text-nowrap uppercase">Verified Store</p>
                                            </div>
                                            <p class="font-bold text-xl leading-tight w-full truncate">Samsung Store Indonesia</p>
                                        </div>
                                        <hr class="border-custom-stroke">
                                        <div class="grid grid-cols-2 gap-6">
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Samsung</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon"> 
                                                    Seller Name
                                                </p>
                                            </div>
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Buitenzorg</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/location-grey.svg" class="size-5" alt="icon"> 
                                                    Store Location
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 w-full">
                                            <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                                <div class="relative size-6">
                                                    <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                                    <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                                </div>
                                            </button>
                                            <a href="store-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                                <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Visit Store</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card flex flex-col rounded-[20px] border border-custom-stroke overflow-hidden">
                                    <div class="thumbnail w-full h-[202px] overflow-hidden bg-custom-background items-center justify-center">
                                        <img src="@/assets/images/thumbnails/apple.png" class="size-full object-cover" alt="thumbnail">
                                    </div>
                                    <div class="flex flex-col p-5 gap-6 bg-white">
                                        <div class="flex flex-col gap-2 w-full min-w-0 overflow-hidden">
                                            <div class="flex items-center w-fit shrink-0 gap-1">
                                                <img src="@/assets/images/icons/verify-star.svg" class="flex size-6 shrink-0" alt="icon">
                                                <p class="font-bold text-custom-blue text-nowrap uppercase">Verified Store</p>
                                            </div>
                                            <p class="font-bold text-xl leading-tight w-full truncate">Apple Store Indonesia</p>
                                        </div>
                                        <hr class="border-custom-stroke">
                                        <div class="grid grid-cols-2 gap-6">
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Jakarta</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon"> 
                                                    Seller Name
                                                </p>
                                            </div>
                                            <div class="flex flex-col gap-[6px]">
                                                <p class="font-bold text-lg leading-tight">Buitenzorg</p>
                                                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                                    <img src="@/assets/images/icons/location-grey.svg" class="size-5" alt="icon"> 
                                                    Store Location
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 w-full">
                                            <button class="group flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10 hover:bg-custom-red transition-300">
                                                <div class="relative size-6">
                                                    <img src="@/assets/images/icons/heart-red.svg" class="absolute flex size-6 shrink-0 opacity-100 group-hover:opacity-0 transition-300" alt="icon">
                                                    <img src="@/assets/images/icons/heart-white-fill.svg" class="absolute flex size-6 shrink-0 opacity-0 group-hover:opacity-100 transition-300" alt="icon">
                                                </div>
                                            </button>
                                            <a href="store-details.html" class="group flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-[6px] bg-custom-blue/10 hover:bg-custom-blue transition-300">
                                                <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Visit Store</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="relative flex items-center justify-center gap-6 h-14 w-fit mx-auto">
                        <button class="swiper-button-prev !relative after:!content-[''] !mt-0 !top-0 !flex !shrink-0 !items-center !justify-center !size-14 !rounded-full !border !border-custom-stroke">
                            <img src="@/assets/images/icons/arrow-right-black.svg" class="size-6 rotate-180" alt="icon">
                        </button>
                        <div class="swiper-pagination !relative !flex !items-center !gap-2 !top-0 !bottom-0"></div>
                        <button class="swiper-button-next !relative after:!content-[''] !mt-0 !top-0 !flex !shrink-0 !items-center !justify-center !size-14 !rounded-full !border !border-custom-stroke">
                            <img src="@/assets/images/icons/arrow-right-black.svg" class="size-6" alt="icon">
                        </button>
                    </div>
                </div>
            </section>
        </main>
</template>