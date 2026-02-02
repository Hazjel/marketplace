<script setup>
import { formatRupiah, formatDate } from '@/helpers/format'
import { useProductStore } from '@/stores/product'
import { storeToRefs } from 'pinia'
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

const route = useRoute()

const product = ref({})
const activeImage = ref()

const productStore = useProductStore()
const { loading } = storeToRefs(productStore)
const { fetchProductById } = productStore

const fetchProduct = async () => {
  const response = await fetchProductById(route.params.id)

  product.value = response

  product.value.product_images.sort((a, b) => {
    return (b.is_thumbnail === true) - (a.is_thumbnail === true)
  })

  activeImage.value = product.value?.product_images?.find((img) => img.is_thumbnail)
}

function setActiveImage(image) {
  activeImage.value = image
}

onMounted(() => {
  fetchProduct()
})
</script>

<template>
  <div class="flex flex-1 gap-5">
    <section class="flex flex-col w-full h-fit rounded-[20px] p-5 gap-5 bg-white">
      <div class="flex items-center gap-[14px] overflow-hidden">
        <div
          class="flex size-[92px] shrink-0 rounded-2xl bg-custom-background overflow-hidden items-center justify-center"
        >
          <img
            :src="product?.product_images?.find((image) => image.is_thumbnail)?.image"
            class="size-full object-contain"
            alt="icon"
          />
        </div>
        <div class="flex flex-col flex-1 gap-[6px] overflow-hidden">
          <p class="font-bold text-lg truncate">{{ product.name }}</p>
          <p class="font-semibold leading-none text-custom-grey flex items-center gap-[6px]">
            <span class="font-bold text-custom-blue">{{ product.product_category?.name }}</span>
          </p>
        </div>
      </div>
      <div class="flex flex-col rounded-[20px] border border-custom-stroke p-4 gap-5">
        <div class="flex items-center gap-[10px] w-[260px]">
          <div
            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
          >
            <img
              src="@/assets/images/icons/status-up-grey.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <div class="flex flex-col gap-1">
            <p class="font-bold text-lg leading-none">{{ product.total_sold || 0 }}</p>
            <p class="font-semibold text-custom-grey">Total Sold</p>
          </div>
        </div>
        <hr class="border-custom-stroke last:hidden" />
        <div class="flex items-center gap-[10px] w-[260px]">
          <div
            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
          >
            <img
              src="@/assets/images/icons/box-black.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <div class="flex flex-col gap-1">
            <p class="font-bold text-lg leading-none">{{ product?.stock }}</p>
            <p class="font-semibold text-custom-grey">Total Stock</p>
          </div>
        </div>
      </div>
      <hr class="border-custom-stroke" />
      <div class="flex flex-col gap-2">
        <p class="font-bold text-xl">About Product</p>
        <p class="font-semibold text-custom-grey whitespace-pre-wrap">{{ product.description }}</p>
      </div>
      <hr class="border-custom-stroke" />
      <div class="flex items-center justify-between">
        <p class="flex items-center gap-2 font-semibold text-custom-grey leading-none">
          <img
            src="@/assets/images/icons/calendar-2-grey.svg"
            class="size-6 flex shrink-0"
            alt="icon"
          />
          Created on {{ formatDate(product.created_at) }}
        </p>
      </div>
    </section>
    <section class="flex flex-col w-[435px] shrink-0 h-fit rounded-[20px] p-5 gap-5 bg-white">
      <div class="grid grid-cols-3 gap-4">
        <div
          v-for="image in product?.product_images"
          data-modal="Gallery-Modal"
          data-index="1"
          data-gallery="../assets/images/thumbnails/macbook-pro-m2.png"
          class="relative flex h-[121px] rounded-2xl bg-custom-background overflow-hidden"
        >
          <img :src="image.image" class="size-full object-cover" alt="thumbnail" />
        </div>
      </div>
      <div class="flex flex-col rounded-[20px] border border-custom-stroke p-4 gap-5">
        <div class="flex items-center gap-[10px] w-[260px]">
          <div
            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
          >
            <img
              src="@/assets/images/icons/box-2-black.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <div class="flex flex-col gap-1">
            <p class="font-bold text-lg leading-none">{{ product.weight }} KG</p>
            <p class="font-semibold text-custom-grey">Product Weight</p>
          </div>
        </div>
        <hr class="border-custom-stroke last:hidden" />
        <div class="flex items-center gap-[10px] w-[260px]">
          <div
            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
          >
            <img
              src="@/assets/images/icons/bag-black.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <div class="flex flex-col gap-1">
            <p class="font-bold text-lg leading-none">{{ product.product_category?.name }}</p>
            <p class="font-semibold text-custom-grey">Product Category</p>
          </div>
        </div>
        <hr class="border-custom-stroke last:hidden" />
        <div class="flex items-center gap-[10px] w-[260px]">
          <div
            class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
          >
            <img
              src="@/assets/images/icons/gift-black.svg"
              class="flex size-6 shrink-0"
              alt="icon"
            />
          </div>
          <div class="flex flex-col gap-1">
            <p class="font-bold text-lg leading-none">{{ product.condition }}</p>
            <p class="font-semibold text-custom-grey">Product Type</p>
          </div>
        </div>
        <hr class="border-custom-stroke last:hidden" />
      </div>
    </section>
  </div>
</template>
