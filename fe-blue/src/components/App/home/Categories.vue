<script setup>
import { useProductCategoryStore } from '@/stores/productCategory';
import { storeToRefs } from 'pinia';
import { onMounted } from 'vue';

const productCategoryStore = useProductCategoryStore();
const { productCategories, loading } = storeToRefs(productCategoryStore);
const { fetchProductCategories } = productCategoryStore;

onMounted(async () => {
    fetchProductCategories({
        limit: 6,
    });
});
</script>

<template>
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
</template>