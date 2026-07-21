<script setup>
import { ref, computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import Sidebar from '@/components/admin/Sidebar.vue'
import BuyerSidebar from '@/components/admin/BuyerSidebar.vue'
import Topbar from '@/components/admin/Topbar.vue'

const isSidebarOpen = ref(false)

const authStore = useAuthStore()
const { user, activeMode } = storeToRefs(authStore)

// Buyer punya sidebar sendiri (menu berbeda total dari seller/admin) —
// shell tetap satu komponen supaya route `/:username/*` tidak perlu dipecah.
const isBuyerShell = computed(() => user.value?.role !== 'admin' && activeMode.value === 'buyer')
</script>

<template>
  <div id="main-container" class="flex h-screen w-full bg-custom-background dark:bg-surface overflow-hidden">
    <BuyerSidebar v-if="isBuyerShell" :is-open="isSidebarOpen" @close="isSidebarOpen = false" />
    <Sidebar v-else :is-open="isSidebarOpen" @close="isSidebarOpen = false" />
    <div id="Content" class="flex flex-col flex-1 h-full min-w-0 overflow-hidden">
      <!-- Topbar Container with padding to match design -->
      <div class="px-4 pt-0 shrink-0 md:px-6">
        <Topbar @toggle-sidebar="isSidebarOpen = !isSidebarOpen" />
      </div>

      <!-- Main Content Area: Scrollable -->
      <main class="flex flex-col gap-5 flex-1 overflow-y-auto p-4 pt-0 md:p-6 md:pt-0">
        <RouterView />
      </main>
    </div>
  </div>
</template>
