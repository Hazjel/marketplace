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
        <div class="flex flex-col md:flex-row items-start md:items-center gap-5 justify-between pr-0 md:pr-[30px]">
            <div class="flex w-full md:w-[320px] shrink-0 items-center gap-[14px] overflow-hidden">
                <div class="flex size-[92px] shrink-0 rounded-2xl bg-custom-background p-[22px] overflow-hidden items-center justify-center">
                    <img :src="item.image" class="size-full object-contain" alt="icon">
                </div>
                <div class="flex flex-col flex-1 gap-[6px] overflow-hidden">
                    <p class="font-bold text-lg truncate">{{ item.name }}</p>
                    <p class="font-semibold text-custom-grey truncate">{{ item.tagline }}</p>
                </div>
            </div>
            <!-- Stats Group -->
            <div class="grid grid-cols-2 gap-5 w-full md:flex md:w-auto md:gap-10">
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-[10px] w-full md:w-[260px]">
                    <div class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                        <img src="@/assets/images/icons/shopping-cart-black.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <div class="flex flex-col gap-1 w-full overflow-hidden">
                        <p class="font-bold text-lg leading-none truncate">{{ item.product_count }}</p>
                        <p class="font-semibold text-custom-grey text-sm sm:text-base truncate">Total Products</p>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-[10px] w-full md:w-[260px]">
                    <div class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                        <img src="@/assets/images/icons/bag-black.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <div class="flex flex-col gap-1 w-full overflow-hidden">
                        <p class="font-bold text-lg leading-none truncate">{{ item.children_count }}</p>
                        <p class="font-semibold text-custom-grey text-sm sm:text-base truncate">Sub Category</p>
                    </div>
                </div>
            </div>
        </div>
        <hr class="border-custom-stroke">
        <div class="flex items-center justify-end">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-[14px] w-full md:w-auto">
                <button @click="emit('delete', item.id)"
                    class="flex items-center justify-center h-14 w-full md:w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10">
                    <img src="@/assets/images/icons/trash-red.svg" class="flex size-6 shrink-0"
                        alt="icon">
                    <span class="font-semibold text-custom-red">Delete</span>
                </button>
                <RouterLink :to="{ name: 'admin.category.edit', params: { id: item.id } }" 
                class="flex items-center justify-center h-14 w-full md:w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-black">
                    <img src="@/assets/images/icons/edit-white.svg" class="flex size-6 shrink-0" alt="icon">
                    <span class="font-semibold text-white">Edit</span>
                </RouterLink>
                <RouterLink :to="{ name: 'admin.category.detail', params: { id: item.id } }" class="flex items-center justify-center h-14 w-full md:w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-blue">
                    <img src="@/assets/images/icons/eye-white.svg" class="flex size-6 shrink-0" alt="icon">
                    <span class="font-semibold text-white">Details</span>
                </RouterLink>
            </div>
        </div>
    </div>
</template>