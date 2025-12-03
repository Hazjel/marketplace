<script setup>
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
    <div class="card flex items-center rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-6 bg-white">
        <div class="flex items-center gap-[14px] w-full min-w-0">
            <div class="flex size-[92px] shrink-0 rounded-full bg-custom-background overflow-hidden">
                <img :src="item.profile_picture" class="size-full object-cover" alt="photo">
            </div>
            <div class="flex flex-col gap-[6px] w-full overflow-hidden">
                <p class="font-bold text-[22px] leading-tight w-full truncate">
                    {{ item.name }}
                </p>
                <p class="flex items-center gap-1 font-semibold text-lg text-custom-grey leading-none">
                    <img src="@/assets/images/icons/call-grey.svg" class="size-5" alt="icon">
                    <span v-if="item?.role === 'store'">
                        {{ item.store?.phone }}
                    </span>
                    <span v-if="item?.role === 'buyer'">
                        {{ item.buyer?.phone_number }}
                    </span>
                </p>
            </div>
        </div>
        <div class="flex items-center gap-[10px] w-full">
            <div
                class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center">
                <img src="@/assets/images/icons/profile-tick-black.svg" class="flex size-6 shrink-0" alt="icon">
            </div>
            <div class="flex flex-col gap-1">
                <p class="font-bold text-lg leading-none">{{ item.role }}</p>
                <p class="font-semibold text-custom-grey">User Role</p>
            </div>
        </div>
    </div>
</template>