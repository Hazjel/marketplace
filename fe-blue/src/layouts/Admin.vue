<script setup>
import { ref } from 'vue'
import Sidebar from '@/components/admin/Sidebar.vue'
import Topbar from '@/components/admin/Topbar.vue'

const isSidebarOpen = ref(false)
</script>

<template>
  <div id="main-container" class="flex h-screen w-full bg-custom-background dark:bg-gray-900 overflow-hidden">
    <Sidebar :is-open="isSidebarOpen" @close="isSidebarOpen = false" />
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
