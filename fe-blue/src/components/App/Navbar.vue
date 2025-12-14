<script setup>
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useProductStore } from '@/stores/product';
import { storeToRefs } from 'pinia';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { formatRupiah } from '@/helpers/format';

const router = useRouter()
const showDropdownProfile = ref(false);
const searchQuery = ref('');
const searchResults = ref([]);
const showSearchResults = ref(false);
const isSearching = ref(false);

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)
const { checkAuth, logout } = authStore

const cartStore = useCartStore()
const { totalItems } = storeToRefs(cartStore)

const productStore = useProductStore()
const { searchProducts } = productStore // ✅ Gunakan method searchProducts

// Debounce function
let searchTimeout = null;

// Handle search input dengan debounce
const handleSearchInput = () => {
    clearTimeout(searchTimeout);
    
    const query = searchQuery.value.trim();
    
    if (query.length < 2) {
        showSearchResults.value = false;
        searchResults.value = [];
        return;
    }
    
    isSearching.value = true;
    
    searchTimeout = setTimeout(async () => {
        try {
            // ✅ Gunakan searchProducts yang tidak mengubah state utama
            const results = await searchProducts({ 
                search: query,
                limit: 5
            });
            
            searchResults.value = results || [];
            showSearchResults.value = searchResults.value.length > 0;
        } catch (error) {
            console.error('Search error:', error);
            searchResults.value = [];
            showSearchResults.value = false;
        } finally {
            isSearching.value = false;
        }
    }, 300);
};

// Handle product click
const handleProductClick = (slug) => {
    showSearchResults.value = false;
    searchQuery.value = '';
    router.push({ name: 'app.product-detail', params: { slug } });
};

// Close search results when clicking outside
const handleClickOutside = (e) => {
    if (!e.target.closest('.search-container')) {
        showSearchResults.value = false;
    }
};

onMounted(() => {
    checkAuth();
    document.addEventListener('click', handleClickOutside);
});

