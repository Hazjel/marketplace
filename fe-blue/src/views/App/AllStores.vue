<script setup>
import StoreCard from '@/components/card/StoreCard.vue'
import { useStoreStore } from '@/stores/store'
import { storeToRefs } from 'pinia'
import { onMounted } from 'vue'

const storeStore = useStoreStore()
const { stores } = storeToRefs(storeStore)
const { fetchStores } = storeStore

onMounted(() => {
  fetchStores({
    limit: 100 // Fetch all stores
  })
})
</script>

<template>
  <div
    class="flex flex-col gap-8 w-full max-w-[1280px] px-4 md:px-[75px] mx-auto mt-8 md:mt-10 mb-20 md:mb-24"
  >
    <div class="flex items-center justify-between">
      <h1 class="font-bold text-[32px] text-custom-black">Trusted Sellers</h1>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-6">
      <StoreCard v-for="store in stores" :key="store.id" :item="store" />
    </div>
  </div>
</template>
