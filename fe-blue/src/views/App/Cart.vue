<script setup>
import { formatRupiah } from '@/helpers/format';
import { useCartStore } from '@/stores/cart'
import { storeToRefs } from 'pinia';

const cart = useCartStore()
const { carts, selectedStores, totalSelectedItems, totalSelectedQuantity, subtotalSelected, ppnSelected, discountSelected, grandTotalSelected, hasSelectedStores } = storeToRefs(cart)
const { decreaseQuantity, increaseQuantity, removeFromCart, toggleStoreSelection } = cart
</script>

<template>
    <form action="checkout.html" class="flex flex-col gap-6 w-full max-w-[1280px] px-[52px] mx-auto ">
        <h1 class="font-bold text-[32px]">My Shopping Cart</h1>
        <div class="flex gap-5">
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
                        <div class="items group flex w-full" v-for="product in store.products">
                            <div class="branch-line-ornaments relative flex shrink-0 w-[30px] overflow-hidden">
                                <div
                                    class="absolute h-full border border-custom-stroke group-[&:last-of-type]:h-[calc(50%-24px)]">
                                </div>
                                <div
                                    class="line-to-items flex h-1/2 w-[200%] shrink-0 border-2 border-custom-stroke border-t-0 border-r-0 rounded-bl-3xl">
                                </div>
                            </div>
                            <div
                                class="items-detail flex flex-col w-full rounded-[20px] border border-custom-stroke p-5 gap-5 mt-4">
                                <div class="flex items-center gap-[14px] overflow-hidden">
                                    <div
                                        class="flex size-[92px] shrink-0 rounded-2xl bg-custom-background overflow-hidden items-center justify-center">
                                        <img :src="product.product_images.find(i => i.is_thumbnail)?.image || product.thumbnail"
                                            class="size-full object-contain" alt="icon">
                                    </div>
                                    <div class="flex flex-col flex-1 gap-[6px] overflow-hidden">
                                        <p class="font-bold text-lg truncate">{{ product.name }}</p>
                                        <p
                                            class="font-semibold leading-none text-custom-grey flex items-center gap-[6px]">
                                            <span class="font-bold text-custom-blue">{{
                                                product.product_category.name}}</span>
                                            <span class="text-[22px] leading-none">•</span>
                                            <span>{{ product.weight }} KG</span>
                                        </p>
                                    </div>
                                    <div
                                        class="quantity-container flex items-center shrink-0 rounded-2xl border border-custom-stroke p-4">
                                        <button type="button"
                                            class="subtract size-5 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                                            @click="decreaseQuantity(store.storeId, product.id)"
                                            :disabled="product.quantity <= 1">
                                            <span class="text-[30px] font-light leading-none align-middle mb-1">-</span>
                                        </button>
                                        <div class="h-[18px] border border-custom-stroke ml-4"></div>
                                        <input type="number" name=""
                                            class="amount appearance-none w-[70px] pl-5 text-center font-bold text-lg"
                                            v-model="product.quantity">
                                        <div class="h-[18px] border border-custom-stroke mr-4" value="1"></div>
                                        <button type="button" class="add size-5 flex items-center justify-center"
                                            @click="increaseQuantity(store.storeId, product.id)">
                                            <span class="text-[24px] font-light leading-none align-middle mb-1">+</span>
                                        </button>
                                    </div>
                                </div>
                                <hr class="border-custom-stroke">
                                <div class="flex items-center justify-between">
                                    <div class="flex flex-col gap-[6px]">
                                        <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
                                            <img src="@/assets/images/icons/shopping-cart-grey.svg" class="size-5"
                                                alt="icon">
                                            Subtotal
                                        </p>
                                        <p class="font-bold text-lg text-custom-blue leading-none">Rp {{
                                            formatRupiah(product.price * product.quantity) }}</p>
                                    </div>
                                    <button
                                        class="flex items-center w-fit h-[52px] rounded-xl py-[14px] px-4 gap-[6px] bg-custom-red/10"
                                        @click="removeFromCart(store.storeId, product.id)" type="button">
                                        <img src="@/assets/images/icons/trash-red.svg" class="size-6 flex shrink-0"
                                            alt="icon">
                                        <span class="font-semibold text-custom-red">Remove</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </section>
            <section id="Order-Summary" class="flex flex-col gap-5 w-[444px] shrink-0">
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