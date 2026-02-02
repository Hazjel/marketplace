<script setup>
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'

const route = useRoute()
const router = useRouter()

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)
const { logout } = authStore

const handleProfileClick = () => {
  if (user.value?.role === 'admin') {
    router.push({ name: 'admin.edit-profile' })
  } else {
    router.push({ name: 'user.edit-profile', params: { username: user.value.username } })
  }
}
</script>

<template>
  <div id="Top-Bar" class="flex items-center w-full gap-4 md:gap-6 mt-5 mb-5 md:mt-8 md:mb-6">
    <!-- Sidebar Toggle (Mobile) -->
    <button
      class="flex md:hidden items-center justify-center size-14 shrink-0 bg-white rounded-3xl"
      @click="$emit('toggleSidebar')"
    >
      <img src="@/assets/images/icons/menu-grey.svg" class="size-6" alt="menu" />
    </button>

    <div class="flex items-center gap-6 min-h-[102px] h-auto bg-white w-full rounded-3xl p-[18px]">
      <div class="flex flex-col gap-2 w-full">
        <h1 class="font-bold text-2xl capitalize">{{ route.meta.title }}</h1>
        <p class="flex items-center gap-1 font-semibold text-custom-grey leading-none">
          View Your {{ route.meta.title }}
        </p>
      </div>
    </div>

    <!-- Desktop User Profile (Flat Card - Click to Edit) -->
    <div
      class="hidden md:flex items-center gap-3 min-h-[102px] h-auto bg-white min-w-[260px] rounded-3xl p-[18px] cursor-pointer hover:bg-slate-50 transition-colors"
      @click="handleProfileClick"
    >
      <div class="flex items-center gap-3 overflow-hidden">
        <div class="flex rounded-full overflow-hidden size-14 shrink-0 border border-slate-100">
          <img
            :src="user?.profile_picture || '@/assets/images/placeholder-avatar.png'"
            class="size-full object-cover"
            alt="photo"
            onerror="this.src = 'https://ui-avatars.com/api/?name=User&background=random'"
          />
        </div>
        <div class="flex flex-col gap-[6px] text-left">
          <p class="font-semibold text-lg leading-tight line-clamp-1">
            {{ user?.name || 'Loading...' }}
          </p>
          <p class="font-bold text-custom-blue text-sm uppercase leading-none">
            {{ user ? (user.role === 'admin' ? 'ADMIN' : authStore.currentMode) : '...' }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
