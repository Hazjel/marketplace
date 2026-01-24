<script setup>
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useProductStore } from '@/stores/product';
import { useWishlistStore } from '@/stores/wishlist';
import { storeToRefs } from 'pinia';
import { onMounted, onUnmounted, ref, computed } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { formatRupiah } from '@/helpers/format';

const router = useRouter()
const showDropdownProfile = ref(false);

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)
const { checkAuth, logout } = authStore

const cartStore = useCartStore()
const { totalItems } = storeToRefs(cartStore)

const wishlistStore = useWishlistStore()
const { totalItems: totalWishlistItems } = storeToRefs(wishlistStore)
const { fetchWishlist } = wishlistStore

const productStore = useProductStore()
const { searchProducts } = productStore

// Computed properties for template logic safety
const sellerDashboardLabel = computed(() => {
    return authStore.activeMode === 'buyer' ? 'Switch to Seller' : 'Seller Dashboard';
});

const wishlistBadgeText = computed(() => {
    return totalWishlistItems.value > 99 ? '99+' : totalWishlistItems.value;
});

const cartBadgeText = computed(() => {
    return totalItems.value > 99 ? '99+' : totalItems.value;
});

const getProductImage = (product) => {
    if (product.product_images && Array.isArray(product.product_images)) {
        const thumb = product.product_images.find(i => i.is_thumbnail);
        if (thumb) return thumb.image;
    }
    return product.thumbnail || 'https://placehold.co/100';
};

// Search State
const searchQuery = ref('');
const searchResults = ref([]);
const showSearchResults = ref(false);
const isSearching = ref(false);
const showHistory = ref(false);
const searchHistory = ref([]);

// History Management
const loadHistory = () => {
    const history = localStorage.getItem('blukios_search_history');
    if (history) {
        searchHistory.value = JSON.parse(history);
    }
};

const saveHistory = (query) => {
    if (!query) return;
    let history = searchHistory.value;
    history = history.filter(item => item.toLowerCase() !== query.toLowerCase());
    history.unshift(query);
    if (history.length > 5) history.pop();
    searchHistory.value = history;
    localStorage.setItem('blukios_search_history', JSON.stringify(history));
};

const removeHistoryItem = (index) => {
    searchHistory.value.splice(index, 1);
    localStorage.setItem('blukios_search_history', JSON.stringify(searchHistory.value));
};

const clearHistory = () => {
    searchHistory.value = [];
    localStorage.removeItem('blukios_search_history');
};

const applyHistorySearch = (item) => {
    searchQuery.value = item;
    handleSearchInput();
};

let searchTimeout = null;

