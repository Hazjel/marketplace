<script setup>
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useChatStore } from '@/stores/chat'
import { useRouter } from 'vue-router'
import SidebarItem from '@/components/admin/sidebar/SidebarItem.vue'
import HomeBlackIcon from '@/assets/images/icons/home-black.svg'
import HomeBlueFillIcon from '@/assets/images/icons/home-blue-fill.svg'
import StickyNoteGreyIcon from '@/assets/images/icons/stickynote-grey.svg'
import StickyNoteBlackIcon from '@/assets/images/icons/stickynote-black.svg'
import StickyNoteBlueFillIcon from '@/assets/images/icons/stickynote-blue-fill.svg'
import HeartBlackIcon from '@/assets/images/icons/heart-black.svg'
import HeartRedIcon from '@/assets/images/icons/heart-red.svg'
import LocationGreyIcon from '@/assets/images/icons/location-grey.svg'
import ShopGreyIcon from '@/assets/images/icons/shop-grey.svg'
import ShopBlueFillIcon from '@/assets/images/icons/shop-blue-fill.svg'

const router = useRouter()
const authStore = useAuthStore()
const chatStore = useChatStore()
const { user } = storeToRefs(authStore)
const { totalUnreadCount } = storeToRefs(chatStore)

const prefix = computed(() => (user.value ? `/${user.value.username}` : ''))

const items = computed(() => [
  {
    label: 'Overview',
    path: `${prefix.value}/dashboard`,
    iconDefault: HomeBlackIcon,
    iconActive: HomeBlueFillIcon,
    permission: 'dashboard-menu'
  },
  {
    label: 'Pesanan Saya',
    path: `${prefix.value}/my-transactions`,
    iconDefault: StickyNoteBlackIcon,
    iconActive: StickyNoteBlueFillIcon,
    permission: 'transaction-menu'
  },
  {
    label: 'Wishlist',
    path: '/wishlist',
    iconDefault: HeartBlackIcon,
    iconActive: HeartRedIcon,
    permission: 'dashboard-menu'
  },
  {
    label: 'Alamat Saya',
    path: `${prefix.value}/settings/address`,
    iconDefault: LocationGreyIcon,
    iconActive: LocationGreyIcon,
    permission: 'dashboard-menu'
  }
])

const settingsItems = computed(() => [
  {
    label: 'Edit Profil',
    path: `${prefix.value}/edit-profile`,
    iconDefault: LocationGreyIcon,
    iconActive: LocationGreyIcon,
    permission: 'dashboard-menu'
  },
  {
    label: 'Notifikasi',
    path: `${prefix.value}/settings/notifications`,
    iconDefault: LocationGreyIcon,
    iconActive: LocationGreyIcon,
    permission: 'dashboard-menu'
  },
  {
    label: 'Privasi',
    path: `${prefix.value}/settings/privacy`,
    iconDefault: LocationGreyIcon,
    iconActive: LocationGreyIcon,
    permission: 'dashboard-menu'
  }
])

const chatLink = computed(() => ({
  label: 'Messages',
  path: `${prefix.value}/chat`,
  iconDefault: StickyNoteGreyIcon,
  iconActive: StickyNoteBlueFillIcon,
  permission: 'dashboard-menu',
  badge: totalUnreadCount.value
}))

const homeLink = {
  label: 'Back to Homepage',
  path: '/',
  iconDefault: HomeBlackIcon,
  iconActive: HomeBlueFillIcon,
  permission: 'dashboard-menu'
}

// Muncul cuma buat user yang sudah py toko — buyer-shell selalu di
// buyer-mode, jadi tak perlu cek activeMode di sini.
const sellerSwitchLink = {
  label: 'Switch to Seller Mode',
  path: null,
  iconDefault: ShopGreyIcon,
  iconActive: ShopBlueFillIcon,
  permission: 'dashboard-menu'
}

const handleSwitchToSeller = () => {
  authStore.switchToMode(router, 'store')
}

const handleLogout = async () => {
  const success = await authStore.logout()
  if (success) {
    const cartStore = useCartStore()
    cartStore.onLogout()
    router.push({ name: 'auth.login' })
  }
}
</script>

<template>
  <div v-if="user" class="flex flex-col h-full pt-[30px] px-4 gap-[30px] bg-white dark:bg-surface-card">
    <div class="flex items-center justify-between">
      <img
        src="@/assets/images/logos/blukios_logo.png"
        class="h-8 w-fit cursor-pointer"
        alt="logo"
        @click="router.push({ name: 'app.home' })"
      />
      <slot name="close-button"></slot>
    </div>

    <div class="flex flex-col gap-5 overflow-y-scroll hide-scrollbar h-full overscroll-contain flex-1">
      <nav class="flex flex-col gap-4 animate-fade-in-up">
        <p class="font-medium text-custom-grey dark:text-gray-400">Main Menu</p>
        <ul class="flex flex-col gap-2">
          <SidebarItem v-for="(item, index) in items" :key="index" :item="item" />
        </ul>
      </nav>

      <nav class="flex flex-col gap-4 animate-fade-in-up">
        <p class="font-medium text-custom-grey dark:text-gray-400">Settings</p>
        <ul class="flex flex-col gap-2">
          <SidebarItem v-for="(item, index) in settingsItems" :key="index" :item="item" />
        </ul>
      </nav>
    </div>

    <div class="pb-8 animate-fade-in-up delay-100">
      <ul class="flex flex-col gap-2">
        <SidebarItem :item="chatLink" />
        <SidebarItem
          v-if="user?.role === 'store'"
          :item="sellerSwitchLink"
          @click="handleSwitchToSeller" />
        <SidebarItem :item="homeLink" />
        <li class="list-none">
          <button
            class="flex items-center gap-3 px-4 py-3 rounded-[10px] w-full transition-all duration-300 hover:bg-custom-background dark:hover:bg-white/5"
            @click="handleLogout"
          >
            <img
              src="@/assets/images/icons/logout.svg"
              class="size-6 text-custom-red svg-red filter-red"
              alt="icon"
            />
            <span class="font-medium text-custom-red">Logout</span>
          </button>
        </li>
      </ul>
    </div>
  </div>
  <div v-else class="flex items-center justify-center h-screen">
    <span class="text-custom-grey dark:text-gray-400 text-xl">Loading menu...</span>
  </div>
</template>
