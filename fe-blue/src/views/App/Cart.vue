<script setup>
import { formatRupiah } from '@/helpers/format';
import { useCartStore } from '@/stores/cart'
import { storeToRefs } from 'pinia';
import { useToast } from "vue-toastification";

const cart = useCartStore()
const toast = useToast()
const { carts, selectedStores, totalSelectedItems, totalSelectedQuantity, subtotalSelected, ppnSelected, discountSelected, grandTotalSelected, hasSelectedStores } = storeToRefs(cart)
const { decreaseQuantity, increaseQuantity, removeFromCart, toggleStoreSelection, updateQuantity } = cart

const handleIncreaseQuantity = (storeId, productId) => {
    const store = carts.value.find(s => s.storeId === storeId)
    const product = store?.products.find(p => p.id === productId)
    if (product) {
       if (product.quantity < product.stock) {
           increaseQuantity(storeId, productId)
       } else {
           toast.error('Maksimal stok tercapai')
       }
    }
}

const handleUpdateQuantity = (storeId, productId, event) => {
    const value = parseInt(event.target.value)
    const store = carts.value.find(s => s.storeId === storeId)
    const product = store?.products.find(p => p.id === productId)
    
    if (product) {
        if (value > product.stock) {
            toast.error(`Stok tidak cukup. Maksimal stok ${product.stock}`)
            event.target.value = product.stock 
            updateQuantity(storeId, productId, product.stock)
        } else if (value < 1) {
             updateQuantity(storeId, productId, 1)
        } else {
             updateQuantity(storeId, productId, value)
        }
    }
}
</script>

