<script setup>
import ProductCard from '@/components/card/ProductCard.vue';
import { formatRupiah, formatDate } from '@/helpers/format';
import { useProductStore } from '@/stores/product';
import { storeToRefs } from 'pinia';
import SectionHeader from '@/components/Molecule/SectionHeader.vue';
import ProductGallery from '@/components/Molecule/ProductGallery.vue';
import StarPointy from '@/assets/images/icons/Star-pointy.svg';
import StarPointyOutline from '@/assets/images/icons/Star-pointy-outline.svg';
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useWishlistStore } from '@/stores/wishlist';
import { useCartStore } from '@/stores/cart';
import { useAuthStore } from '@/stores/auth';
import { useHead } from '@vueuse/head';
import { useToast } from "vue-toastification";

const route = useRoute()
const router = useRouter()
const toast = useToast()

const product = ref({})

useHead({
    title: computed(() => product.value?.name ? `${product.value.name} | Blukios` : 'Product Detail | Blukios'),
    meta: [
        {
            name: 'description',
            content: computed(() => product.value?.description?.slice(0, 160) || 'Buy this amazing product on Blukios')
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
            content: computed(() => product.value?.product_images?.find(img => img.is_thumbnail)?.image || '')
        }
    ]
})

const productStore = useProductStore()
const { products, loading } = storeToRefs(productStore)
const { fetchProductBySlug, fetchProducts } = productStore


const authStore = useAuthStore()
const cart = useCartStore()
const wishlistStore = useWishlistStore()
const { toggleWishlist, fetchWishlist } = wishlistStore

const quantity = ref(1)
const selectedOptions = ref({})

