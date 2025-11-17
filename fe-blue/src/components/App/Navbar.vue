<template>
    <section id="Navbar-Wrapper" class="flex h-[168px] w-full mx-auto relative">
            <div class="fixed top-0 w-full bg-white min-h-[168px] border-b border-custom-stroke py-8 z-30">
                <div class="w-full max-w-[1920px] flex flex-col gap-6 px-7 mx-auto">
                    <div class="flex items-center gap-6 w-full">
                        <a href="main.html" class="flex shrink-0">
                            <img src="@/assets/images/logos/logo.svg" class="h-8" alt="logo">
                        </a>
                        <button type="button" class="flex items-center gap-2 shrink-0">
                            <img src="@/assets/images/icons/menu-grey.svg" class="size-6 flex shrink-0" alt="icon">
                            <p class="flex items-center gap-1 font-semibold text-custom-grey">
                                Categories
                                <img src="@/assets/images/icons/arrow-down-grey.svg" class="size-4" alt="icon">
                            </p>
                        </button>
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
                            <a href="shopping-cart.html">
                                <div class="flex size-14 rounded-full bg-custom-icon-background items-center justify-center overflow-hidden">
                                    <img src="@/assets/images/icons/shopping-cart-black.svg" class="size-6" alt="icon">
                                </div>
                            </a>
                            <a href="signin.html" class="flex shrink-0 h-14 rounded-[18px] py-4 px-8 bg-custom-blue">
                                <p class="font-medium text-white">Sign In/Register</p>
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center gap-8 flex-wrap">
                        <a href="main.html" class="group flex items-center gap-2 active">
                            <img src="@/assets/images/icons/home-blue-fill.svg" class="hidden size-6 shrink-0 group-[&.active]:flex" alt="icon">
                            <img src="@/assets/images/icons/home-grey.svg" class="flex size-6 shrink-0 group-[&.active]:hidden" alt="icon">
                            <span class="font-semibold text-custom-grey group-[&.active]:text-custom-blue">Homepage</span>
                        </a>
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