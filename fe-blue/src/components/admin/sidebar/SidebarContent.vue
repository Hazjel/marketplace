<script setup>
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useChatStore } from '@/stores/chat'
import SidebarItem from '@/components/admin/sidebar/SidebarItem.vue'
import HomeBlackIcon from '@/assets/images/icons/home-black.svg'
import HomeBlueFillIcon from '@/assets/images/icons/home-blue-fill.svg'
import BoxBlackIcon from '@/assets/images/icons/box-black.svg'
import BagGreyIcon from '@/assets/images/icons/bag-grey.svg'
import BagBlueFillIcon from '@/assets/images/icons/bag-blue-fill.svg'
import Bag2BlackIcon from '@/assets/images/icons/bag-2-black.svg'
import ShopGreyIcon from '@/assets/images/icons/shop-grey.svg'
import ShopBlueFillIcon from '@/assets/images/icons/shop-blue-fill.svg'
import StickyNoteGreyIcon from '@/assets/images/icons/stickynote-grey.svg'
import StickyNoteBlueFillIcon from '@/assets/images/icons/stickynote-blue-fill.svg'
import Wallet2BlackIcon from '@/assets/images/icons/wallet-2-black.svg'
import EmpyWalletGreyIcon from '@/assets/images/icons/empty-wallet-grey.svg'
import Wallet3BlueFillIcon from '@/assets/images/icons/wallet-3-blue-fill.svg'
import User2BlackIcon from '@/assets/images/icons/profile-2user-black.svg'
import User2BlueIcon from '@/assets/images/icons/profile-2user-blue-fill.svg'
import LocationGreyIcon from '@/assets/images/icons/location-grey.svg'
import GlobalSearchIcon from '@/assets/images/icons/global-search-grey.svg'

import { useRouter } from 'vue-router'
const router = useRouter()
const authStore = useAuthStore()
const chatStore = useChatStore()
const { user } = storeToRefs(authStore)
const { totalUnreadCount } = storeToRefs(chatStore)

const prefix = computed(() => {
  if (!user.value) return '';
  if (user.value?.role === 'admin') return '/admin';
  return `/${user.value?.username}`;
});


const items = computed(() => {
    const mode = authStore.activeMode;
    const allItems = [
    {
        label: 'Overview',
        path: `${prefix.value}/dashboard`,
        iconDefault: HomeBlackIcon,
        iconActive: HomeBlueFillIcon,
        permission: 'dashboard-menu'
    },
    {
        label: 'My Transactions',
        path: `${prefix.value}/my-transactions`,
        iconDefault: StickyNoteGreyIcon,
        iconActive: StickyNoteBlueFillIcon,
        permission: 'transaction-menu',
        role: ['buyer', 'store']
    },
    {
        label: 'My Addresses',
        path: `${prefix.value}/settings/address`,
        iconDefault: LocationGreyIcon,
        iconActive: LocationGreyIcon,
        permission: 'dashboard-menu',
        role: ['buyer']
    },
    {
        label: 'Manage Product',
        iconDefault: BoxBlackIcon,
        children: [
            {
                label: 'Categories',
                path: `${prefix.value}/category`,
                iconDefault: BagGreyIcon,
                iconActive: BagBlueFillIcon,
                permission: 'product-category-menu'
            },
            {
                label: 'Products',
                path: `${prefix.value}/product`,
                iconDefault: BagGreyIcon,
                iconActive: BagBlueFillIcon,
                permission: 'product-menu'
            }
        ],
        mode: 'store'
    },
    {
        label: 'Incoming Orders',
        iconDefault: BagGreyIcon, 
        iconActive: BagBlueFillIcon,
        permission: 'transaction-list', // Check permission
        role: ['store'],
        path: `${prefix.value}/orders/incoming`,
        mode: 'store'
    },
    {
        label: 'Manage Store',
        iconDefault: Bag2BlackIcon,
        children: [
            {
                label: 'List Store',
                path: `${prefix.value}/store`,
                iconDefault: ShopGreyIcon,
                iconActive: ShopBlueFillIcon,
                permission: 'store-menu',
                role: 'admin'
            },
            {
                label: 'My Store',
                path: `${prefix.value}/my-store`,
                iconDefault: EmpyWalletGreyIcon,
                iconActive: Wallet3BlueFillIcon,
                permission: 'store-menu',
                role: 'store'
            },
            {
                label: 'List Transaction',
                path: `${prefix.value}/transaction`,
                iconDefault: StickyNoteGreyIcon,
                iconActive: StickyNoteBlueFillIcon,
                permission: 'transaction-menu',
                role: 'admin'
            },
            {
                label: 'List Transaction',
                path: `${prefix.value}/transaction`,
                iconDefault: StickyNoteGreyIcon,
                iconActive: StickyNoteBlueFillIcon,
                permission: 'transaction-menu',
                role: 'store'
            }
        ],
        mode: 'store'
    },
    {
        label: 'Manage Wallet',
        iconDefault: Wallet2BlackIcon,
        children: [
            {
                label: 'Store Wallet',
                path: `${prefix.value}/store-balance`,
                iconDefault: EmpyWalletGreyIcon,
                iconActive: Wallet3BlueFillIcon,
                permission: 'store-balance-menu',
                role: 'admin'
            },
            {
                label: 'My Wallet',
                path: `${prefix.value}/my-store-balance`,
                iconDefault: EmpyWalletGreyIcon,
                iconActive: Wallet3BlueFillIcon,
                permission: 'store-balance-menu',
                role: 'store'
            },
            {
                label: 'Withdrawal',
                path: `${prefix.value}/withdrawal`,
                iconDefault: EmpyWalletGreyIcon,
                iconActive: Wallet3BlueFillIcon,
                permission: 'withdrawal-menu',
                role: ['store', 'admin']
            },
        ],
        mode: 'store' 
    },
    {
        label: 'Manage Users',
        path: `${prefix.value}/user`,
        iconDefault: User2BlackIcon,
        iconActive: User2BlueIcon,
        permission: 'user-menu'
    },
    ];

    return allItems.filter(item => {
        if (item.mode && item.mode !== mode && user.value?.role !== 'admin') {
            return false;
        }
        return true;
    });
})

