<script setup>
import { formatRupiah } from '@/helpers/format';
import { useWishlistStore } from '@/stores/wishlist';
import { useAuthStore } from '@/stores/auth';
import { storeToRefs } from 'pinia';
import { computed } from 'vue';
import { useRouter } from 'vue-router';

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
    <div
        class="group flex flex-col bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-floating hover:-translate-y-1 transition-all duration-300 h-full cursor-pointer relative border border-transparent hover:border-custom-blue/10">
        <!-- Wishlist Overlay -->
        <button @click.prevent="handleToggleWishlist"
            class="absolute top-2 right-2 z-10 flex size-8 items-center justify-center rounded-full bg-black/20 hover:bg-black/30 backdrop-blur-sm transition-all duration-200 opacity-0 group-hover:opacity-100 focus:opacity-100">
            <img v-if="!isInWishlist" src="@/assets/images/icons/heart-white-fill.svg"
                class="size-5 transition-transform active:scale-90" alt="wishlist">
            <img v-else src="@/assets/images/icons/heart-red.svg" class="size-5 transition-transform active:scale-90"
                alt="wishlist">
        </button>

        <RouterLink :to="{ name: 'app.product-detail', params: { slug: item.slug } }" class="flex flex-col h-full">
            <!-- Image -->
            <div class="aspect-[4/3] w-full bg-gray-50 overflow-hidden relative">
                <img :src="item.thumbnail"
                    class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500"
                    loading="lazy" alt="product">
                <div v-if="item.stock <= 0"
                    class="absolute inset-0 bg-white/60 backdrop-blur-[2px] flex items-center justify-center z-10">
                    <span
                        class="text-custom-black font-bold text-xs uppercase tracking-widest px-3 py-1 bg-white border border-gray-200 rounded-full shadow-sm">Habis</span>
                </div>
            </div>

            <!-- Content -->
            <div class="flex flex-col p-4 gap-1 flex-1">
                <!-- Store -->
                <div class="flex items-center gap-1 mb-1">
                    <img src="@/assets/images/icons/verify-star.svg" class="size-3.5" v-if="item?.store?.is_official"
                        alt="official">
                    <span class="text-[10px] uppercase font-bold text-custom-grey tracking-wider truncate">{{
                        item?.store?.name || 'Store' }}</span>
                </div>

                <!-- Title -->
                <h3
                    class="font-medium text-custom-black text-sm leading-snug line-clamp-2 mb-1 group-hover:text-custom-blue transition-colors min-h-[40px]">
                    {{ item?.name }}
                </h3>

                <!-- Price -->
                <p class="font-bold text-base text-custom-black mt-auto">
                    Rp {{ formatRupiah(item?.price) }}
                </p>

                <!-- Rating -->
                <div class="flex items-center gap-2 text-xs text-custom-grey mt-2">
                    <div class="flex items-center gap-1 px-1.5 py-0.5 bg-gray-100 rounded">
                        <i class="fa-solid fa-star text-custom-orange text-[10px]"></i>
                        <span class="font-bold text-custom-black">4.9</span>
                    </div>
                    <span class="text-xs">{{ item?.total_sold || 0 }} Sold</span>
                </div>
            </div>
        </RouterLink>
    </div>
</template>