<script setup>
// import { can } from '@/helpers/permissionHelper';
import { RouterLink } from 'vue-router'
import { formatDate } from '@/helpers/format'

defineProps({
  item: {
    type: Object,
    required: true
  },
  selected: {
    type: Boolean,
    default: false
  }
})

import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const emit = defineEmits(['delete', 'toggle-selection'])
</script>

<template>
  <div
    class="card flex flex-col rounded-[20px] border border-custom-stroke dark:border-white/10 py-[18px] px-5 gap-5 bg-white dark:bg-surface-card dark:text-white hover-scale transition-all duration-300">
    <div class="flex flex-col md:flex-row items-start md:items-center gap-5 justify-between pr-0 md:pr-[30px]">
      <div class="flex w-full md:w-auto flex-1 items-center gap-[14px] overflow-hidden">
        <label class="cursor-pointer relative z-10">
          <input type="checkbox" :checked="selected"
            class="checkbox checkbox-primary rounded-lg size-5 md:size-6 border-2 border-gray-300 checked:bg-custom-blue checked:border-custom-blue transition-all"
            @change="emit('toggle-selection', item.id)" />
        </label>
        <div
          class="flex size-[92px] shrink-0 rounded-2xl bg-custom-background overflow-hidden items-center justify-center border border-transparent dark:border-white/10">
          <img :src="item?.product_images?.find((image) => image.is_thumbnail)?.image" class="size-full object-contain"
            alt="icon" />
        </div>
        <div class="flex flex-col flex-1 gap-[6px] overflow-hidden">
          <p class="font-bold text-base md:text-lg truncate">{{ item.name }}</p>
          <p class="font-semibold leading-none text-custom-grey flex items-center gap-[6px]">
            <span class="font-bold text-sm md:text-base text-custom-blue">{{
              item.product_category?.name
              }}</span>
          </p>
        </div>
      </div>
      <!-- Stats Grid on Mobile -->
      <div class="grid grid-cols-2 gap-4 w-full md:flex md:w-auto md:gap-[50px]">
        <div class="flex items-center gap-[10px] w-full md:w-auto">
          <div
            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background dark:bg-white/10 overflow-hidden items-center justify-center">
            <img src="@/assets/images/icons/status-up-black.svg" class="flex size-6 shrink-0 dark:invert" alt="icon" />
          </div>
          <div class="flex flex-col gap-1 overflow-hidden">
            <p class="font-bold text-base md:text-lg leading-none truncate">
              {{ item.total_sold ? item.total_sold.toLocaleString() : 0 }}
            </p>
            <p class="font-semibold text-sm md:text-base text-custom-grey truncate">Total Sold</p>
          </div>
        </div>
        <div class="flex items-center gap-[10px] w-full md:w-auto">
          <div
            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background dark:bg-white/10 overflow-hidden items-center justify-center">
            <img src="@/assets/images/icons/box-black.svg" class="flex size-6 shrink-0 dark:invert" alt="icon" />
          </div>
          <div class="flex flex-col gap-1 overflow-hidden">
            <p class="font-bold text-base md:text-lg leading-none truncate">{{ item.stock }}</p>
            <p class="font-semibold text-sm md:text-base text-custom-grey truncate">Total Stock</p>
          </div>
        </div>
      </div>
    </div>
    <hr class="border-custom-stroke dark:border-white/10" />
    <div class="flex flex-col md:flex-row items-center justify-between gap-5">
      <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none w-full md:w-auto">
        <img src="@/assets/images/icons/calendar-2-grey.svg" class="size-6 flex shrink-0 dark:invert" alt="icon" />
        Created on {{ formatDate(item.created_at) }}
      </p>
      <div class="flex flex-col md:flex-row items-center justify-end gap-[14px] w-full md:w-auto">
        <button v-if="user?.permissions?.includes('product-delete')"
          class="flex items-center justify-center h-14 w-full md:w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10"
          @click="emit('delete', item.id)">
          <img src="@/assets/images/icons/trash-red.svg" class="flex size-6 shrink-0" alt="icon" />
          <span class="font-semibold text-custom-red">Delete</span>
        </button>
        <RouterLink v-if="user?.permissions?.includes('product-edit')"
          :to="{ name: 'admin.product.edit', params: { id: item.id } }"
          class="flex items-center justify-center h-14 w-full md:w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-black">
          <img src="@/assets/images/icons/edit-white.svg" class="flex size-6 shrink-0" alt="icon" />
          <span class="font-semibold text-white">Edit</span>
        </RouterLink>
        <RouterLink :to="{ name: 'admin.product.detail', params: { id: item.id } }"
          class="flex items-center justify-center h-14 w-full md:w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-blue">
          <img src="@/assets/images/icons/eye-white.svg" class="flex size-6 shrink-0" alt="icon" />
          <span class="font-semibold text-white">Details</span>
        </RouterLink>
      </div>
    </div>
  </div>
</template>