const marketplaceLink = computed(() => {
    const isBuyerMode = authStore.activeMode === 'buyer'
    return {
        label: isBuyerMode ? 'Back to Homepage' : 'Back to Buyer Mode',
        path: isBuyerMode ? '/' : null,
        iconDefault: isBuyerMode ? HomeBlackIcon : GlobalSearchIcon,
        iconActive: isBuyerMode ? HomeBlueFillIcon : GlobalSearchIcon,
        permission: 'dashboard-menu'
    }
})

const chatLink = computed(() => ({
    label: 'Messages',
    path: `${prefix.value}/chat`,
    iconDefault: StickyNoteGreyIcon,
    iconActive: StickyNoteBlueFillIcon,
    permission: 'dashboard-menu',
    badge: totalUnreadCount.value
}))

const handleSwitchMode = () => {
    if (authStore.activeMode === 'store') {
        authStore.setMode('buyer')
        router.push({ name: 'app.home' })
    }
}

const handleLogout = async () => {
    const success = await authStore.logout()
    if (success) {
        router.push({ name: 'auth.login' })
    }
}
</script>

<template>
  <div v-if="user" class="flex flex-col h-full pt-[30px] px-4 gap-[30px] bg-white">
    <!-- original sidebar content starts here -->
    <div class="flex flex-col h-full pt-[30px] px-4 gap-[30px] bg-white">
        <div class="flex items-center justify-between">
            <img src="@/assets/images/logos/blukios_logo.png" class="h-8 w-fit cursor-pointer" alt="logo"
                @click="router.push({ name: 'app.home' })" />
            <!-- Close Button Slot (Optional) -->
            <slot name="close-button"></slot>
        </div>

        <div class="flex flex-col gap-5 overflow-y-scroll hide-scrollbar h-full overscroll-contain flex-1">
            <nav class="flex flex-col gap-4 animate-fade-in-up">
                <p class="font-medium text-custom-grey">Main Menu</p>
                <ul class="flex flex-col gap-2">
                    <SidebarItem v-for="(item, index) in items" :key="index" :item="item" />
                </ul>
            </nav>
        </div>

        <div class="pb-8 animate-fade-in-up delay-100">
            <ul class="flex flex-col gap-2">
                <SidebarItem :item="chatLink" v-if="user?.role !== 'admin'" />
                <SidebarItem :item="marketplaceLink" v-if="user?.role !== 'admin'" @click="handleSwitchMode" />
                <!-- Logout Button -->
                <li class="list-none">
                    <button @click="handleLogout"
                        class="flex items-center gap-3 px-4 py-3 rounded-[10px] w-full transition-all duration-300 hover:bg-custom-background">
                        <img src="@/assets/images/icons/logout.svg" class="size-6 text-custom-red svg-red filter-red" alt="icon">
                        <span class="font-medium text-custom-red">Logout</span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
    <!-- original sidebar content ends here -->
  </div>
  <div v-else class="flex items-center justify-center h-screen">
    <span class="text-custom-grey text-xl">Loading menu...</span>
  </div>
</template>
