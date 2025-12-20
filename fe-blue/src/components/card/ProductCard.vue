<script setup>
import { formatRupiah } from '@/helpers/format';
import _ from 'lodash';
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
const { hasProduct } = storeToRefs(wishlistStore)
const { toggleWishlist } = wishlistStore

const isInWishlist = computed(() => {
    return hasProduct.value(props.item.id)
})

const handleToggleWishlist = async () => {
    if (!authStore.token) {
        router.push({ name: 'auth.login' })
        return
    }
    await toggleWishlist(props.item.id)
}
</script>

<template>
    <div class="card flex flex-col rounded-t-[20px] overflow-hidden h-full">
        <RouterLink :to="{ name: 'app.product-detail', params: { slug: item.slug } }">
            <div class="thumbnail w-full overflow-hidden bg-custom-background items-center justify-center transition-all duration-300"
                :class="compact ? 'h-[140px]' : 'h-[192px]'">
                <img :src="item?.product_images?.find(image => image.is_thumbnail)?.image"
                    class="size-full object-contain" alt="thumbnail">
            </div>
        </RouterLink>
        <div class="flex flex-col rounded-b-[20px] overflow-hidden border border-custom-stroke border-t-0 bg-white transition-all duration-300"
            :class="compact ? 'p-3 gap-3' : 'p-5 gap-6'">
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-3" :class="compact ? 'text-xs' : ''">
                    <div class="rounded-[4px] bg-custom-blue/10 flex items-center justify-center"
                        :class="compact ? 'p-1' : 'p-2'">
                        <span class="font-bold text-custom-blue">{{ _.truncate(item?.product_category?.name, {
                            length: compact ? 8 : 12
                        }) }}</span>
                    </div>
                    <p class="font-semibold text-custom-red text-nowrap">{{ item?.total_sold }} Sold</p>
                </div>
                <div class="flex flex-col gap-1 w-full min-w-0 overflow-hidden">
                    <RouterLink :to="{ name: 'app.product-detail', params: { slug: item.slug } }">
                        <p class="font-bold w-full truncate" :class="compact ? 'text-base' : 'text-xl'">{{ item?.name
                            }}</p>
                    </RouterLink>
                    <p class="font-bold text-custom-blue" :class="compact ? 'text-base' : 'text-xl'"> Rp {{
                        formatRupiah(item?.price) }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 w-full">
                <button @click.prevent="handleToggleWishlist"
                    class="group flex items-center justify-center shrink-0 rounded-2xl transition-300"
                    :class="[
                        isInWishlist ? 'bg-custom-red hover:bg-custom-red/80' : 'bg-custom-red/10 hover:bg-custom-red',
                        compact ? 'size-10 p-2' : 'size-14 p-4 gap-2'
                    ]">
                    <div class="relative flex shrink-0" :class="compact ? 'size-5' : 'size-6'">
                        <img v-if="!isInWishlist" src="@/assets/images/icons/heart-red.svg"
                            class="absolute flex size-full shrink-0 opacity-100 group-hover:opacity-0 transition-300"
                            alt="icon">
                        <img src="@/assets/images/icons/heart-white-fill.svg"
                            class="absolute flex size-full shrink-0 transition-300"
                            :class="isInWishlist ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'" alt="icon">
                    </div>
                </button>
                <RouterLink :to="{ name: 'app.product-detail', params: { slug: item.slug } }"
                    class="group flex items-center justify-center w-full rounded-2xl bg-custom-blue/10 hover:bg-custom-blue transition-300"
                    :class="compact ? 'h-10 p-2 text-sm' : 'h-14 p-4 gap-[6px]'">
                    <span class="font-semibold text-custom-blue group-hover:text-white transition-300">Detail</span>
                </RouterLink>
            </div>
        </div>
    </div>
</template>