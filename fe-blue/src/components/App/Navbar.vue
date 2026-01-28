<script setup>
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useProductStore } from '@/stores/product';
import { useWishlistStore } from '@/stores/wishlist';
import { useThemeStore } from '@/stores/theme';
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

const themeStore = useThemeStore();
const { effectiveTheme } = storeToRefs(themeStore);
const { toggleTheme } = themeStore;

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
        class="sticky top-0 left-0 w-full bg-surface/95 backdrop-blur-md border-b border-border py-4 md:py-8 z-50 transition-all duration-300">
        <div class="w-full">
            <div class="w-full max-w-[1920px] flex flex-col gap-6 px-4 md:px-7 mx-auto">
                <div
                    class="flex flex-wrap md:flex-nowrap items-center justify-between md:justify-start gap-2 md:gap-6 w-full">
                    <!-- Logo -->
                    <RouterLink :to="{ name: 'app.home' }" class="flex shrink-0">
                        <img src="@/assets/images/logos/blukios_logo.png"
                            class="h-8 md:h-12 max-w-[120px] md:max-w-none object-contain dark:brightness-0 dark:invert"
                            alt="logo">
                    </RouterLink>

                    <!-- Search Bar with Autocomplete -->
                    <div class="relative order-last md:order-none w-full md:w-auto md:flex-1 search-container">
                        <label
                            class="flex items-center w-full h-12 md:h-14 rounded-[18px] p-3 md:p-4 md:px-6 gap-2 bg-white dark:bg-white/5 border-[1.5px] border-custom-stroke dark:border-white/10 focus-within:border-custom-black dark:focus-within:border-white transition-300">
                            <img src="@/assets/images/icons/search-normal-grey.svg"
                                class="flex size-6 shrink-0 dark:brightness-0 dark:invert" alt="icon">
                            <input type="text" v-model="searchQuery" @input="handleSearchInput"
                                @keyup.enter="handleEnterSearch"
                                class="appearance-none w-full placeholder:text-custom-grey font-semibold bg-transparent focus:outline-none text-custom-black"
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

                    <div class="flex items-center gap-3 shrink-0 ml-auto md:ml-0 h-10 md:h-12">

                        <!-- Cart Icon with Badge (Always Visible) -->
                        <div class="relative group">
                            <RouterLink :to="{ name: 'app.cart' }"
                                class="flex items-center justify-center size-10 md:size-11 rounded-lg hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                <img src="@/assets/images/icons/shopping-cart-black.svg"
                                    class="size-6 dark:brightness-0 dark:invert opacity-70 dark:opacity-100 group-hover:opacity-100 transition-opacity"
                                    alt="cart">
                            </RouterLink>
                            <div v-if="totalItems > 0"
                                class="absolute top-1 right-1 flex items-center justify-center min-w-[18px] h-[18px] rounded-full bg-custom-red border-2 border-white dark:border-[#0B1120] px-1 pointer-events-none">
                                <span class="text-white text-[9px] font-bold leading-none">{{ cartBadgeText }}</span>
                            </div>
                        </div>

                        <!-- Vertical Divider -->
                        <div class="w-[1px] h-6 bg-custom-stroke dark:bg-white/10 mx-1 hidden md:block"></div>

                        <!-- Login Button (If Guest) -->
                        <RouterLink :to="{ name: 'auth.login' }"
                            class="flex shrink-0 h-10 md:h-11 rounded-lg px-6 font-bold text-custom-blue border border-custom-blue hover:bg-blue-50 dark:hover:bg-white/5 items-center justify-center transition-all"
                            v-if="!user">
                            Masuk
                        </RouterLink>

                        <!-- Register Button (If Guest) -->
                        <RouterLink :to="{ name: 'auth.register' }"
                            class="flex shrink-0 h-10 md:h-11 rounded-lg px-6 font-bold text-white bg-custom-blue hover:bg-blue-700 shadow-custom-blue/20 shadow-lg items-center justify-center transition-all"
                            v-if="!user">
                            Daftar
                        </RouterLink>

                        <!-- User Profile Pill (Hover Trigger) -->
                        <div class="relative profile-dropdown-container z-50 h-full flex items-center" v-if="user"
                            @mouseenter="showDropdownProfile = true" @mouseleave="showDropdownProfile = false">

                            <!-- Profile Trigger -->
                            <button
                                class="flex items-center gap-3 pl-2 pr-1 md:pr-4 py-1 rounded-full hover:bg-gray-50 dark:hover:bg-white/5 transition-colors cursor-pointer group">
                                <div
                                    class="relative size-8 md:size-9 rounded-full overflow-hidden border border-custom-stroke dark:border-white/10 shrink-0">
                                    <img :src="user.profile_picture" class="size-full object-cover" alt="avatar">
                                </div>
                                <div class="flex flex-col items-start gap-0.5 max-w-[100px] hidden md:flex">
                                    <p
                                        class="text-xs md:text-sm font-bold text-custom-black truncate w-full group-hover:text-custom-blue dark:group-hover:text-blue-400 transition-colors text-left max-w-[90px]">
                                        {{ user.name?.split(' ')[0] }}
                                    </p>
                                </div>
                            </button>

                            <!-- Dropdown Menu -->
                            <transition enter-active-class="transition ease-out duration-200"
                                enter-from-class="transform opacity-0 translate-y-2"
                                enter-to-class="transform opacity-100 translate-y-0"
                                leave-active-class="transition ease-in duration-150"
                                leave-from-class="transform opacity-100 translate-y-0"
                                leave-to-class="transform opacity-0 translate-y-2">

                                <div v-show="showDropdownProfile"
                                    class="absolute top-[calc(100%-5px)] right-0 w-[280px] bg-white dark:bg-surface-card rounded-xl shadow-[0_8px_30px_rgb(0,0,0,0.12)] border border-custom-stroke dark:border-white/5 overflow-hidden ring-1 ring-black/5">

                                    <!-- Header -->
                                    <div
                                        class="flex items-center gap-3 p-4 bg-gray-50 dark:bg-white/5 border-b border-custom-stroke dark:border-white/5">
                                        <div
                                            class="size-10 rounded-full overflow-hidden border border-white dark:border-white/10 shadow-sm shrink-0">
                                            <img :src="user.profile_picture" class="size-full object-cover"
                                                alt="avatar">
                                        </div>
                                        <div class="flex flex-col overflow-hidden">
                                            <p class="font-bold text-custom-black truncate">{{ user.name }}</p>
                                            <div class="flex items-center gap-1">
                                                <img src="@/assets/images/icons/verify-star.svg" class="size-3"
                                                    alt="verified">
                                                <p class="text-xs text-custom-grey">Verified Account</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Menu Items -->
                                    <div class="p-2 flex flex-col gap-1">

                                        <!-- Section 1: Buying Activity -->
                                        <div
                                            class="flex flex-col gap-1 pb-2 border-b border-custom-stroke dark:border-white/5 border-dashed mb-2">
                                            <p
                                                class="px-3 text-[10px] font-bold text-custom-grey uppercase tracking-wider mb-1 mt-2">
                                                My Activity</p>

                                            <!-- Wishlist (Moved here) -->
                                            <RouterLink :to="{ name: 'app.wishlist' }"
                                                class="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-white/5 hover-glow-blue transition-colors group">
                                                <div class="flex items-center gap-3">
                                                    <img src="@/assets/images/icons/heart-black.svg"
                                                        class="size-5 dark:brightness-0 dark:invert opacity-70 dark:opacity-100 group-hover:opacity-100 transition-opacity"
                                                        alt="wishlist">
                                                    <span class="text-sm font-medium text-custom-black">Wishlist</span>
                                                </div>
                                                <span v-if="totalWishlistItems > 0"
                                                    class="text-[10px] font-bold text-white bg-custom-red px-1.5 py-0.5 rounded-full">{{
                                                        totalWishlistItems }}</span>
                                            </RouterLink>

                                            <!-- Transactions -->
                                            <RouterLink
                                                :to="{ name: 'user.my-transaction', params: { username: user.username } }"
                                                class="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-white/5 hover-glow-blue transition-colors group">
                                                <div class="flex items-center gap-3">
                                                    <img src="@/assets/images/icons/receipt-text-black.svg"
                                                        class="size-5 dark:brightness-0 dark:invert opacity-70 dark:opacity-100 group-hover:opacity-100 transition-opacity"
                                                        alt="tx">
                                                    <span class="text-sm font-medium text-custom-black">Transaction
                                                        List</span>
                                                </div>
                                            </RouterLink>
                                        </div>

                                        <!-- Section 2: Store / Seller -->
                                        <div
                                            class="flex flex-col gap-1 pb-2 border-b border-custom-stroke dark:border-white/5 border-dashed mb-2">
                                            <p
                                                class="px-3 text-[10px] font-bold text-custom-grey uppercase tracking-wider mb-1">
                                                Store</p>

                                            <RouterLink
                                                :to="{ name: user.role === 'store' ? 'admin.dashboard' : 'auth.open-store' }"
                                                class="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-white/5 hover-glow-blue transition-colors group"
                                                @click="authStore.setMode(user.role === 'store' ? 'store' : 'buyer')">
                                                <div class="flex items-center gap-3">
                                                    <img src="@/assets/images/icons/shop-black.svg"
                                                        class="size-5 dark:brightness-0 dark:invert opacity-70 dark:opacity-100 group-hover:opacity-100 transition-opacity"
                                                        alt="shop">
                                                    <span class="text-sm font-medium text-custom-black">{{ user.role ===
                                                        'store' ? 'Store Dashboard' : 'Open Store for Free' }}</span>
                                                </div>
                                                <span class="size-2 rounded-full bg-custom-green"
                                                    v-if="user.role === 'store'"></span>
                                            </RouterLink>
                                        </div>

                                        <!-- Section 3: Settings & Theme -->
                                        <div class="flex flex-col gap-1">
                                            <p
                                                class="px-3 text-[10px] font-bold text-custom-grey uppercase tracking-wider mb-1">
                                                Settings</p>

                                            <!-- Edit Profile -->
                                            <RouterLink
                                                :to="{ name: 'user.edit-profile', params: { username: user.username } }"
                                                class="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-white/5 hover-glow-blue transition-colors group">
                                                <div class="flex items-center gap-3">
                                                    <img src="@/assets/images/icons/setting-black.svg"
                                                        class="size-5 dark:brightness-0 dark:invert opacity-70 dark:opacity-100 group-hover:opacity-100 transition-opacity"
                                                        alt="settings">
                                                    <span class="text-sm font-medium text-custom-black">Account
                                                        Settings</span>
                                                </div>
                                            </RouterLink>

                                            <!-- Theme Toggle (Inside Menu)-->
                                            <button @click="toggleTheme()"
                                                class="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-white/5 hover-glow-blue transition-colors group w-full text-left">
                                                <div class="flex items-center gap-3">
                                                    <div class="size-5 flex items-center justify-center">
                                                        <svg v-if="effectiveTheme === 'dark'"
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            class="size-4 text-custom-black dark:text-white opacity-70 group-hover:opacity-100"
                                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                                        </svg>
                                                        <svg v-else xmlns="http://www.w3.org/2000/svg"
                                                            class="size-5 text-custom-black opacity-70 group-hover:opacity-100"
                                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                                        </svg>
                                                    </div>
                                                    <span class="text-sm font-medium text-custom-black">Theme
                                                        Mode</span>
                                                </div>
                                                <div
                                                    class="flex items-center text-xs font-bold text-custom-grey bg-gray-100 dark:bg-white/10 px-2 py-1 rounded">
                                                    {{ effectiveTheme === 'dark' ? 'Dark' : 'Light' }}
                                                </div>
                                            </button>

                                            <!-- Logout -->
                                            <button @click="logout"
                                                class="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors group w-full text-left mt-1">
                                                <div class="flex items-center gap-3">
                                                    <img src="@/assets/images/icons/logout.svg"
                                                        class="size-5 opacity-70 dark:opacity-100 group-hover:opacity-100 transition-opacity"
                                                        alt="logout">
                                                    <span class="text-sm font-bold text-custom-red">Logout</span>
                                                </div>
                                            </button>
                                        </div>
                                    </div>

                                </div>
                            </transition>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>