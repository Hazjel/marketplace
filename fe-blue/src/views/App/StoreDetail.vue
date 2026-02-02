```html
<script setup>
import ProductCard from '@/components/card/ProductCard.vue'
import SkeletonProductCard from '@/components/skeleton/SkeletonProductCard.vue'
import ReviewCard from '@/components/card/ReviewCard.vue'
import StoreHeader from '@/components/store/StoreHeader.vue'
import { useProductStore } from '@/stores/product'
import { useStoreStore } from '@/stores/store'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@vueuse/head'
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()

const storeStore = useStoreStore()
const { store, reviews } = storeToRefs(storeStore)
const { fetchStoreByUsername, followStore, unfollowStore, checkFollowStatus, fetchStoreReviews } =
  storeStore

const productStore = useProductStore()
const { products, loading: loadingProducts, storeCategories } = storeToRefs(productStore)
const { fetchProducts, fetchCategoriesByStore } = productStore

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

useHead({
  title: computed(() =>
    store.value?.name ? `${store.value.name} | Blukios` : 'Store Detail | Blukios'
  ),
  meta: [
    {
      name: 'description',
      content: computed(
        () => `Visit ${store.value?.name} on Blukios. ${store.value?.address || ''}`
      )
    },
    {
      property: 'og:title',
      content: computed(() => store.value?.name || 'Store Detail')
    },
    {
      property: 'og:image',
      content: computed(() => store.value?.logo || '')
    }
  ]
})

const isFollowing = ref(false)
const activeTab = ref('products')
const selectedCategory = ref(null)
const selectedSort = ref('default')

const fetchStore = async () => {
  const response = await fetchStoreByUsername(route.params.username)
  store.value = response

  // Check follow status if user is logged in
  if (user.value && store.value?.id) {
    isFollowing.value = await checkFollowStatus(store.value.id)
  }
}

const handleSortChange = () => {
  let sortParams = {}
  switch (selectedSort.value) {
    case 'newest':
      sortParams = { sort_by: 'created_at', sort_direction: 'desc' }
      break
    case 'price_low':
      sortParams = { sort_by: 'price', sort_direction: 'asc' }
      break
    case 'price_high':
      sortParams = { sort_by: 'price', sort_direction: 'desc' }
      break
    case 'sold':
      sortParams = { sort_by: 'sold', sort_direction: 'desc' }
      break
    default:
      sortParams = {} // Default backend sorting
  }

  const params = {
    limit: 12,
    store_id: store.value.id,
    product_category_id: selectedCategory.value ? selectedCategory.value.id : null,
    ...sortParams
  }

  fetchProducts(params)
}

const handleFollow = async () => {
  if (!user.value) {
    return router.push({ name: 'auth.login' })
  }
  try {
    await followStore(store.value.id)
    isFollowing.value = true
    store.value.followers_count = (store.value.followers_count || 0) + 1
  } catch (error) {
    console.error('Follow failed', error)
  }
}

const handleUnfollow = async () => {
  try {
    await unfollowStore(store.value.id)
    isFollowing.value = false
    store.value.followers_count = Math.max(0, (store.value.followers_count || 0) - 1)
  } catch (error) {
    console.error('Unfollow failed', error)
  }
}

const handleCategoryFilter = (category) => {
  selectedCategory.value = category

  const params = {
    limit: 12,
    store_id: store.value.id,
    product_category_id: category ? category.id : null
  }

  fetchProducts(params)
}

watch(activeTab, (newTab) => {
  if (newTab === 'reviews' && reviews.value.length === 0) {
    fetchStoreReviews(route.params.username)
  }
})

onMounted(async () => {
  // Reset reviews to prevent showing reviews from previous store
  reviews.value = []

  await fetchStore()

  // Fetch Initial Products
  fetchProducts({
    limit: 12,
    store_id: store.value.id,
    random: true
  })

  // Fetch Store Categories
  fetchCategoriesByStore(route.params.username)
})
</script>

<template>
  <header class="w-full max-w-[1920px] mx-auto overflow-hidden bg-custom-background">
    <div class="flex flex-col w-full max-w-[1280px] py-4 md:py-6 px-4 md:px-[52px] gap-3 mx-auto">
      <div class="flex items-center gap-3">
        <RouterLink
          :to="{ name: 'app.home' }"
          class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue"
        >
          Homepage
        </RouterLink>
        <span class="font-medium text-xl text-custom-grey">/</span>
        <RouterLink
          :to="{ name: 'app.all-stores' }"
          class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue"
        >
          Stores
        </RouterLink>
        <span class="font-medium text-xl text-custom-grey">/</span>
        <a
          href="#"
          class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue"
        >
          {{ store?.name }}
        </a>
      </div>
    </div>
  </header>
  <main
    class="flex flex-col gap-8 md:gap-[52px] w-full max-w-[1280px] px-4 md:px-[52px] mt-8 md:mt-8 mb-20 md:mb-[100px] mx-auto"
  >
    <section class="flex flex-col gap-5 w-full">
      <StoreHeader
        :store="store"
        :is-following="isFollowing"
        @follow="handleFollow"
        @unfollow="handleUnfollow"
      />

      <div class="flex gap-4 overflow-x-auto pb-2 -mx-4 px-4 md:mx-0 md:px-0">
        <a
          href="#"
          class="flex w-[280px] md:w-[320px] shrink-0 overflow-hidden rounded-2xl shadow-sm hover:shadow-md transition-all"
        >
          <img
            src="@/assets/images/thumbnails/promo-potrait-1-small.png"
            class="size-full object-cover"
            alt="promo"
          />
        </a>
        <a
          href="#"
          class="flex w-[280px] md:w-[320px] shrink-0 overflow-hidden rounded-2xl shadow-sm hover:shadow-md transition-all"
        >
          <img
            src="@/assets/images/thumbnails/promo-potrait-2-small.png"
            class="size-full object-cover"
            alt="promo"
          />
        </a>
      </div>
    </section>
    <section class="flex flex-col gap-6 md:gap-8 animate-fade-in-up delay-200">
      <!-- Tabs Navigation -->
      <div
        id="Tab-Buttons"
        class="sticky top-0 z-30 bg-custom-background/95 backdrop-blur-sm pt-4 pb-2 border-b border-custom-stroke flex items-center gap-8 w-full overflow-x-auto hide-scrollbar"
      >
        <button
          :class="[
            'font-bold text-lg md:text-xl whitespace-nowrap transition-colors relative py-2',
            activeTab === 'home' ? 'text-custom-blue' : 'text-custom-grey hover:text-gray-700'
          ]"
          @click="activeTab = 'home'"
        >
          Beranda
          <div
            v-if="activeTab === 'home'"
            class="absolute bottom-[-9px] left-0 w-full h-[3px] bg-custom-blue rounded-t-full"
          ></div>
        </button>
        <button
          :class="[
            'font-bold text-lg md:text-xl whitespace-nowrap transition-colors relative py-2',
            activeTab === 'products' ? 'text-custom-blue' : 'text-custom-grey hover:text-gray-700'
          ]"
          @click="activeTab = 'products'"
        >
          Produk
          <div
            v-if="activeTab === 'products'"
            class="absolute bottom-[-9px] left-0 w-full h-[3px] bg-custom-blue rounded-t-full"
          ></div>
        </button>
        <button
          :class="[
            'font-bold text-lg md:text-xl whitespace-nowrap transition-colors relative py-2',
            activeTab === 'reviews' ? 'text-custom-blue' : 'text-custom-grey hover:text-gray-700'
          ]"
          @click="activeTab = 'reviews'"
        >
          Ulasan
          <div
            v-if="activeTab === 'reviews'"
            class="absolute bottom-[-9px] left-0 w-full h-[3px] bg-custom-blue rounded-t-full"
          ></div>
        </button>
      </div>

      <div id="Tab-Contents" class="min-h-[500px]">
        <!-- Home Tab -->
        <section v-if="activeTab === 'home'" class="flex flex-col gap-6">
          <h2 class="font-bold text-2xl">Featured Products</h2>
          <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3 md:gap-6">
            <template v-if="loadingProducts">
              <SkeletonProductCard v-for="i in 5" :key="i" />
            </template>
            <template v-else>
              <ProductCard
                v-for="product in products.slice(0, 5)"
                :key="product.id"
                :item="product"
              />
            </template>
          </div>
        </section>

        <!-- Products Tab (With Sidebar) -->
        <section v-if="activeTab === 'products'" class="flex flex-col md:flex-row gap-8">
          <!-- Sidebar Filter (Desktop) -->
          <aside class="hidden md:flex flex-col w-64 shrink-0 gap-6">
            <div
              class="rounded-2xl border border-custom-stroke bg-white p-5 flex flex-col gap-4 sticky top-24"
            >
              <h3 class="font-bold text-lg">Etalase Toko</h3>
              <div class="flex flex-col gap-1">
                <button
                  :class="[
                    'text-left px-3 py-2 rounded-lg font-medium transition-colors',
                    !selectedCategory
                      ? 'bg-blue-50 text-custom-blue font-semibold'
                      : 'text-custom-grey hover:bg-gray-50 hover:text-custom-black'
                  ]"
                  @click="handleCategoryFilter(null)"
                >
                  Semua Produk
                </button>
                <hr class="my-2 border-gray-100" />
                <button
                  v-for="category in storeCategories"
                  :key="category.id"
                  :class="[
                    'text-left px-3 py-2 rounded-lg font-medium transition-colors',
                    selectedCategory?.id === category.id
                      ? 'bg-blue-50 text-custom-blue font-semibold'
                      : 'text-custom-grey hover:bg-gray-50 hover:text-custom-black'
                  ]"
                  @click="handleCategoryFilter(category)"
                >
                  {{ category.name }}
                  <span class="text-xs text-gray-400 ml-1">({{ category.products_count }})</span>
                </button>
              </div>
            </div>
          </aside>

          <!-- Product Grid & Sorting -->
          <div class="flex flex-col w-full gap-6">
            <!-- Mobile Filter (Horizontal) -->
            <div class="md:hidden flex overflow-x-auto pb-2 -mx-4 px-4 gap-2 hide-scrollbar">
              <button
                :class="[
                  'whitespace-nowrap px-4 py-2 rounded-full border text-sm transition-colors',
                  !selectedCategory
                    ? 'border-custom-blue bg-blue-50 text-custom-blue font-semibold'
                    : 'border-custom-stroke bg-white text-custom-grey font-medium'
                ]"
                @click="handleCategoryFilter(null)"
              >
                Semua
              </button>
              <button
                v-for="category in storeCategories"
                :key="category.id"
                :class="[
                  'whitespace-nowrap px-4 py-2 rounded-full border text-sm transition-colors',
                  selectedCategory?.id === category.id
                    ? 'border-custom-blue bg-blue-50 text-custom-blue font-semibold'
                    : 'border-custom-stroke bg-white text-custom-grey font-medium'
                ]"
                @click="handleCategoryFilter(category)"
              >
                {{ category.name }}
              </button>
            </div>

            <!-- Sorting -->
            <div class="flex items-center justify-between">
              <p class="font-semibold text-custom-grey">
                <span class="text-custom-black font-bold">{{ products.length }}</span> Produk
              </p>
              <div class="flex items-center gap-2">
                <span class="text-sm text-custom-grey font-medium">Urutkan:</span>
                <select
                  v-model="selectedSort"
                  class="border-none bg-transparent font-bold text-custom-black focus:ring-0 cursor-pointer text-sm"
                  @change="handleSortChange"
                >
                  <option value="default">Paling Sesuai</option>
                  <option value="newest">Terbaru</option>
                  <option value="price_low">Harga Terendah</option>
                  <option value="price_high">Harga Tertinggi</option>
                  <option value="sold">Terlaris</option>
                </select>
              </div>
            </div>

            <!-- Products Grid -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              <template v-if="loadingProducts">
                <SkeletonProductCard v-for="n in 8" :key="n" />
              </template>
              <ProductCard v-for="product in products" v-else :key="product.id" :item="product" />
              <div
                v-if="!loadingProducts && products.length === 0"
                class="col-span-full py-12 flex flex-col items-center justify-center text-center"
              >
                <img
                  src="@/assets/images/icons/box-2-grey.svg"
                  class="w-24 h-24 mb-4 opacity-50"
                  alt="Empty"
                />
                <h3 class="font-bold text-gray-400 text-lg">Belum ada produk</h3>
                <p class="text-sm text-gray-400">Toko ini belum memiliki produk di etalase ini.</p>
              </div>
            </div>
          </div>
        </section>

        <!-- Reviews Tab -->
        <section v-if="activeTab === 'reviews'" class="flex flex-col gap-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <ReviewCard v-for="review in reviews" :key="review.id" :review="review" />

            <div
              v-if="reviews.length === 0"
              class="col-span-full py-12 flex flex-col items-center justify-center text-center"
            >
              <img
                src="@/assets/images/icons/message-text-grey.svg"
                class="w-24 h-24 mb-4 opacity-50"
                alt="Empty"
              />
              <h3 class="font-bold text-gray-400 text-lg">Belum ada ulasan</h3>
              <p class="text-sm text-gray-400">Toko ini belum memiliki ulasan dari pembeli.</p>
            </div>
          </div>
        </section>
      </div>
    </section>
  </main>
</template>
