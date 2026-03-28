<script setup>
import StoreCard from '@/components/card/StoreCard.vue'
import SkeletonStoreCard from '@/components/skeleton/SkeletonStoreCard.vue'
import { useStoreStore } from '@/stores/store'
import { storeToRefs } from 'pinia'
import { onMounted } from 'vue'

const storeStore = useStoreStore()
const { stores, loading } = storeToRefs(storeStore)
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

    <div v-if="loading && stores.length === 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-6">
      <SkeletonStoreCard v-for="i in 6" :key="i" />
    </div>

    <div v-else-if="stores.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-6">
      <StoreCard v-for="store in stores" :key="store.id" :item="store" />
    </div>

    <div v-else class="flex flex-col items-center justify-center py-20 text-center">
      <img src="@/assets/images/icons/shop-grey.svg" class="size-20 opacity-50 mb-4" alt="No stores" />
      <h2 class="font-bold text-2xl text-custom-grey">No stores available</h2>
      <p class="text-custom-grey mt-1">Check back later for new sellers.</p>
      <RouterLink
        :to="{ name: 'app.home' }"
        class="mt-4 px-6 py-3 bg-custom-black text-white rounded-full font-medium hover:bg-black/80 transition-colors"
      >
        Back to Home
      </RouterLink>
    </div>
  </div>
</template>
