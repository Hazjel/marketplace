<script setup>
import { formatRupiah, formatToClientTimeZone } from '@/helpers/format'
import { can } from '@/helpers/permissionHelper'
import { RouterLink } from 'vue-router'

defineProps({
  item: {
    type: Object,
    required: true
  }
})

import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const getDetailRoute = (withdrawalId) => {
  if (user.value?.role === 'admin') {
    return {
      name: 'admin.withdrawal.detail',
      params: { id: withdrawalId }
    }
  }
  return {
    name: 'user.withdrawal.detail',
    params: { username: user.value?.username, id: withdrawalId }
  }
}
const emit = defineEmits(['delete'])
</script>

<template>
  <div
    class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white"
  >
    <div class="flex flex-col md:flex-row items-start md:items-center gap-5 justify-between">
      <div class="flex items-center gap-[14px] w-full md:w-[320px]">
        <div class="flex size-[84px] shrink-0 rounded-full bg-custom-background overflow-hidden">
          <img :src="item?.store_balance?.store?.logo" class="size-full object-cover" alt="photo" />
        </div>
        <div class="flex flex-col gap-[6px] w-full overflow-hidden">
          <p class="font-bold text-lg leading-tight w-full truncate">
            {{ item?.store_balance?.store?.name }}
          </p>
          <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
            <img src="@/assets/images/icons/user-grey.svg" class="size-5" alt="icon" />
            {{ item?.store_balance?.store?.user?.name }}
          </p>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-5 w-full md:flex md:w-auto md:gap-10 items-center">
        <div class="flex items-center gap-[10px] w-full md:w-[260px]">
          <div
            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
          >
            <img
              src="@/assets/images/icons/calendar-2-black.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <div class="flex flex-col gap-1 overflow-hidden">
            <p class="font-bold text-lg leading-none truncate">
              {{ formatToClientTimeZone(item.created_at) }}
            </p>
            <p class="font-semibold text-custom-grey truncate">Request Date</p>
          </div>
        </div>
        <p
          class="badge w-fit rounded-full py-3 px-[18px] flex shrink-0 font-bold uppercase bg-custom-orange/10 text-custom-orange"
        >
          {{ item.status }}
        </p>
      </div>
    </div>
    <hr class="border-custom-stroke" />
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
      <div class="flex flex-col gap-[6px]">
        <p class="font-bold text-xl text-custom-blue">Rp {{ formatRupiah(item?.amount) }}</p>
        <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
          <img
            src="@/assets/images/icons/card-send-grey.svg"
            class="size-6 flex shrink-0"
            alt="icon"
          />
          Withdrawal Request
        </p>
      </div>
      <div class="flex flex-col md:flex-row items-center justify-end gap-[14px] w-full md:w-auto">
        <button
          class="flex items-center justify-center h-14 w-full md:w-fit shrink-0 rounded-2xl p-4 gap-2 bg-custom-black"
        >
          <span class="font-semibold text-white">Export</span>
          <img
            src="@/assets/images/icons/receive-square-white.svg"
            class="flex size-6 shrink-0"
            alt="icon"
          />
        </button>
        <RouterLink
          :to="getDetailRoute(item.id)"
          class="flex items-center justify-center h-14 w-full md:w-[126px] shrink-0 rounded-2xl p-4 gap-2 bg-custom-blue"
        >
          <img src="@/assets/images/icons/eye-white.svg" class="flex size-6 shrink-0" alt="icon" />
          <span class="font-semibold text-white">Details</span>
        </RouterLink>
      </div>
    </div>
  </div>
</template>
