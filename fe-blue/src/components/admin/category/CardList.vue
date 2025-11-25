<script setup>
defineProps({
    item: {
        type: Object,
        required: true
    }
})

const emit = defineEmits(['delete'])
</script>

<template>
    <div class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white">
        <div class="flex items-center gap-5 justify-between pr-[30px]">
            <div class="flex w-[320px] shrink-0 items-center gap-[14px] overflow-hidden">
                <div class="flex size-[92px] shrink-0 rounded-2xl bg-custom-background p-[22px] overflow-hidden items-center justify-center">
                    <img :src="item.image" class="size-full object-contain" alt="icon">
                </div>
                <div class="flex flex-col flex-1 gap-[6px] overflow-hidden">
                    <p class="font-bold text-lg truncate">{{ item.name }}</p>
                    <p class="font-semibold text-custom-grey truncate">{{ item.tagline }}</p>
                </div>
            </div>
            <div class="flex items-center gap-[10px] w-[260px]">
                <div class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                    <img src="@/assets/images/icons/shopping-cart-black.svg" class="flex size-6 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-1">
                    <p class="font-bold text-lg leading-none">{{ item.product_count }}</p>
                    <p class="font-semibold text-custom-grey">Total Products</p>
                </div>
            </div>
            <div class="flex items-center gap-[10px] w-[260px]">
                <div class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                    <img src="@/assets/images/icons/bag-black.svg" class="flex size-6 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-1">
                    <p class="font-bold text-lg leading-none">{{ item.children_count }}</p>
                    <p class="font-semibold text-custom-grey">Sub Category</p>
                </div>
            </div>
        </div>
        <hr class="border-custom-stroke">
        <div class="flex items-center justify-end gap-[14px]">
            <button @click="emit('delete', item.id)"
                class="flex items-center justify-center h-14 w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10">
                <img src="@/assets/images/icons/trash-red.svg" class="flex size-6 shrink-0"
                    alt="icon">
                <span class="font-semibold text-custom-red">Delete</span>
            </button>
            <RouterLink :to="{ name: 'admin.category.edit', params: { id: item.id } }" 
            class="flex items-center justify-center h-14 w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-black">
                <img src="@/assets/images/icons/edit-white.svg" class="flex size-6 shrink-0" alt="icon">
                <span class="font-semibold text-white">Edit</span>
            </RouterLink>
            <a href="category-details.html" class="flex items-center justify-center h-14 w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-blue">
                <img src="@/assets/images/icons/eye-white.svg" class="flex size-6 shrink-0" alt="icon">
                <span class="font-semibold text-white">Details</span>
            </a>
        </div>
    </div>
</template>