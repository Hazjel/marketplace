<script setup>
import { formatDate } from '@/helpers/format'
import { useProductStore } from '@/stores/product'
import { storeToRefs } from 'pinia'
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const product = ref({})
const activeImage = ref()

const productStore = useProductStore()
const { fetchProductById } = productStore

const fetchProduct = async () => {
  const response = await fetchProductById(route.params.id)

  product.value = response

  product.value.product_images.sort((a, b) => {
    return (b.is_thumbnail === true) - (a.is_thumbnail === true)
  })

  activeImage.value = product.value?.product_images?.find((img) => img.is_thumbnail)
}



onMounted(() => {
  fetchProduct()
})
</script>

<template>
  <div class="flex flex-col lg:flex-row flex-1 gap-5">
    <!-- Main Info Section -->
    <section class="flex flex-col w-full h-fit rounded-2xl p-5 gap-5 bg-white dark:bg-surface-card dark:text-white border border-gray-100 dark:border-white/10 shadow-sm">
      <!-- Product Header -->
      <div class="flex items-center gap-4 overflow-hidden">
        <div class="flex size-20 shrink-0 rounded-2xl bg-gray-50 dark:bg-white/5 overflow-hidden items-center justify-center border border-gray-100 dark:border-white/10">
          <img
            :src="product?.product_images?.find((image) => image.is_thumbnail)?.image"
            class="size-full object-contain" alt="icon" />
        </div>
        <div class="flex flex-col flex-1 gap-1.5 overflow-hidden">
          <p class="font-bold text-lg truncate">{{ product.name }}</p>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 text-xs font-semibold w-fit">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
            {{ product.product_category?.name }}
          </span>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-2 gap-3">
        <div class="flex items-center gap-3 rounded-xl border border-gray-100 dark:border-white/10 p-3">
          <div class="flex size-10 shrink-0 rounded-lg bg-green-50 dark:bg-green-500/10 items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
          </div>
          <div class="flex flex-col gap-0.5">
            <p class="font-bold text-lg leading-none">{{ product.total_sold || 0 }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Terjual</p>
          </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-gray-100 dark:border-white/10 p-3">
          <div class="flex size-10 shrink-0 rounded-lg bg-blue-50 dark:bg-blue-500/10 items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
          </div>
          <div class="flex flex-col gap-0.5">
            <p class="font-bold text-lg leading-none">{{ product?.stock }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Stok</p>
          </div>
        </div>
      </div>

      <hr class="border-gray-100 dark:border-white/10" />

      <!-- Description -->
      <div class="flex flex-col gap-2">
        <p class="font-bold text-base">Tentang Produk</p>
        <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap leading-relaxed">{{ product.description }}</p>
      </div>

      <hr class="border-gray-100 dark:border-white/10" />

      <!-- Footer -->
      <div class="flex items-center">
        <p class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          Dibuat {{ formatDate(product.created_at) }}
        </p>
      </div>
    </section>

    <!-- Sidebar: Gallery & Details -->
    <section class="flex flex-col w-full lg:w-[400px] shrink-0 h-fit rounded-2xl p-5 gap-5 bg-white dark:bg-surface-card dark:text-white border border-gray-100 dark:border-white/10 shadow-sm">
      <!-- Image Gallery -->
      <div class="grid grid-cols-3 gap-3">
        <div
          v-for="(image, index) in product?.product_images"
          :key="index"
          class="relative flex h-[110px] rounded-xl bg-gray-50 dark:bg-white/5 overflow-hidden border border-gray-100 dark:border-white/10">
          <img :src="image.image" class="size-full object-cover" alt="thumbnail" />
        </div>
      </div>

      <!-- Product Specs -->
      <div class="flex flex-col rounded-2xl border border-gray-100 dark:border-white/10 p-4 gap-4">
        <div class="flex items-center gap-3">
          <div class="flex size-11 shrink-0 rounded-xl bg-gray-50 dark:bg-white/5 items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
            </svg>
          </div>
          <div class="flex flex-col gap-0.5">
            <p class="font-bold text-sm leading-none">{{ product.weight }} KG</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Berat Produk</p>
          </div>
        </div>
        <hr class="border-gray-100 dark:border-white/10" />
        <div class="flex items-center gap-3">
          <div class="flex size-11 shrink-0 rounded-xl bg-gray-50 dark:bg-white/5 items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
          </div>
          <div class="flex flex-col gap-0.5">
            <p class="font-bold text-sm leading-none">{{ product.product_category?.name }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Kategori</p>
          </div>
        </div>
        <hr class="border-gray-100 dark:border-white/10" />
        <div class="flex items-center gap-3">
          <div class="flex size-11 shrink-0 rounded-xl bg-gray-50 dark:bg-white/5 items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="flex flex-col gap-0.5">
            <p class="font-bold text-sm leading-none capitalize">{{ product.condition }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Kondisi</p>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
