<script setup>
import { formatRupiah, formatToClientTimeZone } from '@/helpers/format'
import { can } from '@/helpers/permissionHelper'
import { RouterLink } from 'vue-router'
import Badge from '@/components/Atom/Badge.vue'

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

const getDetailRoute = (transactionId) => {
  if (user.value?.role === 'admin') {
    return {
      name: 'admin.transaction.detail',
      params: { id: transactionId }
    }
  }
  return {
    name: 'user.transaction.detail',
    params: { username: user.value?.username, id: transactionId }
  }
}
const emit = defineEmits(['delete'])
</script>

<template>
  <div
    class="card flex flex-col rounded-[20px] border border-custom-stroke py-[18px] px-5 gap-5 bg-white"
  >
    <div class="flex items-center justify-between">
      <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
        <img
          src="@/assets/images/icons/calendar-2-grey.svg"
          class="size-6 flex shrink-0"
          alt="icon"
        />
        {{ formatToClientTimeZone(item.created_at) }}
      </p>
      <Badge v-if="item.delivery_status === 'pending'" variant="warning" size="sm"> Pending </Badge>
      <Badge v-if="item.delivery_status === 'processing'" variant="info" size="sm">
        Processing
      </Badge>
      <Badge v-if="item.delivery_status === 'delivering'" variant="orange" size="sm">
        Delivering
      </Badge>
      <Badge v-if="item.delivery_status === 'completed'" variant="success" size="sm">
        Completed
      </Badge>
    </div>
    <hr class="border-custom-stroke" />
    <div
      class="flex flex-col md:flex-row items-start md:items-center gap-5 justify-between pr-0 md:pr-[30px]"
    >
      <div class="flex items-center gap-[14px] w-full md:w-[320px]">
        <div class="flex size-[84px] shrink-0 rounded-full bg-custom-background overflow-hidden">
          <img
            :src="item.buyer?.user?.profile_picture"
            class="size-full object-cover"
            alt="photo"
          />
        </div>
        <div class="flex flex-col gap-[6px] w-full overflow-hidden">
          <p class="font-bold text-lg leading-tight w-full truncate">
            {{ item?.buyer?.user?.name }}
          </p>
        </div>
      </div>
      <!-- Stats Container: Grid on mobile (2 cols), Row on desktop -->
      <div class="grid grid-cols-2 gap-5 w-full md:flex md:w-auto md:gap-10">
        <div
          class="flex flex-col sm:flex-row items-center sm:items-start gap-[10px] w-full md:w-[260px]"
        >
          <div
            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
          >
            <img
              src="@/assets/images/icons/shopping-cart-black.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <div class="flex flex-col gap-1 w-full overflow-hidden">
            <p class="font-bold text-lg leading-none truncate">
              {{ item?.transaction_details?.length }}
            </p>
            <p class="font-semibold text-custom-grey text-sm sm:text-base truncate">
              Total Products
            </p>
          </div>
        </div>
        <div
          class="flex flex-col sm:flex-row items-center sm:items-start gap-[10px] w-full md:w-[260px]"
        >
          <div
            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
          >
            <img
              src="@/assets/images/icons/box-black.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <div class="flex flex-col gap-1 w-full overflow-hidden">
            <p class="font-bold text-lg leading-none truncate">
              {{ item?.transaction_details?.reduce((total, detail) => total + detail.qty, 0) }}
            </p>
            <p class="font-semibold text-custom-grey text-sm sm:text-base truncate">
              Total Quantity
            </p>
          </div>
        </div>
      </div>
    </div>
    <hr class="border-custom-stroke" />
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
      <div class="flex flex-col gap-[6px]">
        <p class="font-bold text-xl text-custom-blue">{{ formatRupiah(item.grand_total) }}</p>
        <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
          <img src="@/assets/images/icons/money-grey.svg" class="size-6 flex shrink-0" alt="icon" />
          Grand Total
        </p>
      </div>
      <div class="flex items-center justify-end gap-[14px] w-full md:w-auto">
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
