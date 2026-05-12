<script setup>
import { formatRupiah } from '@/helpers/format'
import { useWishlistStore } from '@/stores/wishlist'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import { computed } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
  item: {
    type: Object,
    required: true
  }
})

const router = useRouter()
const authStore = useAuthStore()
const wishlistStore = useWishlistStore()
const { wishlistIds } = storeToRefs(wishlistStore)
const { toggleWishlist } = wishlistStore

const isInWishlist = computed(() => {
  if (wishlistIds.value && props.item) {
    return wishlistIds.value.includes(props.item.id)
  }
  return false
})

const handleToggleWishlist = async () => {
  if (!authStore.token) {
    router.push({ name: 'auth.login' })
    return
  }
  await toggleWishlist(props.item)
}
</script>

<template>
  <RouterLink
    :to="{ name: 'app.product-detail', params: { slug: item.slug } }"
    class="group flex flex-col bg-white dark:bg-surface-card rounded-2xl overflow-hidden border border-gray-100 dark:border-white/5 hover:shadow-xl hover:shadow-blue-500/5 hover:-translate-y-1 transition-all duration-300 ease-out h-full relative"
  >
    <!-- Wishlist Button -->
    <button
      class="absolute top-2 right-2 z-20 flex size-7 items-center justify-center rounded-full bg-white/80 dark:bg-black/40 backdrop-blur-sm shadow-sm hover:bg-white dark:hover:bg-black/60 transition-all opacity-0 group-hover:opacity-100 focus:opacity-100 active:scale-90"
      @click.prevent="handleToggleWishlist"
    >
      <svg v-if="!isInWishlist" class="size-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
      </svg>
      <svg v-else class="size-4 text-red-500" fill="currentColor" viewBox="0 0 24 24">
        <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
      </svg>
    </button>

    <!-- Image -->
    <div class="aspect-square w-full bg-gray-50 dark:bg-white/5 overflow-hidden relative">
      <img
        :src="item.thumbnail"
        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
        loading="lazy"
        :alt="item.name"
      />
      <!-- Stock habis overlay -->
      <div
        v-if="item.stock <= 0"
        class="absolute inset-0 bg-white/70 dark:bg-black/60 flex items-center justify-center"
      >
        <span class="text-xs font-bold text-gray-600 dark:text-gray-300 bg-white dark:bg-surface-card px-3 py-1 rounded-full border">Stok Habis</span>
      </div>
    </div>

    <!-- Content -->
    <div class="flex flex-col p-3 gap-1.5 flex-1">
      <!-- Product Name -->
      <h3 class="text-sm font-medium text-gray-800 dark:text-gray-200 leading-snug line-clamp-2 min-h-[2.5rem]">
        {{ item.name }}
      </h3>

      <!-- Price -->
      <p class="font-bold text-base text-custom-blue dark:text-blue-400">
        Rp {{ formatRupiah(item.price) }}
      </p>

      <!-- Meta: Rating + Sold -->
      <div class="flex items-center gap-2 mt-auto pt-1.5">
        <div class="flex items-center gap-0.5">
          <svg class="size-3.5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
          </svg>
          <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">4.9</span>
        </div>
        <span class="text-xs text-gray-400 dark:text-gray-500">|</span>
        <span class="text-xs text-gray-500 dark:text-gray-400">{{ item.total_sold || 0 }} terjual</span>
      </div>

      <!-- Store info -->
      <div class="flex items-center gap-1 pt-1 border-t border-gray-50 dark:border-white/5 mt-1">
        <svg class="size-3 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-xs text-gray-400 dark:text-gray-500 truncate">{{ item?.store?.name || 'Store' }}</span>
      </div>
    </div>
  </RouterLink>
</template>