const uniqueAttributes = computed(() => {
    const variants = product.value?.variants || []
    if (variants.length === 0) return {}

    const attributes = {}
    variants.forEach(variant => {
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
    Object.keys(attributes).forEach(key => {
        result[key] = Array.from(attributes[key])
    })
    return result
})

const selectedVariant = ref(null)

// Sync selectedVariant when attributes change
watch(selectedOptions, (newOptions) => {
    const variants = product.value?.variants || []
    if (Object.keys(newOptions).length > 0) {
        const found = variants.find(variant => {
            const attrs = variant.variant_attributes || {}
            return Object.entries(newOptions).every(([key, value]) => attrs[key] === value)
        })
        if (found) selectedVariant.value = found
    }
}, { deep: true })

// Watch for product changes to auto-select first variant options
watch(() => product.value, (newProduct) => {
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
}, { immediate: true })

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

// Helper to check if an option value is available given other CURRENT selections
const isOptionAvailable = (key, value) => {
    // If no other options selected, everything is available matches (roughly)
    // But we should correct logic:
    // We want to see if there is ANY variant that has { ...selectedOptions, [key]: value }
    // IGNORING the current value of [key] in selectedOptions (because we are testing switching to 'value')

    // Create a candidate criteria based on current selections, but override the key we are checking
    const criteria = { ...selectedOptions.value, [key]: value }

    // Remove null/undefined values from criteria just in case
    Object.keys(criteria).forEach(k => {
        if (!criteria[k]) delete criteria[k]
    })

    // Check if any variant matches this criteria subset
    return product.value?.variants?.some(variant => {
        const attrs = variant.variant_attributes || {}
        // Check if variant has ALL keys from criteria matching
        return Object.entries(criteria).every(([cKey, cVal]) => attrs[cKey] === cVal)
    })
}

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
    const exists = product.value?.variants?.some(variant => {
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
watch(() => product.value, (newProduct) => {
    if (newProduct?.variants?.length > 0) {
        // Auto select the first variant's attributes by default
        const firstVariant = newProduct.variants[0]
        if (firstVariant.variant_attributes) {
            selectedOptions.value = { ...firstVariant.variant_attributes }
        }
    } else {
        selectedOptions.value = {}
    }
}, { immediate: true })

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
    return product.value.product_reviews.filter(r => Math.round(Number(r.rating)) === star).length
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
    await toggleWishlist(product.value.id)
}

const fetchProduct = async () => {
    const response = await fetchProductBySlug(route.params.slug)

    product.value = response

    product.value.product_images.sort((a, b) => {
        return (b.is_thumbnail === true) - (a.is_thumbnail === true)
    })
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
    const storeCart = cart.carts.find(s => s.storeId === storeId)
    if (storeCart) {
        const existingProduct = storeCart.products.find(p => {
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
        toast.error(`Stok tidak cukup. Anda punya ${currentInCart} di keranjang. Maksimal stok ${stock}.`)
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

onMounted(() => {
    fetchProduct()
    fetchProducts({
        limit: 4,
        random: true
    })
    if (authStore.user) {
        fetchWishlist()
    }
})

watch(
    () => route.params.slug,
    () => {
        fetchProduct()
        fetchProducts({
            limit: 4,
            random: true
        })
    }
)
</script>

<template>
    <header class="w-full max-w-[1920px] mx-auto overflow-hidden bg-custom-background">
        <div class="flex flex-col w-full max-w-[1280px] py-4 md:py-6 px-4 md:px-[52px] gap-3 mx-auto">
            <div class="flex items-center gap-3">
                <RouterLink :to="{ name: 'app.home' }"
                    class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue">
                    Homepage
                </RouterLink>
                <span class="font-medium text-xl text-custom-grey">/</span>
                <RouterLink :to="{ name: 'app.browse-category', params: { slug: product?.product_category?.slug } }"
                    class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue">
                    {{ product?.product_category?.name }}
                </RouterLink>
                <span class="font-medium text-xl text-custom-grey">/</span>
                <a href="#" class="font-medium text-lg text-custom-grey last:font-semibold last:text-custom-blue">
                    Product Details
                </a>
            </div>
        </div>
    </header>
    <main class="w-full max-w-[1280px] px-4 md:px-[52px] mt-8 mb-[100px] mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Left Column: Gallery (Span 4) -->
            <div class="lg:col-span-4">
                <ProductGallery :images="product?.product_images || []" />
            </div>

            <!-- Center Column: Info & Content (Span 5) -->
            <div class="lg:col-span-5 flex flex-col gap-6">
                <!-- Title & Stats -->
                <div>
                    <h1 class="font-bold text-xl md:text-2xl leading-snug mb-2">{{ product?.name }}</h1>
                    <div class="flex items-center gap-3 text-sm">
                        <div class="flex items-center gap-1">
                            <span class="font-bold text-custom-black">Terjual {{ product?.total_sold || 0 }}</span>
                            <span class="text-custom-grey">•</span>
                            <div class="flex items-center gap-1 border border-custom-stroke rounded px-1.5 py-0.5">
                                <img src="@/assets/images/icons/Star-pointy.svg" class="size-3.5" />
                                <span class="font-bold text-custom-black">{{ averageRating }}</span>
                                <span class="text-custom-grey">({{ product?.product_reviews?.length || 0 }})</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-3xl font-bold text-custom-black mt-4">
                        Rp {{ formatRupiah(displayedPrice) }}
                    </div>

                    <!-- Variant Options -->
                    <div v-if="product?.variants?.length > 0" class="flex flex-col gap-4 mt-6">
                        <div v-for="(values, key) in uniqueAttributes" :key="key">
                            <h3 class="font-bold text-base mb-2 capitalize">{{ key }}</h3>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="value in values" :key="value" @click="selectOption(key, value)"
                                    class="px-4 py-2 rounded-lg text-sm font-semibold border transition-all" :class="selectedOptions[key] === value
                                        ? 'bg-custom-black text-white border-custom-black'
                                        : 'bg-white text-custom-black border-gray-200 hover:border-custom-black'">
                                    {{ value }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="border-custom-stroke" />

                <!-- Tabs Navigation (Sticky) -->
                <div
                    class="sticky-tabs flex items-center gap-6 overflow-x-auto hide-scrollbar bg-white -mx-4 px-4 md:mx-0 md:px-0">
                    <a href="#Detail" class="tab-link active">Detail</a>
                    <a href="#Reviews" class="tab-link">Ulasan</a>
                    <a href="#Recommendation" class="tab-link">Rekomendasi</a>
                </div>

                <!-- Detail Section -->
                <div id="Detail" class="flex flex-col gap-4 scroll-mt-[180px]">
                    <h3 class="font-bold text-lg">Detail Produk</h3>
                    <div class="flex flex-col gap-3 text-sm text-custom-grey">
                        <div class="flex">
                            <span class="w-32 shrink-0">Kondisi</span>
                            <span class="font-medium text-custom-black">{{ product?.condition }}</span>
                        </div>
                        <div class="flex">
                            <span class="w-32 shrink-0">Berat Satuan</span>
                            <span class="font-medium text-custom-black">{{ product?.weight }} kg</span>
                        </div>
                        <div class="flex">
                            <span class="w-32 shrink-0">Kategori</span>
                            <span class="font-medium text-custom-blue">{{ product?.product_category?.name }}</span>
                        </div>
                        <div class="flex">
                            <span class="w-32 shrink-0">Etalase</span>
                            <span class="font-medium text-custom-blue">Semua Etalase</span>
                        </div>
                    </div>

                    <div class="whitespace-pre-wrap text-custom-black leading-relaxed text-sm mt-2">
                        {{ product?.description }}
                    </div>
                </div>

                <hr class="border-custom-stroke" />

                <!-- Store Info -->
                <div class="flex items-center gap-3">
                    <div class="size-12 rounded-full overflow-hidden bg-gray-100 shrink-0">
                        <img :src="product?.store?.logo" class="size-full object-cover" />
                    </div>
                    <div class="flex flex-col">
                        <div class="flex items-center gap-1">
                            <span class="font-bold text-base">{{ product?.store?.name }}</span>
                            <img src="@/assets/images/icons/verify-star.svg" class="size-4" />
                        </div>
                        <span class="text-xs text-custom-green font-bold">Online</span>
                    </div>
                    <RouterLink v-if="product?.store?.username"
                        :to="{ name: 'app.store-detail', params: { username: product?.store?.username } }"
                        class="ml-auto px-4 py-1.5 border border-custom-blue text-custom-blue rounded-lg text-sm font-bold hover:bg-blue-50">
                        Follow
                    </RouterLink>
                </div>

                <hr class="border-custom-stroke" />

                <!-- Reviews Section -->
                <div id="Reviews" class="flex flex-col gap-4 scroll-mt-[180px]">
                    <h3 class="font-bold text-lg">Ulasan Pembeli</h3>
                    <div class="flex flex-col md:flex-row items-center gap-10">
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center gap-2">
                                <img src="@/assets/images/icons/Star-pointy.svg" class="size-8" />
                                <span class="text-4xl font-bold">{{ averageRating }}</span>
                                <span class="text-custom-grey text-sm mb-[-5px]">/ 5.0</span>
                            </div>
                            <span class="text-sm font-semibold text-custom-black">{{ product?.product_reviews?.length ||
                                0 }} Ulasan</span>
                        </div>

                        <div class="flex flex-col gap-1 flex-1 w-full max-w-sm">
                            <div v-for="star in 5" :key="star" class="flex items-center gap-2">
                                <img src="@/assets/images/icons/Star-pointy.svg" class="size-4" />
                                <span class="text-sm font-bold w-3 text-custom-grey">{{ 6 - star }}</span>
                                <div class="h-2 flex-1 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-custom-green rounded-full"
                                        :style="{ width: `${getRatingPercentage(6 - star)}%` }"></div>
                                </div>
                                <span class="text-xs text-custom-grey w-8 text-right">{{ getRatingCount(6 - star)
                                    }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Review List -->
                    <div class="flex flex-col gap-6 mt-4">
                        <div v-if="!product?.product_reviews || product.product_reviews.length === 0"
                            class="text-center py-8 text-custom-grey">
                            Belum ada ulasan untuk produk ini.
                        </div>
                        <div v-else class="flex flex-col gap-6">
                            <div v-for="review in product.product_reviews" :key="review.id"
                                class="flex gap-4 border-b border-gray-100 pb-6 last:border-0">
                                <!-- User Avatar -->
                                <div class="w-10 h-10 rounded-full bg-gray-100 overflow-hidden flex-shrink-0">
                                    <img v-if="!review.is_anonymous && review.user?.profile_picture"
                                        :src="review.user.profile_picture" class="w-full h-full object-cover">
                                    <div v-else
                                        class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-500 font-bold text-xs uppercase">
                                        {{ review.is_anonymous ? 'A' : (review.user?.name?.[0] || 'U') }}
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2 flex-1">
                                    <!-- Header: Name & Date -->
                                    <div class="flex justify-between items-start">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-sm text-custom-black">
                                                {{ review.is_anonymous ? 'Anonim' : review.user?.name }}
                                            </span>
                                            <div class="flex items-center gap-1">
                                                <div class="flex">
                                                    <img v-for="i in 5" :key="i"
                                                        :src="i <= review.rating ? StarPointy : StarPointyOutline"
                                                        class="w-3.5 h-3.5" />
                                                </div>
                                                <span class="text-xs text-gray-400 ml-2">{{
                                                    formatDate(review.created_at) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Content -->
                                    <p class="text-sm text-gray-700 leading-relaxed">{{ review.review }}</p>

                                    <!-- Media Attachments -->
                                    <div v-if="review.attachments && review.attachments.length > 0"
                                        class="flex gap-2 mt-2 overflow-x-auto pb-2">
                                        <div v-for="media in review.attachments" :key="media.id"
                                            class="w-20 h-20 rounded-lg overflow-hidden flex-shrink-0 bg-gray-100 border border-gray-200">
                                            <img v-if="media.file_type === 'image'" :src="media.file_path"
                                                class="w-full h-full object-cover cursor-pointer hover:opacity-90">
                                            <video v-else :src="media.file_path"
                                                class="w-full h-full object-cover"></video>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Sticky Action Card (Span 3) -->
            <div class="lg:col-span-3">
                <div
                    class="sticky top-[120px] bg-white rounded-2xl p-5 shadow-[0_8px_30px_rgb(0,0,0,0.06)] border border-gray-100 flex flex-col gap-5">
                    <h3 class="font-bold text-base">Atur jumlah dan catatan</h3>

                    <div class="flex items-center gap-3 my-2">
                        <div class="size-12 rounded bg-gray-100 overflow-hidden shrink-0">
                            <img :src="product?.product_images?.find(img => img.is_thumbnail)?.image"
                                class="size-full object-cover" />
                        </div>
                        <p class="line-clamp-2 text-sm">{{ product?.name }}</p>
                    </div>

                    <hr class="border-custom-stroke" />

                    <!-- Variant Selector -->
                    <!-- Multi-Dimensional Variant Selector -->
                    <!-- Selected Variant Display (Read Only) -->
                    <div v-if="product?.has_variants && Object.keys(selectedOptions).length > 0"
                        class="flex flex-col gap-1">
                        <span class="text-sm font-semibold text-custom-black">Varian Dipilih:</span>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="(value, key) in selectedOptions" :key="key"
                                class="px-2 py-1 bg-gray-100 rounded text-xs font-medium text-custom-black border border-gray-200 capitalize">
                                {{ key }}: {{ value }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 border border-custom-stroke rounded-lg p-1 w-fit">
                        <button type="button" @click="decrease" :disabled="quantity <= 1"
                            class="size-7 flex items-center justify-center text-custom-blue hover:bg-gray-50 rounded disabled:text-gray-300">
                            <i class="fa-solid fa-minus text-xs"></i>
                        </button>
                        <input type="number" v-model="quantity" readonly
                            class="w-10 text-center font-bold text-sm outline-none" />
                        <button type="button" @click="increase" :disabled="quantity >= (displayedStock || 0)"
                            class="size-7 flex items-center justify-center text-custom-blue hover:bg-gray-50 rounded disabled:text-gray-300">
                            <i class="fa-solid fa-plus text-xs"></i>
                        </button>
                    </div>
                    <p class="text-xs text-custom-orange" v-if="(displayedStock || 0) < 5">Sisa {{ displayedStock }}
                        buah lagi!
                    </p>
                    <p class="text-xs text-custom-grey" v-else>Stok Total: {{ displayedStock || 0 }}</p>

                    <div class="flex items-center justify-between mt-2">
                        <span class="text-custom-grey">Subtotal</span>
                        <span class="font-bold text-lg">Rp {{ formatRupiah((displayedPrice || 0) * quantity) }}</span>
                    </div>

                    <div class="flex flex-col gap-2 mt-2">
                        <button @click.prevent="addToCart" :disabled="!displayedStock || displayedStock <= 0"
                            class="w-full py-3 bg-custom-blue text-white rounded-lg font-bold hover:bg-blue-600 disabled:bg-gray-300 transition-colors flex items-center justify-center gap-2">
                            <i class="fa-solid fa-plus"></i> Keranjang
                        </button>
                        <button
                            class="w-full py-3 border border-custom-blue text-custom-blue rounded-lg font-bold hover:bg-blue-50 transition-colors">
                            Beli Langsung
                        </button>
                    </div>

                    <div class="flex items-center justify-center gap-4 mt-2">
                        <button @click="handleToggleWishlist"
                            class="flex items-center gap-2 text-sm font-bold text-custom-grey hover:text-custom-red transition-colors">
                            <i class="fa-solid fa-heart" :class="isInWishlist ? 'text-custom-red' : ''"></i> Wishlist
                        </button>
                        <button
                            class="flex items-center gap-2 text-sm font-bold text-custom-grey hover:text-custom-black transition-colors">
                            <i class="fa-solid fa-share-nodes"></i> Share
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Sticky Bottom Bar -->
        <div
            class="fixed bottom-0 left-0 w-full z-50 bg-white border-t border-custom-stroke p-4 md:hidden shadow-[0_-4px_20px_-4px_rgba(0,0,0,0.1)] flex gap-3 items-center">
            <button @click="handleToggleWishlist"
                class="flex items-center justify-center size-14 rounded-2xl border border-custom-stroke grow-0 shrink-0"
                :class="{ 'bg-custom-red/10 border-custom-red': isInWishlist }">
                <img v-if="isInWishlist" src="@/assets/images/icons/heart-red.svg" class="size-6 shrink-0" alt="icon" />
                <img v-else src="@/assets/images/icons/heart-grey.svg" class="size-6 shrink-0" alt="icon" />
            </button>
            <button @click.prevent="addToCart" :disabled="!product?.stock || product?.stock <= 0"
                class="flex items-center justify-center h-14 w-full rounded-2xl p-4 gap-2 bg-custom-blue disabled:bg-custom-grey disabled:cursor-not-allowed grow">
                <img src="@/assets/images/icons/shopping-cart-white.svg" class="flex size-6 shrink-0" alt="icon" />
                <span class="font-bold text-white">
                    {{ !product?.stock || product?.stock <= 0 ? 'Out of Stock' : 'Add to Cart' }} </span>
            </button>
        </div>

        <section id="Recommendation" class="flex flex-col gap-9 scroll-mt-[150px]">
            <SectionHeader title="Shop Quality Picks" subtitle="from Top Sellers"
                :link="{ name: 'app.all-products' }" />
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 md:gap-6">
                <ProductCard v-for="product in products" :key="product.id" :item="product" v-if="!loading" />
            </div>
        </section>
    </main>
</template>