// Cleanup
onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <section id="Navbar-Wrapper" class="flex h-[168px] w-full mx-auto relative">
        <div class="fixed top-0 w-full bg-white min-h-[168px] border-b border-custom-stroke py-8 z-30">
            <div class="w-full max-w-[1920px] flex flex-col gap-6 px-7 mx-auto">
                <div class="flex items-center gap-6 w-full">
                    <RouterLink :to="{ name: 'app.home' }" class="flex shrink-0">
                        <img src="@/assets/images/logos/logo.svg" class="h-8" alt="logo">
                    </RouterLink>
                    
                    <!-- Search Bar with Autocomplete -->
                    <div class="relative w-full search-container">
                        <label class="flex items-center w-full h-14 rounded-[18px] p-4 px-6 gap-2 bg-white border-[1.5px] border-custom-stroke focus-within:border-custom-black transition-300">
                            <img src="@/assets/images/icons/search-normal-grey.svg" class="flex size-6 shrink-0" alt="icon">
                            <input 
                                type="text" 
                                v-model="searchQuery"
                                @input="handleSearchInput"
                                class="appearance-none w-full placeholder:text-custom-grey font-semibold focus:outline-none" 
                                placeholder="Search any products">
                            <div v-if="isSearching" class="flex items-center">
                                <div class="size-5 border-2 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
                            </div>
                        </label>
                        
                        <!-- Search Results Dropdown -->
                        <div v-if="showSearchResults" 
                            class="absolute top-full mt-2 w-full bg-white rounded-2xl shadow-[0px_6px_30px_0px_#00000017] border border-custom-stroke overflow-hidden z-50">
                            <div class="flex flex-col max-h-[400px] overflow-y-auto">
                                <button
                                    v-for="product in searchResults" 
                                    :key="product.id"
                                    @click="handleProductClick(product.slug)"
                                    class="flex items-center gap-4 p-4 hover:bg-custom-background transition-300 text-left">
                                    <div class="flex size-16 shrink-0 rounded-xl bg-custom-background overflow-hidden">
                                        <img 
                                            :src="product.product_images.find(i => i.is_thumbnail)?.image || product.thumbnail" 
                                            class="size-full object-cover" 
                                            alt="product" />
                                    </div>
                                    <div class="flex flex-col gap-1 flex-1 min-w-0">
                                        <p class="font-bold text-sm truncate">{{ product.name }}</p>
                                        <p class="text-custom-grey text-xs">{{ product.product_category?.name }}</p>
                                        <p class="font-bold text-custom-blue text-sm">Rp {{ formatRupiah(product.price) }}</p>
                                    </div>
                                    <img src="@/assets/images/icons/arrow-right-black.svg" class="size-5 shrink-0" alt="icon">
                                </button>
                            </div>
                            <div class="p-3 bg-custom-background border-t border-custom-stroke">
                                <p class="text-xs text-custom-grey text-center">Showing {{ searchResults.length }} results for "{{ searchQuery }}"</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 shrink-0">
                        <a href="#">
                            <div class="flex size-14 rounded-full bg-custom-icon-background items-center justify-center overflow-hidden">
                                <img src="@/assets/images/icons/notification-black.svg" class="size-6" alt="icon">
                            </div>
                        </a>
                        
                        <!-- Cart Icon with Badge -->
                        <div class="relative">
                            <RouterLink :to="{ name: 'app.cart' }">
                                <div class="flex size-14 rounded-full bg-custom-icon-background items-center justify-center overflow-hidden">
                                    <img src="@/assets/images/icons/shopping-cart-black.svg" class="size-6" alt="icon">
                                </div>
                            </RouterLink>
                            <div v-if="totalItems > 0" class="absolute top-0 right-0 flex items-center justify-center min-w-[20px] min-h-[20px] rounded-full bg-custom-red border-2 border-white px-1">
                                <span class="text-white text-[10px] font-bold leading-none">{{ totalItems > 99 ? '99+' : totalItems }}</span>
                            </div>
                        </div>
                        
                        <RouterLink :to="{ name: 'auth.login' }" class="flex shrink-0 h-14 rounded-[18px] py-4 px-8 bg-custom-blue" v-if="!user">
                            <p class="font-medium text-white">Sign In/Register</p>
                        </RouterLink>
                        <div class="relative" v-if="user">
                            <button @click="showDropdownProfile = !showDropdownProfile"
                                class="flex size-14 rounded-full bg-custom-icon-background items-center justify-center overflow-hidden">
                                <img :src="user.profile_picture" class="size-full object-cover" alt="icon">
                            </button>
                            <div id="Profile-Dropdown"
                                class="absolute transform top-[calc(100%+12px)] right-0 z-30" v-if="showDropdownProfile">
                                <nav
                                    class="flex flex-col w-[201px] rounded-[20px] rounded-tr-none py-6 px-4 gap-[18px] bg-white shadow-[0px_6px_30px_0px_#00000017]">
                                    <RouterLink 
                                        v-if="user" 
                                        :to="{ name: 'admin.dashboard' }" 
                                        class="flex w-full items-center justify-between"
                                        @click="showDropdownProfile = false">
                                        <span class="font-medium text-custom-grey">My Profile</span>
                                        <img src="@/assets/images/icons/profile-circle-grey.svg"
                                            class="flex size-6 shrink-0" alt="icon">
                                    </RouterLink>
                                    <RouterLink :to="{ name: 'admin.my-transaction' }"
                                        class="flex w-full items-center justify-between">
                                        <span class="font-medium text-custom-grey">My Transactions</span>
                                        <img src="@/assets/images/icons/stickynote-grey.svg" class="flex size-6 shrink-0"
                                            alt="icon">
                                    </RouterLink>
                                    <a href="#" class="flex w-full items-center justify-between">
                                        <span class="font-medium text-custom-grey">Settings</span>
                                        <img src="@/assets/images/icons/setting-2-grey.svg" class="flex size-6 shrink-0"
                                            alt="icon">
                                    </a>
                                    <hr class="border-custom-stroke">
                                    <button @click="logout" class="flex w-full items-center justify-between">
                                        <span class="font-medium text-custom-red">Log Out</span>
                                        <img src="@/assets/images/icons/logout.svg" class="flex size-6 shrink-0"
                                            alt="icon">
                                    </button>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-8 flex-wrap">
                    <RouterLink :to="{ name: 'app.home' }" class="group flex items-center gap-2 active">
                        <img src="@/assets/images/icons/home-blue-fill.svg" class="hidden size-6 shrink-0 group-[&.active]:flex" alt="icon">
                        <img src="@/assets/images/icons/home-grey.svg" class="flex size-6 shrink-0 group-[&.active]:hidden" alt="icon">
                        <span class="font-semibold text-custom-grey group-[&.active]:text-custom-blue">Homepage</span>
                    </RouterLink>
                    <a href="#" class="group flex items-center gap-2">
                        <img src="@/assets/images/icons/flash-grey.svg" class="flex size-6 shrink-0" alt="icon">
                        <span class="font-semibold text-custom-grey">Flash Deals</span>
                    </a>
                    <a href="#" class="group flex items-center gap-2">
                        <img src="@/assets/images/icons/box-search-grey.svg" class="flex size-6 shrink-0" alt="icon">
                        <span class="font-semibold text-custom-grey">Track Order</span>
                    </a>
                    <a href="#" class="group flex items-center gap-2">
                        <img src="@/assets/images/icons/note-grey.svg" class="flex size-6 shrink-0" alt="icon">
                        <span class="font-semibold text-custom-grey">Return & Refund</span>
                    </a>
                    <a href="#" class="group flex items-center gap-2">
                        <img src="@/assets/images/icons/car-delivery-grey.svg" class="flex size-6 shrink-0" alt="icon">
                        <span class="font-semibold text-custom-grey">Shipping Info</span>
                    </a>
                    <a href="#" class="group flex items-center gap-2">
                        <img src="@/assets/images/icons/buildings-grey.svg" class="flex size-6 shrink-0" alt="icon">
                        <span class="font-semibold text-custom-grey">About Us</span>
                    </a>
                    <a href="#" class="group flex items-center gap-2">
                        <img src="@/assets/images/icons/callcenter-grey.svg" class="flex size-6 shrink-0" alt="icon">
                        <span class="font-semibold text-custom-grey">Customer Services</span>
                    </a>
                </div>
            </div>
        </div>
    </section>
</template>