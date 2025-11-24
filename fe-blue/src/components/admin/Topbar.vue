<script setup>
import { useRoute } from 'vue-router';

const route = useRoute()
</script>

<template>
    <div id="Top-Bar" class="flex items-center w-full gap-6 mt-[30px] mb-6">
        <div class="flex items-center gap-6 h-[102px] bg-white w-full rounded-3xl p-[18px]">
            <div class="flex flex-col gap-2 w-full">
                <h1 class="font-bold text-2xl capitalize">{{ route.meta.title }}</h1>
                <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                    View Your {{ route.meta.title }}
                </p>
            </div>
            <div class="flex items-center flex-nowrap gap-3">
                <a href="#">
                    <div class="flex size-14 rounded-full bg-custom-icon-background items-center justify-center overflow-hidden">
                        <img src="@/assets/images/icons/search-normal-black.svg" class="size-6" alt="icon">
                    </div>
                </a>
                <a href="#">
                    <div class="flex size-14 rounded-full bg-custom-icon-background items-center justify-center overflow-hidden">
                        <img src="@/assets/images/icons/notification-black.svg" class="size-6" alt="icon">
                    </div>
                </a>
                <div class="relative w-fit">
                    <div class="flex size-14 rounded-full bg-custom-lime-green items-center justify-center overflow-hidden">
                        <img src="@/assets/images/icons/crown-black-fill.svg" class="size-6" alt="icon">
                    </div>
                    <p class="absolute transform -translate-x-1/2 left-1/2 -bottom-2 rounded-[20px] py-1 px-2 bg-custom-black text-white w-fit font-extrabold text-[8px]">PRO</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3 h-[102px] bg-white w-fit rounded-3xl p-[18px]">
            <div class="flex rounded-full overflow-hidden size-14">
                <img src="@/assets/images/photos/photo-1.png" class="size-full object-cover" alt="photo">
            </div>
            <div class="flex flex-col gap-[6px] min-w-[155px] w-fit">
                <p class="font-semibold text-lg leading-tight">Bimore W</p>
                <p class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none">
                    <img src="@/assets/images/icons/user-grey.svg" class="size-[18px]" alt="icon"> 
                    Manager
                </p>
            </div>
            <a href="../index.html" class="flex w-6">
                <img src="@/assets/images/icons/logout.svg" class="flex size-6 shrink-0" alt="icon">
            </a>
        </div>
    </div>
</template>