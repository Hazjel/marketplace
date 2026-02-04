<script setup>
import { formatRupiah } from '@/helpers/format'
import { useWishlistStore } from '@/stores/wishlist'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { Card, CardContent } from '@/components/ui/card'

const props = defineProps({
  item: {
    type: Object,
    required: true
  },
  compact: {
    type: Boolean,
    default: false
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
  // Pass full item object so store can add it to list immediately
  await toggleWishlist(props.item)
}
</script>

<template>
  <Card
    class="group flex flex-col overflow-hidden shadow-sm hover-glow-blue h-full cursor-pointer relative w-full rounded-2xl border-transparent">
    <!-- Wishlist Overlay -->
    <button
      class="absolute top-2 right-2 z-20 flex size-8 md:size-9 items-center justify-center rounded-full bg-black/20 hover:bg-black/30 backdrop-blur-sm transition-all duration-200 opacity-100 md:opacity-0 md:group-hover:opacity-100 focus:opacity-100 active:scale-95 touch-manipulation"
      @click.prevent="handleToggleWishlist">
      <img v-if="!isInWishlist" src="@/assets/images/icons/heart-white-fill.svg"
        class="size-5 md:size-5 transition-transform" alt="wishlist" />
      <img v-else src="@/assets/images/icons/heart-red.svg" class="size-5 md:size-5 transition-transform"
        alt="wishlist" />
    </button>

    <!-- Dynamic Badges (Optional) -->
    <div v-if="item.is_new || item.discount > 0" class="absolute top-2 left-2 z-20 flex flex-col gap-1">
      <span v-if="item.is_new"
        class="px-2 py-0.5 bg-custom-green/90 backdrop-blur-sm text-white text-xs font-bold rounded-lg uppercase tracking-wider shadow-sm">
        New
      </span>
      <span v-if="item.discount > 0"
        class="px-2 py-0.5 bg-custom-orange/90 backdrop-blur-sm text-white text-xs font-bold rounded-lg uppercase tracking-wider shadow-sm">
        {{ item.discount }}% OFF
      </span>
    </div>

    <RouterLink :to="{ name: 'app.product-detail', params: { slug: item.slug } }" class="flex flex-col h-full w-full">
      <!-- Image -->
      <div class="aspect-square md:aspect-4/3 w-full bg-gray-50 overflow-hidden relative">
        <img :src="item.thumbnail"
          class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500 will-change-transform"
          loading="lazy" alt="product" />
        <div v-if="item.stock <= 0"
          class="absolute inset-0 bg-white/60 dark:bg-surface-card/80 backdrop-blur-[2px] flex items-center justify-center z-10">
          <span
            class="text-custom-black dark:text-white font-bold text-xs uppercase tracking-widest px-3 py-1 bg-white dark:bg-surface-card border border-gray-200 dark:border-white/10 rounded-full shadow-sm">
            Habis
          </span>
        </div>
      </div>

      <!-- Content -->
      <CardContent class="flex flex-col p-3 md:p-4 gap-1 flex-1 min-w-0">
        <!-- Store -->
        <div class="flex items-center gap-1 mb-0.5">
          <img v-if="item?.store?.is_official" src="@/assets/images/icons/verify-star.svg"
            class="size-3 md:size-3.5 shrink-0 dark:brightness-0 dark:invert" alt="official" />
          <span class="text-xs uppercase font-bold text-custom-grey tracking-wider truncate flex-1 md:flex-none">
            {{ item?.store?.name || 'Store' }}
          </span>
        </div>

        <!-- Title -->
        <h3
          class="font-medium text-custom-black dark:text-white text-sm leading-snug line-clamp-2 md:mb-1 transition-colors h-[40px] md:min-h-[40px]">
          {{ item?.name }}
        </h3>

        <div class="mt-auto flex flex-col gap-0.5">
          <p class="font-bold text-sm md:text-base text-custom-black dark:text-white">
            Rp {{ formatRupiah(item?.price) }}
          </p>
          <p v-if="item.original_price && item.discount > 0"
            class="text-xs text-custom-grey line-through decoration-custom-grey/60">
            Rp {{ formatRupiah(item.original_price) }}
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs text-custom-grey mt-2">
          <div class="flex items-center gap-1 px-1.5 py-0.5 bg-gray-100 dark:bg-white/5 rounded">
            <i class="fa-solid fa-star text-custom-orange text-xs"></i>
            <span class="font-bold text-custom-black dark:text-white">4.9</span>
          </div>
          <span class="text-xs md:text-sm truncate">{{ item?.total_sold || 0 }} Sold</span>
          <span v-if="item.location" class="text-xs text-custom-grey/60 hidden md:inline-block md:ml-auto">
            • {{ item.location }}
          </span>
        </div>
      </CardContent>
    </RouterLink>
  </Card>
</template>
