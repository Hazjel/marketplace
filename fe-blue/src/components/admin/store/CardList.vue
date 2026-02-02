<script setup>
import { can } from '@/helpers/permissionHelper'
import { formatDate } from '@/helpers/format'
import { RouterLink } from 'vue-router'

defineProps({
  item: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['delete'])
</script>

<template>
  <div
    class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white"
  >
    <div
      class="flex flex-col md:flex-row items-start md:items-center gap-5 justify-between pr-0 md:pr-[30px]"
    >
      <div class="flex items-center gap-[14px] w-full md:w-[320px]">
        <div class="flex size-[84px] shrink-0 rounded-full bg-custom-background overflow-hidden">
          <img :src="item.logo" class="size-full object-cover" alt="photo" />
        </div>
        <div class="flex flex-col gap-[6px] w-full overflow-hidden">
          <p class="font-bold text-base md:text-lg leading-tight w-full truncate">
            {{ item.name }}
          </p>
          <p
            class="flex items-center gap-1 font-semibold text-sm md:text-base text-custom-grey leading-none"
          >
            <img src="@/assets/images/icons/calendar-2-grey.svg" class="size-5" alt="icon" />
            Since {{ formatDate(item.created_at) }}
          </p>
        </div>
      </div>
      <div class="grid grid-cols-2 md:flex md:items-center gap-[30px] w-full md:w-auto">
        <div class="flex items-center gap-[10px] w-full md:w-[260px]">
          <div
            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
          >
            <img
              src="@/assets/images/icons/stickynote-black.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <div class="flex flex-col gap-1">
            <p class="font-bold text-base md:text-lg leading-none">{{ item.transaction_count }}</p>
            <p class="font-semibold text-sm md:text-base text-custom-grey">Total Transaction</p>
          </div>
        </div>
        <div class="flex items-center gap-[10px] w-full md:w-[260px]">
          <div
            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
          >
            <img
              src="@/assets/images/icons/shopping-cart-black.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <div class="flex flex-col gap-1">
            <p class="font-bold text-base md:text-lg leading-none">{{ item.product_count }}</p>
            <p class="font-semibold text-sm md:text-base text-custom-grey">Total Products</p>
          </div>
        </div>
      </div>
    </div>
    <hr class="border-custom-stroke" />
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
      <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
        <img
          src="@/assets/images/icons/calendar-2-grey.svg"
          class="size-6 flex shrink-0"
          alt="icon"
        />
        Created on {{ formatDate(item.created_at) }}
      </p>
      <div class="flex flex-col md:flex-row items-center justify-end gap-[14px] w-full md:w-auto">
        <button
          v-if="can('store-delete')"
          class="flex items-center justify-center h-14 w-full md:w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10"
          @click="emit('delete', item.id)"
        >
          <img src="@/assets/images/icons/trash-red.svg" class="flex size-6 shrink-0" alt="icon" />
          <span class="font-semibold text-custom-red">Delete</span>
        </button>
        <RouterLink
          :to="{ name: 'admin.store.detail', params: { id: item.id } }"
          class="flex items-center justify-center h-14 w-full md:w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-blue"
        >
          <img src="@/assets/images/icons/eye-white.svg" class="flex size-6 shrink-0" alt="icon" />
          <span class="font-semibold text-white">Details</span>
        </RouterLink>
      </div>
    </div>
  </div>
</template>
