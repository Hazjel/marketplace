<script setup>
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
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
import GlobalSearchIcon from '@/assets/images/icons/global-search-grey.svg'
import router from '@/router'

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const prefix = computed(() => {
    if (user.value?.role === 'admin') return '/admin'
    return `/${user.value?.username}`
})

const items = computed(() => [
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
        role: 'buyer'
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
        ]
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
        ]
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
                permission: 'withdrawal-menu'
            },
        ]
    },
    {
        label: 'Manage Users',
        path: `${prefix.value}/user`,
        iconDefault: User2BlackIcon,
        iconActive: User2BlueIcon,
        permission: 'user-menu'
    },
])

const marketplaceLink = {
    label: 'Back to Marketplace',
    path: '/',
    iconDefault: GlobalSearchIcon,
    iconActive: GlobalSearchIcon,
    permission: 'dashboard-menu'
}
</script>

<template>
    <aside class="relative flex h-auto w-[280px] shrink-0 bg-white">
        <div class="flex flex-col fixed top-0 w-[280px] shrink-0 h-screen pt-[30px] px-4 gap-[30px] bg-white">
            <img src="@/assets/images/logos/logo.svg" class="h-8 w-fit cursor-pointer" alt="logo"
                @click="router.push({ name: 'app.home' })" />
            <div class="flex flex-col gap-5 overflow-y-scroll hide-scrollbar h-full overscroll-contain flex-1">
                <nav class="flex flex-col gap-4">
                    <p class="font-medium text-custom-grey">Main Menu</p>
                    <ul class="flex flex-col gap-2">
                        <SidebarItem v-for="(item, index) in items" :key="index" :item="item" />
                    </ul>
                </nav>
            </div>

            <div class="pb-8">
                <ul class="flex flex-col">
                    <SidebarItem :item="marketplaceLink" />
                </ul>
            </div>
        </div>
    </aside>
</template>