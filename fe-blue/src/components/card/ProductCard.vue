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
    <div class="group flex flex-col bg-white rounded-lg overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 h-full cursor-pointer relative border border-transparent hover:border-custom-blue/10">
        <!-- Wishlist Overlay -->
        <button @click.prevent="handleToggleWishlist"
            class="absolute top-2 right-2 z-10 flex size-8 items-center justify-center rounded-full bg-black/20 hover:bg-black/30 backdrop-blur-sm transition-all duration-200">
             <img v-if="!isInWishlist" src="@/assets/images/icons/heart-white-fill.svg" class="size-5 transition-transform active:scale-90" alt="wishlist">
             <img v-else src="@/assets/images/icons/heart-red.svg" class="size-5 transition-transform active:scale-90" alt="wishlist">
        </button>

        <RouterLink :to="{ name: 'app.product-detail', params: { slug: item.slug } }" class="flex flex-col h-full">
            <!-- Image -->
            <div class="h-[180px] md:h-[220px] w-full bg-gray-100 overflow-hidden relative group-hover:opacity-90 transition-opacity">
                <img :src="item.thumbnail" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500" loading="lazy" alt="product">
                <div v-if="item.stock <= 0" class="absolute inset-0 bg-black/50 flex items-center justify-center">
                    <span class="text-white font-bold text-sm px-3 py-1 bg-black/50 rounded-full">Habis</span>
                </div>
            </div>

            <!-- Content -->
            <div class="flex flex-col p-3 gap-1 flex-1">
                <!-- Title -->
                <h3 class="font-medium text-custom-black text-sm leading-snug line-clamp-2 mb-1 group-hover:text-custom-blue transition-colors">
                    {{ item?.name }}
                </h3>

                <!-- Price -->
                <p class="font-bold text-base text-custom-black">
                    Rp {{ formatRupiah(item?.price) }}
                </p>

                <!-- Location / Store -->
                <div class="flex items-center gap-1 mt-auto pt-2">
                     <img src="@/assets/images/icons/verify-star.svg" class="size-4" v-if="item?.store?.is_official" alt="official">
                     <span class="text-xs text-custom-grey truncate">{{ item?.store?.name || 'Jakarta Pusat' }}</span>
                </div>

                <!-- Rating & Sold -->
                <div class="flex items-center gap-2 text-xs text-custom-grey mt-1">
                    <div class="flex items-center gap-1">
                        <img src="@/assets/images/icons/Star-pointy.svg" class="size-3" alt="star">
                        <span class="text-custom-black">4.9</span> 
                    </div>
                    <template v-if="item?.total_sold">
                        <span class="w-px h-3 bg-custom-stroke"></span>
                        <span>Terjual {{ item?.total_sold }}</span>
                    </template>
                </div>
            </div>
        </RouterLink>
    </div>
</template>