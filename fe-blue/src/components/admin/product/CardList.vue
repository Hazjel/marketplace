<script setup>
import { can } from '@/helpers/permissionHelper';
import { RouterLink } from 'vue-router';
import { formatDate } from '@/helpers/format';

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
            <div class="flex w-full items-center gap-[14px] overflow-hidden">
                <div
                    class="flex size-[92px] shrink-0 rounded-2xl bg-custom-background overflow-hidden items-center justify-center">
                    <img :src="item?.product_images?.find(image => image.is_thumbnail)?.image"
                        class="size-full object-contain" alt="icon">
                </div>
                <div class="flex flex-col flex-1 gap-[6px] overflow-hidden">
                    <p class="font-bold text-lg truncate">{{ item.name }}</p>
                    <p class="font-semibold leading-none text-custom-grey flex items-center gap-[6px]">
                        <span class="font-bold text-custom-blue">{{ item.product_category?.name }}</span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-[10px] w-full">
                <div
                    class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                    <img src="@/assets/images/icons/status-up-black.svg" class="flex size-6 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-1">
                    <p class="font-bold text-lg leading-none">{{ item.total_sold ? item.total_sold.toLocaleString() : 0
                        }}</p>
                    <p class="font-semibold text-custom-grey">Total Sold</p>
                </div>
            </div>
            <div class="flex items-center gap-[10px] w-full">
                <div
                    class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                    <img src="@/assets/images/icons/box-black.svg" class="flex size-6 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-1">
                    <p class="font-bold text-lg leading-none">{{ item.stock }}</p>
                    <p class="font-semibold text-custom-grey">Total Stock</p>
                </div>
            </div>
        </div>
        <hr class="border-custom-stroke">
        <div class="flex items-center justify-between">
            <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
                <img src="@/assets/images/icons/calendar-2-grey.svg" class="size-6 flex shrink-0" alt="icon">
                Created on {{ formatDate(item.created_at) }}
            </p>
            <div class="flex items-center justify-end gap-[14px]">
                <button
                    class="flex items-center justify-center h-14 w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10"
                    v-if="can('product-delete')" @click="emit('delete', item.id)">
                    <img src="@/assets/images/icons/trash-red.svg" class="flex size-6 shrink-0" alt="icon">
                    <span class="font-semibold text-custom-red">Delete</span>
                </button>
                <RouterLink :to="{ name: 'admin.product.edit', params: { id: item.id } }"
                    class="flex items-center justify-center h-14 w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-black"
                    v-if="can('product-edit')">
                    <img src="@/assets/images/icons/edit-white.svg" class="flex size-6 shrink-0" alt="icon">
                    <span class="font-semibold text-white">Edit</span>
                </RouterLink>
                <RouterLink :to="{ name: 'admin.product.detail', params: { id: item.id } }"
                    class="flex items-center justify-center h-14 w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-blue">
                    <img src="@/assets/images/icons/eye-white.svg" class="flex size-6 shrink-0" alt="icon">
                    <span class="font-semibold text-white">Details</span>
                </RouterLink>
            </div>
        </div>
    </div>
</template>