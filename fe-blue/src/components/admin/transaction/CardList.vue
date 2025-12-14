<script setup>
import { formatRupiah, formatToClientTimeZone } from '@/helpers/format';
import { can } from '@/helpers/permissionHelper';
import { RouterLink } from 'vue-router';

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
        <div class="flex items-center justify-between">
            <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
                <img src="@/assets/images/icons/calendar-2-grey.svg" class="size-6 flex shrink-0" alt="icon">
                {{ formatToClientTimeZone(item.created_at) }}
            </p>
            <p class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-yellow text-[#544607]"
                v-if="item.delivery_status === 'pending'">
                Pending
            </p>
            <p class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-blue/10 text-custom-blue"
                v-if="item.delivery_status === 'processing'">
                Processing
            </p>
            <p class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-orange/10 text-custom-orange"
                v-if="item.delivery_status === 'delivering'">
                Delivering
            </p>
            <p class="badge rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-green/10 text-custom-green"
                v-if="item.delivery_status === 'completed'">
                Completed
            </p>
        </div>
        <hr class="border-custom-stroke">
        <div class="flex items-center gap-5 justify-between pr-[30px]">
            <div class="flex items-center gap-[14px] w-[320px]">
                <div class="flex size-[84px] shrink-0 rounded-full bg-custom-background overflow-hidden">
                    <img :src="item.buyer?.user?.profile_picture" class="size-full object-cover" alt="photo">
                </div>
                <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                    <p class="font-bold text-lg leading-tight w-full truncate">
                        {{ item?.buyer?.user?.name }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-[10px] w-[260px]">
                <div
                    class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                    <img src="@/assets/images/icons/shopping-cart-black.svg" class="flex size-6 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-1">
                    <p class="font-bold text-lg leading-none">{{ item?.transaction_details?.length }}</p>
                    <p class="font-semibold text-custom-grey">Total Products</p>
                </div>
            </div>
            <div class="flex items-center gap-[10px] w-[260px]">
                <div
                    class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                    <img src="@/assets/images/icons/box-black.svg" class="flex size-6 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-1">
                    <p class="font-bold text-lg leading-none">{{ item?.transaction_details?.reduce((total, detail) => total + detail.qty, 0) }}</p>
                    <p class="font-semibold text-custom-grey">Total Quantity</p>
                </div>
            </div>
        </div>
        <hr class="border-custom-stroke">
        <div class="flex items-center justify-between">
            <div class="flex flex-col gap-[6px]">
                <p class="font-bold text-xl text-custom-blue">{{ formatRupiah(item.grand_total) }}</p>
                <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
                    <img src="@/assets/images/icons/money-grey.svg" class="size-6 flex shrink-0" alt="icon">
                    Grand Total
                </p>
            </div>
            <div class="flex items-center justify-end gap-[14px]">
                <button
                    class="flex items-center justify-center h-14 w-fit shrink-0 rounded-2xl p-4 gap-2 bg-custom-black">
                    <span class="font-semibold text-white">Export</span>
                    <img src="@/assets/images/icons/receive-square-white.svg" class="flex size-6 shrink-0" alt="icon">
                </button>
                <RouterLink :to="{name: 'admin.transaction.detail', params: {id: item.id}}"
                    class="flex items-center justify-center h-14 w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-blue">
                    <img src="@/assets/images/icons/eye-white.svg" class="flex size-6 shrink-0" alt="icon">
                    <span class="font-semibold text-white">Details</span>
                </RouterLink >
            </div>
        </div>
    </div>
</template>