const handleSearchInput = () => {
    clearTimeout(searchTimeout);
    const query = searchQuery.value.trim();

    if (query.length === 0) {
        showSearchResults.value = false;
        showHistory.value = true;
        searchResults.value = [];
        return;
    }

    showHistory.value = false;
    isSearching.value = true;

    searchTimeout = setTimeout(async () => {
        try {
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

const handleEnterSearch = () => {
    const query = searchQuery.value.trim();
    if (query.length > 0) {
        saveHistory(query);
        showSearchResults.value = false;
        showHistory.value = false;
        router.push({ name: 'app.search', query: { q: query } });
    }
}

const handleProductClick = (slug, name) => {
    saveHistory(name);
    showSearchResults.value = false;
    showHistory.value = false;
    searchQuery.value = '';
    router.push({ name: 'app.product-detail', params: { slug } });
};

const handleClickOutside = (e) => {
    if (!e.target.closest('.search-container')) {
        showSearchResults.value = false;
        showHistory.value = false;
    }
    if (!e.target.closest('.profile-dropdown-container')) {
        showDropdownProfile.value = false;
    }
};

const handleFocus = () => {
    loadHistory();
    if (searchQuery.value.trim() === '') {
        showHistory.value = true;
    } else {
        showSearchResults.value = true;
    }
};

onMounted(() => {
    checkAuth();
    if (user.value) {
        fetchWishlist();
    }
    loadHistory();
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <section id="Navbar-Wrapper"
        class="sticky top-0 left-0 w-full bg-white border-b border-custom-stroke py-4 md:py-8 z-50 transition-all duration-300">
        <div class="w-full">
            <div class="w-full max-w-[1920px] flex flex-col gap-6 px-4 md:px-7 mx-auto">
                <div
                    class="flex flex-wrap md:flex-nowrap items-center justify-between md:justify-start gap-2 md:gap-6 w-full">
                    <!-- Logo -->
                    <RouterLink :to="{ name: 'app.home' }" class="flex shrink-0">
                        <img src="@/assets/images/logos/blukios_logo.png"
                            class="h-8 md:h-12 max-w-[120px] md:max-w-none object-contain" alt="logo">
                    </RouterLink>

                    <!-- Search Bar with Autocomplete -->
                    <div class="relative order-last md:order-none w-full md:w-auto md:flex-1 search-container">
                        <label
                            class="flex items-center w-full h-12 md:h-14 rounded-[18px] p-3 md:p-4 md:px-6 gap-2 bg-white border-[1.5px] border-custom-stroke focus-within:border-custom-black transition-300">
                            <img src="@/assets/images/icons/search-normal-grey.svg" class="flex size-6 shrink-0"
                                alt="icon">
                            <input type="text" v-model="searchQuery" @input="handleSearchInput"
                                @keyup.enter="handleEnterSearch"
                                class="appearance-none w-full placeholder:text-custom-grey font-semibold focus:outline-none"
                                placeholder="Search any products">
                            <div v-if="isSearching" class="flex items-center">
                                <div
                                    class="size-5 border-2 border-custom-blue border-t-transparent rounded-full animate-spin">
                                </div>
                            </div>
                        </label>

                        <!-- Search Results Dropdown -->
                        <div v-show="showSearchResults || showHistory"
                            class="absolute top-full mt-2 w-full bg-white rounded-2xl shadow-[0px_6px_30px_0px_#00000017] border border-custom-stroke overflow-hidden z-50">

                            <!-- History Section -->
                            <div v-if="showHistory && searchHistory.length > 0">
                                <div
                                    class="flex items-center justify-between px-4 py-3 border-b border-custom-stroke/50">
                                    <span class="text-xs font-bold text-custom-grey uppercase tracking-wider">Recent
                                        Searches</span>
                                    <button @click="clearHistory"
                                        class="text-xs font-semibold text-custom-red hover:underline">Clear All</button>
                                </div>
                                <div class="flex flex-col">
                                    <div v-for="(item, index) in searchHistory" :key="index"
                                        class="flex items-center justify-between p-4 hover:bg-custom-background transition-300 cursor-pointer group"
                                        @click="applyHistorySearch(item)">
                                        <div class="flex items-center gap-3">
                                            <img src="@/assets/images/icons/search-normal-grey.svg"
                                                class="size-5 opacity-60" alt="history">
                                            <span
                                                class="font-medium text-custom-black group-hover:text-custom-blue transition-colors">{{
                                                    item }}</span>
                                        </div>
                                        <button @click.stop="removeHistoryItem(index)"
                                            class="hidden group-hover:flex p-1 hover:bg-gray-200 rounded-full">
                                            <img src="@/assets/images/icons/close-circle-black.svg" class="size-4"
                                                alt="remove">
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Product Results Section -->
                            <div v-else-if="showSearchResults">
                                <div class="flex flex-col max-h-[400px] overflow-y-auto custom-scrollbar">
                                    <button v-for="product in searchResults" :key="product.id"
                                        @click="handleProductClick(product.slug, product.name)"
                                        class="flex items-center gap-4 p-4 hover:bg-custom-background transition-300 text-left border-b border-custom-stroke/30 last:border-0">
                                        <div
                                            class="flex size-14 shrink-0 rounded-xl bg-custom-background overflow-hidden border border-custom-stroke/50">
                                            <img :src="getProductImage(product)" class="size-full object-cover"
                                                alt="product">
                                        </div>
                                        <div class="flex flex-col gap-1 flex-1 min-w-0">
                                            <p class="font-bold text-sm truncate text-custom-black">{{ product.name }}
                                            </p>
                                            <div class="flex items-center gap-2">
                                                <p class="text-custom-grey text-xs">{{ product.product_category?.name }}
                                                </p>
                                            </div>
                                            <p class="font-bold text-custom-blue text-sm">Rp {{
                                                formatRupiah(product.price) }}</p>
                                        </div>
                                    </button>
                                </div>
                                <div class="p-3 bg-custom-background border-t border-custom-stroke hover:bg-custom-blue/5 transition-colors cursor-pointer text-center"
                                    @click="handleEnterSearch">
                                    <p class="text-xs font-bold text-custom-blue">See all results for "{{ searchQuery
                                    }}"</p>
                                </div>
                            </div>

                            <!-- Empty State for History -->
                            <div v-else-if="!showSearchResults && showHistory && searchHistory.length === 0"
                                class="p-8 text-center text-custom-grey">
                                <p class="text-sm">No recent searches</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 md:gap-3 shrink-0 ml-auto md:ml-0">
                        <!-- Wishlist Icon with Badge -->
                        <div class="relative">
                            <RouterLink :to="{ name: 'app.wishlist' }">
                                <div
                                    class="flex size-10 md:size-14 rounded-full bg-custom-icon-background items-center justify-center overflow-hidden">
                                    <img src="@/assets/images/icons/heart-black.svg" class="size-5 md:size-6"
                                        alt="icon">
                                </div>
                            </RouterLink>
                            <div v-if="totalWishlistItems > 0"
                                class="absolute top-0 right-0 flex items-center justify-center min-w-[16px] md:min-w-[20px] min-h-[16px] md:min-h-[20px] rounded-full bg-custom-red border-2 border-white px-1">
                                <span class="text-white text-[8px] md:text-[10px] font-bold leading-none">{{
                                    wishlistBadgeText }}</span>
                            </div>
                        </div>

                        <!-- Cart Icon with Badge -->
                        <div class="relative">
                            <RouterLink :to="{ name: 'app.cart' }">
                                <div
                                    class="flex size-10 md:size-14 rounded-full bg-custom-icon-background items-center justify-center overflow-hidden">
                                    <img src="@/assets/images/icons/shopping-cart-black.svg" class="size-5 md:size-6"
                                        alt="icon">
                                </div>
                            </RouterLink>
                            <div v-if="totalItems > 0"
                                class="absolute top-0 right-0 flex items-center justify-center min-w-[16px] md:min-w-[20px] min-h-[16px] md:min-h-[20px] rounded-full bg-custom-red border-2 border-white px-1">
                                <span class="text-white text-[8px] md:text-[10px] font-bold leading-none">{{
                                    cartBadgeText }}</span>
                            </div>
                        </div>

                        <RouterLink :to="{ name: 'admin.dashboard' }" v-if="user && user.role === 'store'"
                            @click="authStore.setMode('store')"
                            class="flex shrink-0 h-10 md:h-14 rounded-[18px] py-2 px-3 md:py-4 md:px-6 bg-custom-black text-white hover:shadow-lg transition-300">
                            <img src="@/assets/images/icons/shop-white.svg" class="size-5 md:size-6 md:mr-2" alt="icon">
                            <p class="font-medium hidden md:block">{{ sellerDashboardLabel }}</p>
                        </RouterLink>

                        <RouterLink :to="{ name: 'auth.open-store' }" v-if="user && user.role === 'buyer'"
                            @click="authStore.setMode('buyer')"
                            class="flex shrink-0 h-10 md:h-14 rounded-[18px] py-2 px-3 md:py-4 md:px-6 bg-custom-black text-white hover:shadow-lg transition-300">
                            <img src="@/assets/images/icons/shop-white.svg" class="size-5 md:size-6 md:mr-2" alt="icon">
                            <p class="font-medium hidden md:block">Start Selling</p>
                        </RouterLink>

                        <RouterLink :to="{ name: 'auth.login' }"
                            class="flex shrink-0 h-10 md:h-14 rounded-[18px] py-2 px-3 md:py-4 md:px-8 bg-custom-blue"
                            v-if="!user">
                            <p class="font-medium text-white text-xs md:text-base">Sign In</p>
                        </RouterLink>
                        <div class="relative profile-dropdown-container" v-if="user">
                            <button @click="showDropdownProfile = !showDropdownProfile"
                                class="flex size-10 md:size-14 rounded-full bg-custom-icon-background items-center justify-center overflow-hidden">
                                <img :src="user.profile_picture" class="size-full object-cover" alt="icon">
                            </button>
                            <div id="Profile-Dropdown"
                                class="absolute transform top-[calc(100%+8px)] md:top-[calc(100%+12px)] right-0 z-30"
                                v-if="showDropdownProfile">
                                <nav
                                    class="flex flex-col w-[201px] rounded-[20px] rounded-tr-none py-6 px-4 gap-[18px] bg-white shadow-[0px_6px_30px_0px_#00000017]">
                                    <RouterLink v-if="user"
                                        :to="{ name: user.role === 'admin' ? 'admin.dashboard' : 'user.dashboard', params: user.role === 'admin' ? {} : { username: user.username } }"
                                        class="flex w-full items-center justify-between"
                                        @click="() => { showDropdownProfile = false; authStore.setMode('buyer') }">
                                        <span class="font-medium text-custom-grey">My Dashboard</span>
                                        <img src="@/assets/images/icons/profile-circle-grey.svg"
                                            class="flex size-6 shrink-0" alt="icon">
                                    </RouterLink>
                                    <RouterLink v-if="user && (user.role === 'buyer' || user.role === 'store')"
                                        :to="{ name: 'user.my-transaction', params: { username: user.username } }"
                                        class="flex w-full items-center justify-between"
                                        @click="showDropdownProfile = false">
                                        <span class="font-medium text-custom-grey">My Transactions</span>
                                        <img src="@/assets/images/icons/box-search-grey.svg"
                                            class="flex size-6 shrink-0" alt="icon">
                                    </RouterLink>
                                    <RouterLink
                                        :to="{ name: user.role === 'admin' ? 'admin.edit-profile' : 'user.edit-profile', params: user.role === 'admin' ? {} : { username: user.username } }"
                                        class="flex w-full items-center justify-between">
                                        <span class="font-medium text-custom-grey">Edit Profile</span>
                                        <img src="@/assets/images/icons/setting-2-grey.svg" class="flex size-6 shrink-0"
                                            alt="icon">
                                    </RouterLink>
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
            </div>
        </div>
    </section>
</template>