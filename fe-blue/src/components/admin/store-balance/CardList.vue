<script setup>
import { formatRupiah } from '@/helpers/format';
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
        <div class="flex items-center gap-5 justify-between pr-[30px]">
            <div class="flex items-center gap-[14px] w-[320px]">
                <div class="flex size-[84px] shrink-0 rounded-full bg-custom-background overflow-hidden">
                    <img :src="item.store?.logo" class="size-full object-cover" alt="photo">
                </div>
                <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                    <p class="font-bold text-lg leading-tight w-full truncate">
                        {{ item.store?.name }}
                    </p>
                    <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                        <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon">
                        {{ item.store?.user?.name }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-[10px] w-[260px]">
                <div
                    class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                    <img src="@/assets/images/icons/stickynote-black.svg" class="flex size-6 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-1">
                    <p class="font-bold text-lg leading-none">{{ item.store?.transaction_count }}</p>
                    <p class="font-semibold text-custom-grey">Total Transaction</p>
                </div>
            </div>
            <div class="flex items-center gap-[10px] w-[260px]">
                <div
                    class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                    <img src="@/assets/images/icons/shopping-cart-black.svg" class="flex size-6 shrink-0" alt="icon">
                </div>
                <div class="flex flex-col gap-1">
                    <p class="font-bold text-lg leading-none">{{ item.store?.product_count }}</p>
                    <p class="font-semibold text-custom-grey">Total Products</p>
                </div>
            </div>
        </div>
        <hr class="border-custom-stroke">
        <div class="flex items-center justify-between">
            <div class="flex flex-col gap-[6px]">
                <p class="font-bold text-xl text-custom-blue">Rp {{ formatRupiah(item.balance) }}</p>
                <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
                    <img src="@/assets/images/icons/wallet-3-grey.svg" class="size-6 flex shrink-0" alt="icon">
                    Total Wallet
                </p>
            </div>
            <div class="flex items-center justify-end gap-[14px]">
                <RouterLink :to="{ name : 'admin.store-balance.detail', params: { id: item.id }}"
                    class="flex items-center justify-center h-14 w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-blue">
                    <img src="@/assets/images/icons/eye-white.svg" class="flex size-6 shrink-0" alt="icon">
                    <span class="font-semibold text-white">Details</span>
                </RouterLink>
            </div>
        </div>
    </div>
</template>