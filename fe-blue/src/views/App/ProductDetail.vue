<script setup>
import ProductCard from '@/components/card/ProductCard.vue'
import { formatRupiah, formatDate } from '@/helpers/format'
import { useProductStore } from '@/stores/product'
import { storeToRefs } from 'pinia'
import SectionHeader from '@/components/Molecule/SectionHeader.vue'
import ProductGallery from '@/components/Molecule/ProductGallery.vue'
import ProductSpecs from '@/components/Molecule/ProductSpecs.vue'
import Container from '@/components/Molecule/Container.vue'
import StarPointy from '@/assets/images/icons/Star-pointy.svg'
import StarPointyOutline from '@/assets/images/icons/Star-pointy-outline.svg'
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useWishlistStore } from '@/stores/wishlist'
import { useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import { axiosInstance } from '@/plugins/axios'
import { useHead } from '@vueuse/head'
import { useToast } from 'vue-toastification'
import { logger } from '@/utils/logger'
import { useRecommendationStore } from '@/stores/recommendation'
import { getGuestSessionId } from '@/utils/guestSession'
import SkeletonProductCard from '@/components/skeleton/SkeletonProductCard.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const product = ref({})

useHead({
  title: computed(() =>
    product.value?.name ? `${product.value.name} | Blukios` : 'Product Detail | Blukios'
  ),
  meta: [
    {
      name: 'description',
      content: computed(
        () => product.value?.description?.slice(0, 160) || 'Buy this amazing product on Blukios'
      )
    },
    {
      property: 'og:title',
      content: computed(() => product.value?.name || 'Product Detail')
    },
    {
      property: 'og:description',
      content: computed(() => product.value?.description?.slice(0, 160) || '')
    },
    {
      property: 'og:image',
      content: computed(
        () => product.value?.product_images?.find((img) => img.is_thumbnail)?.image || ''
      )
    }
  ]
})

const productStore = useProductStore()
const { fetchProductBySlug } = productStore

const recommendationStore = useRecommendationStore()
const { similarProducts, loadingSimilar } = storeToRefs(recommendationStore)
const { fetchSimilarProducts, trackProductView } = recommendationStore

const authStore = useAuthStore()
const cart = useCartStore()
const wishlistStore = useWishlistStore()
const { toggleWishlist, fetchWishlist } = wishlistStore

const quantity = ref(1)
const selectedOptions = ref({})
const showVariantDrawer = ref(false)
const drawerAction = ref('cart') // 'cart' | 'buy'
const isFollowing = ref(false)
const followLoading = ref(false)

const handleDrawerAction = () => {
  if (drawerAction.value === 'cart') {
    addToCart()
  } else {
    handleBuyNow()
  }
  showVariantDrawer.value = false
}

const uniqueAttributes = computed(() => {
  const variants = product.value?.variants || []
  if (variants.length === 0) return {}

  const attributes = {}
  variants.forEach((variant) => {
    if (variant.variant_attributes) {
      Object.entries(variant.variant_attributes).forEach(([key, value]) => {
        if (!attributes[key]) {
          attributes[key] = new Set()
        }
        attributes[key].add(value)
      })
    }
  })

  // Convert Sets to Arrays
  const result = {}
  Object.keys(attributes).forEach((key) => {
    result[key] = Array.from(attributes[key])
  })
  return result
})

const selectedVariant = ref(null)

// Sync selectedVariant when attributes change
watch(
  selectedOptions,
  (newOptions) => {
    const variants = product.value?.variants || []
    if (Object.keys(newOptions).length > 0) {
      const found = variants.find((variant) => {
        const attrs = variant.variant_attributes || {}
        return Object.entries(newOptions).every(([key, value]) => attrs[key] === value)
      })
      if (found) selectedVariant.value = found
    }
  },
  { deep: true }
)

// Watch for product changes to auto-select first variant options
watch(
  () => product.value,
  (newProduct) => {
    if (newProduct?.variants?.length > 0) {
      const firstVariant = newProduct.variants[0]
      selectedVariant.value = firstVariant // Set initial reference

      if (firstVariant.variant_attributes) {
        selectedOptions.value = { ...firstVariant.variant_attributes }
      }
    } else {
      selectedOptions.value = {}
      selectedVariant.value = null
    }
  },
  { immediate: true }
)

const allVariantsSelected = computed(() => {
  if (!product.value?.variants?.length) return true
  const keys = Object.keys(uniqueAttributes.value)
  return keys.length > 0 && keys.every((key) => selectedOptions.value[key] !== undefined)
})

const displayedPrice = computed(() => {
  if (selectedVariant.value) {
    return selectedVariant.value.price
  }
  return product.value?.price
})

const displayedStock = computed(() => {
  if (selectedVariant.value) {
    return selectedVariant.value.stock
  }
  return product.value?.stock
})

const selectOption = (key, value) => {
  // 1. Set the new value
  // If clicking same value, maybe toggle off? optional. Let's keep it simple: always select.
  // Actually standard e-commerce: clicking active doesn't deselect, but changing value does.

  const newOptions = { ...selectedOptions.value, [key]: value }

  // 2. Cleanup invalid combinations
  // If I changed 'Color' to 'Blue', but current 'RAM' is '8GB' and Blue-8GB doesn't exist,
  // I should clear 'RAM'.

  // Logic: Validate the NEW full set. If no variant exists for the NEW full set,
  // we assume the JUST CLICKED item is the 'truth', and we drop others that conflict.

  // Check if a variant exists for the new COMPLETE set (assuming keys match what we have so far)
  const exists = product.value?.variants?.some((variant) => {
    const attrs = variant.variant_attributes || {}
    // Check if variant matches fully the newOptions (for the keys that exist in newOptions)
    return Object.entries(newOptions).every(([k, v]) => attrs[k] === v)
  })

  if (!exists) {
    // If the combination doesn't exist, we keep the KEY we just changed ([key]: value)
    // and try to find which OTHER key is causing the conflict.
    // Simple approach: Keep the new one, reset others that conflict.
    // Bruteforce: Reset everything else? No, that's annoying.
    // Smart approach: Reset only keys that make it invalid.

    // Let's just reset all OTHER keys for now to ensure user flows "First Color, Then RAM".
    // Or better: Just set selectedOptions to { [key]: value }.
    // This forces user to re-pick strictly available options.
    // It mimics "First select Variant 1".

    // However, if I have 3 attributes, clearing all 2 others might be annoying.
    // But preventing "Invalid State" is priority.

    // Let's try: Keep the new setting, drop others.
    selectedOptions.value = { [key]: value }
  } else {
    selectedOptions.value = newOptions
  }
}

// Watch for product changes to auto-select first variant options
watch(
  () => product.value,
  (newProduct) => {
    if (newProduct?.variants?.length > 0) {
      // Auto select the first variant's attributes by default
      const firstVariant = newProduct.variants[0]
      if (firstVariant.variant_attributes) {
        selectedOptions.value = { ...firstVariant.variant_attributes }
      }
    } else {
      selectedOptions.value = {}
    }
  },
  { immediate: true }
)

const isInWishlist = computed(() => {
  return wishlistStore.isInWishlist(product.value?.id)
})

const averageRating = computed(() => {
  const reviews = product.value?.product_reviews || []
  if (reviews.length === 0) return 0
  const sum = reviews.reduce((acc, review) => acc + Number(review.rating), 0)
  return (sum / reviews.length).toFixed(1)
})

const getRatingCount = (star) => {
  if (!product.value?.product_reviews) return 0
  return product.value.product_reviews.filter((r) => Math.round(Number(r.rating)) === star).length
}

const getRatingPercentage = (star) => {
  const total = product.value?.product_reviews?.length || 0
  if (total === 0) return 0
  return (getRatingCount(star) / total) * 100
}

const handleToggleWishlist = async () => {
  if (!authStore.token) {
    router.push({ name: 'auth.login' })
    return
  }
  await toggleWishlist(product.value)
}

/**
 * Enterprise pattern: context-aware chat initiation.
 * Prevents user from chatting with their own store.
 * Deep-links directly to the specific seller conversation.
 */
const handleChatSeller = () => {
  if (!authStore.token) {
    router.push({ name: 'auth.login' })
    return
  }

  const storeOwnerId = product.value?.store?.user_id

  // Prevent seller from chatting with themselves
  if (authStore.user?.id === storeOwnerId) {
    toast.warning('Ini adalah toko Anda sendiri.')
    return
  }

  if (!storeOwnerId) {
    toast.error('Info seller tidak ditemukan.')
    return
  }

  // Deep-link to chat page with seller pre-selected via query param
  router.push({
    name: 'user.chat',
    params: { username: authStore.user.username },
    query: { userId: storeOwnerId }
  })
}

const fetchProduct = async () => {
  const response = await fetchProductBySlug(route.params.slug)

  product.value = response

  product.value.product_images.sort((a, b) => {
    return (b.is_thumbnail === true) - (a.is_thumbnail === true)
  })

  // Rekomendasi & view tracking baru bisa jalan setelah product.value.id ada
  fetchSimilarProducts(product.value.id)
  trackProductView(product.value.id, authStore.user?.id ? null : getGuestSessionId())
}

const increase = () => {
  const stock = Number(displayedStock.value || 0)
  if (quantity.value < stock) {
    quantity.value++
  } else {
    toast.error('Maksimal stok tercapai', { timeout: 2000 })
  }
}

const decrease = () => {
  if (quantity.value > 1) {
    quantity.value--
  }
}

const addToCart = () => {
  if (!authStore.token) {
    router.push({ name: 'auth.login' })
    return
  }

  const stock = displayedStock.value || 0
  const storeId = product.value?.store?.id

  // Check if product requires variants but none selected (though we auto-select, good to be safe)
  if (product.value?.has_variants && !selectedVariant.value) {
    toast.error('Pilih varian terlebih dahulu')
    return
  }

  // Find current quantity in cart
  let currentInCart = 0
  const storeCart = cart.carts.find((s) => s.storeId === storeId)
  if (storeCart) {
    const existingProduct = storeCart.products.find((p) => {
      // Match by Product ID AND Variant (if applicable)
      if (selectedVariant.value) {
        return p.id === product.value.id && p.variantId === selectedVariant.value.id
      }
      return p.id === product.value.id
    })
    if (existingProduct) {
      currentInCart = existingProduct.quantity
    }
  }

  if (currentInCart + quantity.value > stock) {
    toast.error(
      `Stok tidak cukup. Anda punya ${currentInCart} di keranjang. Maksimal stok ${stock}.`
    )
    return
  }

  const cartItem = {
    ...product.value,
    quantity: quantity.value,
    price: displayedPrice.value // Use variant price
  }

  // Append Variant Specifics
  if (selectedVariant.value) {
    cartItem.variant_id = selectedVariant.value.id
    cartItem.variant_name = selectedVariant.value.name
    cartItem.variant_attributes = selectedVariant.value.variant_attributes
  }

  cart.addToCart(cartItem)
  toast.success('Produk berhasil ditambahkan ke keranjang')
}

watch(
  () => route.params.slug,
  () => {
    fetchProduct()
  }
)

onMounted(() => {
  fetchProduct()
  if (authStore.user) {
    fetchWishlist()
  }
})

/**
 * Beli Langsung: tambah ke cart lalu langsung redirect ke cart.
 * User akan memilih checkout dari halaman cart — ini enterprise standard
 * (Tokopedia/Shopee pola: cart → checkout, bukan direct-to-payment).
 */
const handleBuyNow = () => {
  if (!authStore.token) {
    router.push({ name: 'auth.login' })
    return
  }
  addToCart()
  router.push({ name: 'app.cart' })
}

/**
 * Follow/unfollow store toggle.
 * Optimistic UI: update state dulu, rollback jika API gagal.
 */
const handleFollowStore = async () => {
  if (!authStore.token) {
    router.push({ name: 'auth.login' })
    return
  }

  const storeUsername = product.value?.store?.username
  if (!storeUsername || followLoading.value) return

  followLoading.value = true
  const prev = isFollowing.value
  isFollowing.value = !prev // Optimistic update

  try {
    await axiosInstance.post(`/store/${storeUsername}/follow`)
    toast.success(isFollowing.value ? 'Mengikuti toko ini!' : 'Berhenti mengikuti toko.')
  } catch {
    isFollowing.value = prev // Rollback on failure
    toast.error('Gagal memperbarui status follow.')
  } finally {
    followLoading.value = false
  }
}

/**
 * Share product: gunakan Web Share API jika tersedia (mobile-first),
 * fallback ke clipboard copy untuk desktop.
 */
const handleShare = async () => {
  const shareData = {
    title: product.value?.name,
    text: `Lihat produk ini di Blukios: ${product.value?.name}`,
    url: window.location.href
  }

  if (navigator.share && navigator.canShare(shareData)) {
    try {
      await navigator.share(shareData)
    } catch (err) {
      // User cancelled share — no error needed
      if (err.name !== 'AbortError') logger.error(err)
    }
  } else {
    // Fallback: copy URL to clipboard
    try {
      await navigator.clipboard.writeText(window.location.href)
      toast.success('Link produk disalin ke clipboard!')
    } catch {
      toast.error('Gagal menyalin link.')
    }
  }
}
</script>

<template>
  <header class="w-full max-w-480 mx-auto overflow-hidden bg-custom-background">
    <div class="flex flex-col w-full max-w-7xl py-4 md:py-6 px-4 md:px-13 gap-3 mx-auto">
      <div class="flex items-center gap-3">
        <RouterLink
:to="{ name: 'app.home' }"
          class="font-medium text-lg text-custom-grey last:font-medium last:text-custom-blue">
          Homepage
        </RouterLink>
        <span class="font-medium text-xl text-custom-grey">/</span>
        <RouterLink
:to="{ name: 'app.browse-category', params: { slug: product?.product_category?.slug } }"
          class="font-medium text-lg text-custom-grey last:font-medium last:text-custom-blue">
          {{ product?.product_category?.name }}
        </RouterLink>
        <span class="font-medium text-xl text-custom-grey">/</span>
        <a href="#" class="font-medium text-lg text-custom-grey last:font-medium last:text-custom-blue">
          Product Details
        </a>
      </div>
    </div>
  </header>
  <Container as="main" class="mt-8 mb-25">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
      <!-- Left Column: Gallery (Span 5, lebih besar) -->
      <div class="lg:col-span-5">
        <ProductGallery :images="product?.product_images || []" />
      </div>

      <!-- Center Column: Info & Content (Span 4) -->
      <div class="lg:col-span-4 flex flex-col gap-6">
        <!-- Title & Stats -->
        <div>
          <h1 class="font-medium text-xl md:text-2xl leading-snug mb-3">{{ product?.name }}</h1>

          <!-- Social Proof Header -->
          <div class="flex items-center gap-4 text-sm mb-4">
            <div class="flex items-center gap-1.5 pr-4 border-r border-gray-200">
              <span class="font-medium text-custom-orange text-lg">{{ averageRating }}</span>
              <div class="flex">
                <img src="@/assets/images/icons/Star-pointy.svg" class="size-4" />
              </div>
            </div>
            <div class="flex items-center gap-1.5 pr-4 border-r border-gray-200 cursor-pointer hover:text-custom-blue">
              <span class="font-medium text-custom-black underline decoration-gray-300 underline-offset-2">{{
                product?.product_reviews?.length || 0 }}</span>
              <span class="text-custom-grey">Ulasan</span>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="font-medium text-custom-black">{{ product?.total_sold || 0 }}</span>
              <span class="text-custom-grey">Terjual</span>
            </div>
          </div>

          <div class="text-3xl font-medium text-custom-black mt-2">
            Rp {{ formatRupiah(displayedPrice) }}
          </div>

          <!-- Variant Options -->
          <div v-if="product?.variants?.length > 0" class="flex flex-col gap-4 mt-6">
            <div v-for="(values, key) in uniqueAttributes" :key="key">
              <h3 class="font-medium text-base mb-2 capitalize flex items-center gap-1">
                {{ key }}
                <span v-if="!selectedOptions[key]" class="text-custom-red text-xs font-normal">(wajib dipilih)</span>
              </h3>
              <div class="flex flex-wrap gap-2">
                <button
v-for="value in values" :key="value"
                  class="px-4 py-2 rounded-lg text-sm font-medium border transition-all" :class="selectedOptions[key] === value
                    ? 'bg-custom-black dark:bg-white text-white dark:text-custom-background border-custom-black dark:border-white'
                    : 'bg-white dark:bg-transparent text-custom-black dark:text-white border-gray-200 dark:border-white/20 hover:border-custom-black dark:hover:border-white'
                    " @click="selectOption(key, value)">
                  {{ value }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <hr class="border-custom-stroke" />

        <!-- Tabs Navigation (Sticky) -->

        <!-- Detail Section -->
        <div id="Detail" class="flex flex-col gap-6 scroll-mt-45">
          <ProductSpecs :product="product" />

          <div class="flex flex-col gap-2">
            <h3 class="font-medium text-lg">Deskripsi</h3>
            <div class="whitespace-pre-wrap text-custom-black leading-relaxed text-sm">
              {{ product?.description }}
            </div>
          </div>
        </div>

        <hr class="border-custom-stroke" />

        <!-- Store Info -->
        <div class="flex items-center gap-3">
          <div class="size-12 rounded-full overflow-hidden bg-gray-100 dark:bg-white/10 shrink-0">
            <img :src="product?.store?.logo" class="size-full object-cover" />
          </div>
          <div class="flex flex-col">
            <div class="flex items-center gap-1">
              <span class="font-medium text-base">{{ product?.store?.name }}</span>
              <img src="@/assets/images/icons/verify-star.svg" class="size-4" />
            </div>
            <span class="text-xs text-custom-green font-medium">Online</span>
          </div>
          <RouterLink
v-if="product?.store?.username"
            :to="{ name: 'app.store-detail', params: { username: product?.store?.username } }"
            class="ml-auto px-4 py-1.5 border border-custom-blue text-custom-blue rounded-lg text-sm font-medium hover:bg-blue-50 transition-colors">
            Kunjungi
          </RouterLink>
          <button
            :disabled="followLoading"
            class="px-4 py-1.5 rounded-lg text-sm font-medium transition-all"
            :class="isFollowing
              ? 'bg-gray-100 dark:bg-white/10 text-custom-grey border border-custom-stroke dark:border-white/10 hover:bg-red-50 hover:text-custom-red'
              : 'border border-custom-blue text-custom-blue hover:bg-blue-50'"
            @click="handleFollowStore">
            {{ followLoading ? '...' : isFollowing ? 'Mengikuti' : 'Follow' }}
          </button>
        </div>

        <hr class="border-custom-stroke" />

        <!-- Reviews Section -->
        <div id="Reviews" class="flex flex-col gap-4 scroll-mt-45">
          <h3 class="font-medium text-lg">Ulasan Pembeli</h3>
          <div class="flex flex-col md:flex-row items-center gap-10">
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2">
                <img src="@/assets/images/icons/Star-pointy.svg" class="size-8" />
                <span class="text-4xl font-medium">{{ averageRating }}</span>
                <span class="text-custom-grey text-sm -mb-1.25">/ 5.0</span>
              </div>
              <span class="text-sm font-medium text-custom-black">{{ product?.product_reviews?.length || 0 }}
                Ulasan</span>
            </div>

            <div class="flex flex-col gap-1 flex-1 w-full max-w-sm">
              <div v-for="star in 5" :key="star" class="flex items-center gap-2">
                <img src="@/assets/images/icons/Star-pointy.svg" class="size-4" />
                <span class="text-sm font-medium w-3 text-custom-grey">{{ 6 - star }}</span>
                <div class="h-2 flex-1 bg-gray-100 rounded-full overflow-hidden">
                  <div
class="h-full bg-custom-green rounded-full"
                    :style="{ width: `${getRatingPercentage(6 - star)}%` }"></div>
                </div>
                <span class="text-xs text-custom-grey w-8 text-right">{{
                  getRatingCount(6 - star)
                }}</span>
              </div>
            </div>
          </div>

          <!-- Review List -->
          <div class="flex flex-col gap-6 mt-4">
            <div
v-if="!product?.product_reviews || product.product_reviews.length === 0"
              class="text-center py-8 text-custom-grey">
              Belum ada ulasan untuk produk ini.
            </div>
            <div v-else class="flex flex-col gap-6">
              <div
v-for="review in product.product_reviews" :key="review.id"
                class="flex gap-4 border-b border-border pb-6 last:border-0">
                <!-- User Avatar -->
                <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-white/10 overflow-hidden shrink-0">
                  <img
v-if="!review.is_anonymous && review.user?.profile_picture" :src="review.user.profile_picture"
                    class="w-full h-full object-cover" />
                  <div
v-else
                    class="w-full h-full flex items-center justify-center bg-gray-200 dark:bg-white/5 text-gray-500 dark:text-gray-400 font-medium text-xs uppercase">
                    {{ review.is_anonymous ? 'A' : review.user?.name?.[0] || 'U' }}
                  </div>
                </div>

                <div class="flex flex-col gap-2 flex-1">
                  <!-- Header: Name & Date -->
                  <div class="flex justify-between items-start">
                    <div class="flex flex-col">
                      <span class="font-medium text-sm text-custom-black">
                        {{ review.is_anonymous ? 'Anonim' : review.user?.name }}
                      </span>
                      <div class="flex items-center gap-1">
                        <div class="flex">
                          <img
v-for="i in 5" :key="i" :src="i <= review.rating ? StarPointy : StarPointyOutline"
                            class="w-3.5 h-3.5" />
                        </div>
                        <span class="text-xs text-gray-400 ml-2">{{
                          formatDate(review.created_at)
                        }}</span>
                      </div>
                    </div>
                  </div>

                  <!-- Content -->
                  <p class="text-sm text-custom-grey leading-relaxed">{{ review.review }}</p>

                  <!-- Media Attachments -->
                  <div
v-if="review.attachments && review.attachments.length > 0"
                    class="flex gap-2 mt-2 overflow-x-auto pb-2">
                    <div
v-for="media in review.attachments" :key="media.id"
                      class="w-20 h-20 rounded-lg overflow-hidden shrink-0 bg-gray-100 border border-gray-200">
                      <img
v-if="media.file_type === 'image'" :src="media.file_path"
                        class="w-full h-full object-cover cursor-pointer hover:opacity-90" />
                      <video v-else :src="media.file_path" class="w-full h-full object-cover"></video>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column: Sticky Action Card (Span 3) -->
      <div class="hidden lg:block lg:col-span-3">
        <div
          class="sticky top-40 bg-white dark:bg-surface-card rounded-2xl p-5 shadow-[0_8px_30px_rgb(0,0,0,0.06)] border border-border flex flex-col gap-5 transition-all duration-300">
          <h3 class="font-medium text-base">Atur jumlah dan catatan</h3>

          <div class="flex items-center gap-3 my-2">
            <div class="size-12 rounded bg-gray-100 dark:bg-white/10 overflow-hidden shrink-0">
              <img
:src="product?.product_images?.find((img) => img.is_thumbnail)?.image"
                class="size-full object-cover" />
            </div>
            <p class="line-clamp-2 text-sm">{{ product?.name }}</p>
          </div>

          <hr class="border-custom-stroke" />

          <!-- Variant Selector -->
          <!-- Multi-Dimensional Variant Selector -->
          <!-- Selected Variant Display (Read Only) -->
          <div v-if="product?.has_variants && Object.keys(selectedOptions).length > 0" class="flex flex-col gap-1">
            <span class="text-sm font-medium text-custom-black">Varian Dipilih:</span>
            <div class="flex flex-wrap gap-2">
              <span
v-for="(value, key) in selectedOptions" :key="key"
                class="px-2 py-1 bg-gray-100 dark:bg-white/10 rounded text-xs font-medium text-custom-black dark:text-white border border-gray-200 dark:border-white/10 capitalize">
                {{ key }}: {{ value }}
              </span>
            </div>
          </div>

          <div class="flex items-center gap-2 border border-custom-stroke rounded-lg p-1 w-fit">
            <button
type="button" :disabled="quantity <= 1"
              class="size-7 flex items-center justify-center text-custom-blue hover:bg-gray-50 rounded disabled:text-gray-300"
              @click="decrease">
              <i class="fa-solid fa-minus text-xs"></i>
            </button>
            <input v-model="quantity" type="number" readonly class="w-10 text-center font-medium text-sm outline-none" />
            <button
type="button" :disabled="quantity >= (displayedStock || 0)"
              class="size-7 flex items-center justify-center text-custom-blue hover:bg-gray-50 rounded disabled:text-gray-300"
              @click="increase">
              <i class="fa-solid fa-plus text-xs"></i>
            </button>
          </div>
          <p v-if="(displayedStock || 0) < 5" class="text-xs text-custom-orange">
            Sisa {{ displayedStock }}
            buah lagi!
          </p>
          <p v-else class="text-xs text-custom-grey">Stok Total: {{ displayedStock || 0 }}</p>

          <div class="flex items-center justify-between mt-2">
            <span class="text-custom-grey">Subtotal</span>
            <span class="font-medium text-lg">Rp {{ formatRupiah((displayedPrice || 0) * quantity) }}</span>
          </div>

          <div class="flex flex-col gap-2 mt-2">
            <p v-if="product?.variants?.length > 0 && !allVariantsSelected" class="text-xs text-custom-red font-medium -mb-1">
              Pilih semua varian terlebih dahulu
            </p>
            <button
              :disabled="!displayedStock || displayedStock <= 0 || !allVariantsSelected"
              class="w-full py-3 bg-custom-blue text-white rounded-lg font-medium hover:bg-blue-600 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors flex items-center justify-center gap-2"
              @click.prevent="addToCart">
              <i class="fa-solid fa-plus"></i> Keranjang
            </button>
            <button
              :disabled="!displayedStock || displayedStock <= 0 || !allVariantsSelected"
              class="w-full py-3 border border-custom-blue text-custom-blue rounded-lg font-medium hover:bg-blue-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              @click="handleBuyNow">
              Beli Langsung
            </button>
          </div>

          <div class="flex items-center justify-center gap-4 mt-2">
            <button
              class="flex items-center gap-2 text-sm font-medium text-custom-grey hover:text-custom-red transition-colors"
              @click="handleToggleWishlist">
              <i class="fa-solid fa-heart" :class="isInWishlist ? 'text-custom-red' : ''"></i>
              Wishlist
            </button>
            <!-- Chat Seller: enterprise pattern — deep-link to seller conversation -->
            <button
              class="flex items-center gap-2 text-sm font-medium text-custom-grey hover:text-custom-blue transition-colors"
              @click="handleChatSeller">
              <svg
xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" stroke-width="2">
                <path
stroke-linecap="round" stroke-linejoin="round"
                  d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
              </svg>
              Tanya Seller
            </button>
            <button
              class="flex items-center gap-2 text-sm font-medium text-custom-grey hover:text-custom-black transition-colors"
              @click="handleShare">
              <i class="fa-solid fa-share-nodes"></i> Share
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Mobile Sticky Bottom Bar -->
    <div
      class="fixed bottom-0 left-0 w-full z-50 bg-white border-t border-custom-stroke p-4 md:hidden shadow-[0_-4px_20px_-4px_rgba(0,0,0,0.1)] flex gap-3 items-center">
      <!-- Wishlist + Chat Icon Buttons -->
      <div class="flex flex-col gap-1 shrink-0">
        <button
          class="flex items-center justify-center size-12 rounded-xl border border-custom-stroke"
          :class="{ 'bg-custom-red/10 border-custom-red': isInWishlist }"
          @click="handleToggleWishlist">
          <img v-if="isInWishlist" src="@/assets/images/icons/heart-red.svg" class="size-5 shrink-0" alt="wishlist" />
          <img v-else src="@/assets/images/icons/heart-grey.svg" class="size-5 shrink-0" alt="wishlist" />
        </button>
        <button
          class="flex items-center justify-center size-12 rounded-xl border border-custom-stroke hover:border-custom-blue hover:bg-blue-50 transition-colors"
          @click="handleChatSeller">
          <svg
xmlns="http://www.w3.org/2000/svg" class="size-5 text-custom-grey" fill="none" viewBox="0 0 24 24"
            stroke="currentColor" stroke-width="2">
            <path
stroke-linecap="round" stroke-linejoin="round"
              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
          </svg>
        </button>
      </div>

      <!-- Action Buttons Container -->
      <div class="flex gap-3 grow h-12">
        <button
          :disabled="!displayedStock || displayedStock <= 0 || !allVariantsSelected"
          class="flex-1 rounded-xl border border-custom-blue text-custom-blue font-medium text-sm hover:bg-blue-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          @click="handleBuyNow">
          Beli Langsung
        </button>
        <button
          :disabled="!displayedStock || displayedStock <= 0 || !allVariantsSelected"
          class="flex-1 rounded-xl bg-custom-blue text-white font-medium text-sm flex items-center justify-center gap-2 disabled:bg-custom-grey disabled:cursor-not-allowed hover:bg-blue-700 transition-colors"
          @click.prevent="addToCart">
          <img src="@/assets/images/icons/shopping-cart-white.svg" class="size-5 shrink-0" alt="icon" />
          <span>+ Keranjang</span>
        </button>
      </div>
    </div>
    <section v-if="similarProducts.length || loadingSimilar" id="Recommendation" class="flex flex-col gap-9 scroll-mt-37.5">
      <SectionHeader title="Produk Serupa" subtitle="" :link="{ name: 'app.all-products' }" />
      <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 md:gap-6">
        <template v-if="loadingSimilar">
          <SkeletonProductCard v-for="i in 6" :key="i" />
        </template>
        <template v-else>
          <ProductCard v-for="recProduct in similarProducts" :key="recProduct.id" :item="recProduct" />
        </template>
      </div>
    </section>
  </Container>

  <!-- Mobile Variant Drawer (Bottom Sheet) -->
  <Transition
enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0"
    enter-to-class="opacity-100" leave-active-class="transition duration-200 ease-in" leave-from-class="opacity-100"
    leave-to-class="opacity-0">
    <div v-if="showVariantDrawer" class="fixed inset-0 z-60 bg-black/50 md:hidden" @click="showVariantDrawer = false">
    </div>
  </Transition>

  <Transition
enter-active-class="transition duration-300 ease-out" enter-from-class="translate-y-full"
    enter-to-class="translate-y-0" leave-active-class="transition duration-200 ease-in" leave-from-class="translate-y-0"
    leave-to-class="translate-y-full">
    <div
v-if="showVariantDrawer"
      class="fixed bottom-0 left-0 w-full z-70 bg-white rounded-t-2xl p-4 md:hidden flex flex-col max-h-[85vh] shadow-[0_-4px_20px_-4px_rgba(0,0,0,0.1)]">
      <!-- Handle Bar -->
      <div class="mx-auto w-12 h-1.5 bg-gray-300 rounded-full mb-4 shrink-0"></div>

      <!-- Header: Product Info -->
      <div class="flex gap-3 mb-4 shrink-0">
        <div class="size-24 rounded-lg bg-gray-100 overflow-hidden shrink-0 border border-custom-stroke">
          <img
:src="selectedVariant?.image ||
            product?.product_images?.find((img) => img.is_thumbnail)?.image
            " class="size-full object-cover" />
        </div>
        <div class="flex flex-col gap-1 items-start">
          <span class="text-custom-red font-medium text-lg">Rp {{ formatRupiah(displayedPrice) }}</span>
          <span class="text-xs text-custom-grey">Stok: {{ displayedStock || 0 }}</span>
        </div>
        <button class="ml-auto -mt-2 text-custom-grey p-2" @click="showVariantDrawer = false">
          <i class="fa-solid fa-times text-xl"></i>
        </button>
      </div>

      <hr class="border-custom-stroke mb-4 shrink-0" />

      <!-- Scrollable Content: Variants & Quantity -->
      <div class="overflow-y-auto no-scrollbar flex-1 flex flex-col gap-6 pb-20">
        <!-- Variants -->
        <div v-if="product?.variants?.length > 0" class="flex flex-col gap-4">
          <div v-for="(values, key) in uniqueAttributes" :key="key">
            <h3 class="font-medium text-sm mb-2 capitalize">{{ key }}</h3>
            <div class="flex flex-wrap gap-2">
              <button
v-for="value in values" :key="value"
                class="px-3 py-1.5 rounded-lg text-sm font-medium border transition-all" :class="selectedOptions[key] === value
                  ? 'bg-custom-black text-white border-custom-black'
                  : 'bg-white text-custom-black border-gray-200 hover:border-custom-black'
                  " @click="selectOption(key, value)">
                {{ value }}
              </button>
            </div>
          </div>
        </div>

        <!-- Quantity -->
        <div class="flex items-center justify-between border-t border-gray-100 pt-4">
          <span class="font-medium text-sm">Jumlah</span>
          <div class="flex items-center gap-3 border border-custom-stroke rounded-lg p-1">
            <button
type="button" :disabled="quantity <= 1"
              class="size-8 flex items-center justify-center text-custom-blue hover:bg-gray-50 rounded disabled:text-gray-300"
              @click="decrease">
              <i class="fa-solid fa-minus text-sm"></i>
            </button>
            <input v-model="quantity" type="number" readonly class="w-10 text-center font-medium text-sm outline-none" />
            <button
type="button" :disabled="quantity >= (displayedStock || 0)"
              class="size-8 flex items-center justify-center text-custom-blue hover:bg-gray-50 rounded disabled:text-gray-300"
              @click="increase">
              <i class="fa-solid fa-plus text-sm"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Sticky Bottom Action -->
      <div class="absolute bottom-0 left-0 w-full p-4 bg-white border-t border-custom-stroke">
        <button
:disabled="!displayedStock || displayedStock <= 0"
          class="w-full py-3 bg-custom-green text-white rounded-xl font-medium hover:bg-green-600 disabled:bg-gray-300 transition-colors shadow-lg shadow-green-100"
          @click="handleDrawerAction">
          {{ drawerAction === 'cart' ? '+ Keranjang' : 'Beli Sekarang' }}
        </button>
      </div>
    </div>
  </Transition>
</template>
