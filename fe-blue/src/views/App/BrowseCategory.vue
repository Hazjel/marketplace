<script setup>
import { useProductCategoryStore } from '@/stores/productCategory'
import { useProductStore } from '@/stores/product'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import ProductCard from '@/components/card/ProductCard.vue'
import FilterSidebar from '@/components/App/FilterSidebar.vue'

const route = useRoute()
import { useHead } from '@vueuse/head'
import { computed } from 'vue'

const productCategory = ref({})

useHead({
  title: computed(() =>
    productCategory.value?.name ? `${productCategory.value.name} | Blukios` : 'Category | Blukios'
  ),
  meta: [
    {
      name: 'description',
      content: computed(
        () =>
          `Explore high quality ${productCategory.value?.name || 'products'} on Blukios. Authenticity Guaranteed.`
      )
    }
  ]
})

const productCategoryStore = useProductCategoryStore()
const { fetchProductCategoryBySlug } = productCategoryStore

const productStore = useProductStore()
const { products, loading: loadingProducts, meta } = storeToRefs(productStore)
const { fetchProductsPaginated, loadMoreProducts } = productStore

const showFilters = ref(false)
const currentFilters = ref({})

const fetchProductCategory = async () => {
  const response = await fetchProductCategoryBySlug(route.params.slug)
  productCategory.value = response
}

const fetchProducts = async (filters = {}) => {
  if (!productCategory.value.id) return

  await fetchProductsPaginated({
    product_category_id: productCategory.value.id,
    row_per_page: 12,
    ...filters
  })
}

const handleLoadMore = async () => {
  await loadMoreProducts({
    product_category_id: productCategory.value.id,
    row_per_page: 8,
    page: meta.value.current_page + 1,
    ...currentFilters.value
  })
}

const handleFilterChange = (newFilters) => {
  currentFilters.value = newFilters
  fetchProducts(newFilters)
}

onMounted(async () => {
  await fetchProductCategory()
  await fetchProducts()
})

watch(
  () => route.params.slug,
  async () => {
    await fetchProductCategory()
    await fetchProducts(currentFilters.value)
  }
)
</script>

<template>
  <header
    class="w-full max-w-[1920px] mx-auto overflow-hidden bg-custom-background py-8 md:p-[52px]"
  >
    <div class="flex flex-col w-full max-w-[1280px] px-4 md:px-[52px] gap-3 mx-auto">
      <div class="flex items-center gap-3">
        <RouterLink
          :to="{ name: 'app.home' }"
          class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue"
        >
          Homepage
        </RouterLink>
        <span class="font-medium text-xl text-custom-grey">/</span>
        <RouterLink
          :to="{ name: 'app.browse-category', params: { slug: productCategory?.slug } }"
          class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue"
        >
          {{ productCategory?.name }}
        </RouterLink>
      </div>
      <h1 class="font-extrabold text-[32px] capitalize">Explore based on Gadget Category</h1>
      <div class="flex items-center gap-4">
        <div class="group flex items-center gap-2">
          <img src="@/assets/images/icons/box-grey.svg" class="flex size-5 shrink-0" alt="icon" />
          <span class="font-semibold text-custom-grey"
            >{{ productCategory?.product_count }} Total Products</span
          >
        </div>
        <div class="group flex items-center gap-2">
          <img
            src="@/assets/images/icons/verify-star-grey.svg"
            class="flex size-5 shrink-0"
            alt="icon"
          />
          <span class="font-semibold text-custom-grey">Authenticity Guaranteed</span>
        </div>
      </div>
    </div>
  </header>

  <main
    class="flex flex-col lg:flex-row gap-10 w-full max-w-[1280px] px-4 md:px-[52px] mt-8 md:mt-[50px] mb-20 md:mb-[100px] mx-auto"
  >
    <!-- Mobile Filter Toggle -->
    <button
      class="flex lg:hidden items-center justify-between w-full p-4 bg-white border border-custom-stroke rounded-xl"
      @click="showFilters = !showFilters"
    >
      <span class="font-bold text-lg">Filter Products</span>
      <i
        class="fa-solid fa-chevron-down transition-transform"
        :class="{ 'rotate-180': showFilters }"
      ></i>
    </button>

    <!-- Sidebar -->
    <aside class="shrink-0 w-full lg:w-auto" :class="{ 'hidden lg:block': !showFilters }">
      <FilterSidebar :initial-filters="currentFilters" @filter-change="handleFilterChange" />
    </aside>

    <!-- Content -->
    <div class="flex flex-col gap-10 w-full">
      <template v-if="loadingProducts || products.length > 0">
        <section id="Popular" class="flex flex-col gap-9">
          <div class="flex items-center justify-between">
            <h2 class="font-extrabold text-[32px]">All Products</h2>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 md:gap-6">
            <ProductCard v-for="product in products" :key="product.id" :item="product" />
          </div>
        </section>

        <button
          v-if="meta.current_page < meta.last_page"
          class="flex items-center w-fit h-14 rounded-[18px] py-4 px-6 gap-[10px] bg-custom-black mx-auto"
          @click="handleLoadMore"
        >
          <span class="font-medium text-white">Load More</span>
          <img
            src="@/assets/images/icons/arrow-down-white.svg"
            class="flex size-6 shrink-0"
            alt="icon"
          />
        </button>
      </template>

      <section v-else class="flex flex-col gap-9 items-center justify-center py-20 text-center">
        <div class="flex flex-col items-center gap-4">
          <img
            src="@/assets/images/icons/box-search-grey.svg"
            class="size-20 opacity-50"
            alt="No results"
          />
          <h2 class="font-bold text-2xl text-custom-grey">Belum ada produk di kategori ini</h2>
          <p class="text-custom-grey">Coba cek kategori lain atau kembali ke beranda.</p>
          <RouterLink
            :to="{ name: 'app.home' }"
            class="mt-2 px-6 py-3 bg-custom-black text-white rounded-full font-medium hover:bg-black/80 transition-colors"
          >
            Ke Beranda
          </RouterLink>
        </div>
      </section>
    </div>
  </main>
</template>