<template>
    <form action="checkout.html" class="flex flex-col gap-6 w-full max-w-[1280px] px-4 md:px-[52px] mx-auto ">
        <h1 class="font-bold text-[32px]">My Shopping Cart</h1>
        <div class="flex flex-col lg:flex-row gap-5">
            <section id="Carts-Container" class="flex flex-col gap-5 w-full min-w-0">
                <div id="Empty-Cart-State"
                    class=" flex flex-col flex-1 items-center justify-center rounded-[20px] bg-white gap-9"
                    v-if="carts.length === 0">
                    <img src="@/assets/images/icons/bag-cross-blue-transparent.svg" class="size-16" alt="icon">
                    <div class="text-center">
                        <p class="font-bold text-2xl">Oops! Your shopping cart is empty.</p>
                        <p class="font-semibold text-custom-grey">Time to add the things you love!</p>
                    </div>
                    <RouterLink :to="{ name: 'app.home' }"
                        class="flex items-center justify-center h-14 w-fit rounded-2xl p-4 px-6 gap-2 bg-custom-blue">
                        <span class="font-bold text-white">Find Product</span>
                        <img src="@/assets/images/icons/arrow-right-circle-white-thick.svg" class="flex size-6 shrink-0"
                            alt="icon">
                    </RouterLink>
                </div>
                <div class="cart flex flex-col w-full rounded-[20px] p-5 bg-white" v-for="store in carts">
                    <div class="cart-header flex gap-4">
                        <label class="group flex items-center gap-1 relative">
                            <input type="checkbox" :checked="selectedStores.has(store.storeId)"
                                @change="toggleStoreSelection(store.storeId)" class="-z-10 absolute">
                            <div class="flex size-6 overflow-hidden relative">
                                <img src="@/assets/images/icons/checkbox-unchecked.svg"
                                    class="size-full object-contain absolute group-has-[:checked]:opacity-0 transition-300"
                                    alt="icon">
                                <img src="@/assets/images/icons/checkbox.svg"
                                    class="size-full object-contain absolute opacity-0 group-has-[:checked]:opacity-100 transition-300"
                                    alt="icon">
                            </div>
                        </label>
                        <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                            <img src="@/assets/images/icons/shop-grey.svg" class="size-4" alt="icon">
                            {{ store.storeName }}
                        </p>
                    </div>
                    <div class="cart-items-container flex flex-col pl-[10px]">
                        <div class="items group flex w-full border-b border-gray-100 last:border-0 pb-6 mb-6 last:pb-0 last:mb-0" v-for="product in store.products">
                            <div class="items-detail flex flex-col w-full gap-4">
                                <div class="flex items-start gap-4">
                                    <div class="flex size-[80px] shrink-0 rounded-xl bg-gray-50 p-2 items-center justify-center">
                                        <img :src="product.product_images.find(i => i.is_thumbnail)?.image || product.thumbnail"
                                            class="size-full object-contain mix-blend-multiply" alt="icon">
                                    </div>
                                    <div class="flex flex-col flex-1 gap-1">
                                        <p class="font-bold text-base leading-tight line-clamp-2 w-full">{{ product.name }}</p>
                                        <div class="flex items-center gap-2">
                                            <p class="text-xs font-semibold text-custom-blue bg-blue-50 px-2 py-1 rounded-md">
                                                {{ product.product_category.name}}
                                            </p>
                                            <p class="text-sm text-custom-grey">{{ product.weight }} KG</p>
                                        </div>
                                        <p class="font-bold text-lg text-custom-black mt-2">Rp {{ formatRupiah(product.price) }}</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center justify-between w-full pl-[96px]">
                                     <button
                                        class="flex items-center gap-1 text-custom-grey hover:text-custom-blue transition-colors"
                                        type="button">
                                        <i class="fa-regular fa-pen-to-square text-sm"></i>
                                        <span class="font-medium text-sm">Tulis Catatan</span>
                                    </button>

                                    <div class="flex items-center gap-4">
                                         <button
                                            class="flex items-center gap-1 text-custom-grey hover:text-custom-red transition-colors mr-2"
                                            @click="removeFromCart(store.storeId, product.id)" type="button">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </button>

                                        <div class="quantity-container flex items-center shrink-0 rounded-full border border-gray-200 p-1">
                                            <button type="button"
                                                class="subtract size-7 flex items-center justify-center rounded-full hover:bg-gray-100 disabled:opacity-50 transition-all"
                                                @click="decreaseQuantity(store.storeId, product.id)"
                                                :disabled="product.quantity <= 1">
                                                <i class="fa-solid fa-minus text-xs"></i>
                                            </button>
                                            <input type="number" 
                                                class="amount appearance-none w-10 text-center font-bold text-sm outline-none"
                                                :value="product.quantity" @change="handleUpdateQuantity(store.storeId, product.id, $event)">
                                            <button type="button" class="add size-7 flex items-center justify-center rounded-full hover:bg-gray-100 transition-all"
                                                @click="handleIncreaseQuantity(store.storeId, product.id)">
                                                <i class="fa-solid fa-plus text-xs"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </section>
            <section id="Order-Summary" class="flex flex-col gap-5 w-full lg:w-[444px] shrink-0">
                <div class="flex flex-col gap-4 rounded-[20px] p-5 bg-white">
                    <p class="font-bold text-xl">Order Summary</p>
                    <div class="flex flex-col rounded-xl border border-custom-stroke p-5 gap-4">
                        <div class="flex items-center justify-between">
                            <p class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none">
                                <img src="@/assets/images/icons/shopping-cart-grey.svg" class="size-6 flex shrink-0"
                                    alt="icon">
                                Total Items:
                            </p>
                            <p class="font-bold text-lg leading-none">{{ totalSelectedItems }} Items</p>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex items-center justify-between">
                            <p class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none">
                                <img src="@/assets/images/icons/box-grey.svg" class="size-6 flex shrink-0" alt="icon">
                                Total Quantity:
                            </p>
                            <p class="font-bold text-lg leading-none">{{ totalSelectedQuantity }}x</p>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex items-center justify-between">
                            <p class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none">
                                <img src="@/assets/images/icons/ticket-grey.svg" class="size-6 flex shrink-0"
                                    alt="icon">
                                Sub Total:
                            </p>
                            <p class="font-bold text-lg leading-none">Rp {{ formatRupiah(subtotalSelected) }}</p>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex items-center justify-between">
                            <p class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none">
                                <img src="@/assets/images/icons/receipt-2-grey.svg" class="size-6 flex shrink-0"
                                    alt="icon">
                                PPN 11%
                            </p>
                            <p class="font-bold text-lg leading-none">Rp {{ formatRupiah(ppnSelected) }}</p>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex items-center justify-between">
                            <p class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none">
                                <img src="@/assets/images/icons/discount-shape-grey.svg" class="size-6 flex shrink-0"
                                    alt="icon">
                                Discount
                            </p>
                            <p class="font-bold text-lg leading-none">Rp {{ formatRupiah(discountSelected) }}</p>
                        </div>
                        <hr class="border-custom-stroke">
                        <div class="flex items-center justify-between">
                            <p class="flex items-center gap-1 font-semibold text-custom-grey text-lg leading-none">
                                <img src="@/assets/images/icons/money-grey.svg" class="size-6 flex shrink-0" alt="icon">
                                Grand total
                            </p>
                            <p class="font-bold text-lg leading-none text-custom-blue">Rp {{
                                formatRupiah(grandTotalSelected) }}</p>
                        </div>
                    </div>
                    <RouterLink :to="{ name: 'app.checkout' }"
                        class="flex items-center justify-center h-16 w-full rounded-2xl p-4 gap-2 bg-custom-blue disabled:bg-custom-stroke transition-300">
                        <span class="font-bold text-white">
                            {{ hasSelectedStores ? 'Continue To Checkout' : 'Select stores to checkout' }}
                        </span>
                        <img src="@/assets/images/icons/arrow-right-circle-white-thick.svg" class="flex size-6 shrink-0"
                            alt="icon">
                    </RouterLink>

                    <!-- Pesan ketika tidak ada toko yang dipilih -->
                    <div v-if="!hasSelectedStores && carts.length > 0" class="text-center p-4">
                        <p class="text-custom-grey font-medium">Please select at least one store to continue</p>
                    </div>
                </div>
            </section>
        </div>
    </form>
</template>