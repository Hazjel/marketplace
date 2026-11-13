<script setup>
import { onMounted, ref } from 'vue'
import { axiosInstance } from '@/plugins/axios'
import { useAuthStore } from '@/stores/auth'
import { logger } from '@/utils/logger'
import ProductCard from '@/components/card/ProductCard.vue'
import SkeletonProductCard from '@/components/skeleton/SkeletonProductCard.vue'

// "Lanjut cek ini, yuk": the signed-in buyer's last viewed products. Guests
// see nothing (views are kept per session, but there is no endpoint for them).
const authStore = useAuthStore()
const products = ref([])
const loading = ref(false)

onMounted(async () => {
  if (!authStore.token) return

  loading.value = true
  try {
    const response = await axiosInstance.get('product/recently-viewed')
    products.value = response.data.data || []
  } catch (error) {
    // Nice to have: on failure the row just stays hidden.
    logger.error('Fetch Recently Viewed Error', error)
    products.value = []
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <section v-if="loading || products.length" class="flex flex-col gap-6 md:gap-8">
    <h2 class="font-medium text-lg md:text-[32px] leading-tight text-custom-black dark:text-white">
      Lanjut cek ini, yuk
    </h2>

    <div class="-mx-4 flex snap-x gap-4 overflow-x-auto px-4 pb-2 md:mx-0 md:gap-6 md:px-0">
      <template v-if="loading">
        <div v-for="i in 5" :key="i" class="w-40 shrink-0 md:w-52">
          <SkeletonProductCard />
        </div>
      </template>
      <div v-for="product in products" v-else :key="product.id" class="w-40 shrink-0 snap-start md:w-52">
        <ProductCard :item="product" />
      </div>
    </div>
  </section>
</template>
