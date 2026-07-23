<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const router = useRouter()

onMounted(async () => {
  if (authStore.token) {
    if (!authStore.user) {
      try {
        await authStore.checkAuth()
      } catch (e) {
        // Token tidak valid, biarkan tetap di halaman auth
      }
    }

    const isSellerBuild = import.meta.env.VITE_APP_TARGET === 'seller'
    const isSellerRole = authStore.user?.role === 'admin' || authStore.user?.role === 'store'

    if (isSellerRole && !isSellerBuild) {
      // Dashboard toko/admin tidak ada di build buyer -- SSO ke seller app.
      await authStore.initiateSso(import.meta.env.VITE_SELLER_APP_URL)
    } else if (!isSellerRole && isSellerBuild) {
      // Marketplace tidak ada di build seller -- SSO balik ke buyer app.
      await authStore.initiateSso(import.meta.env.VITE_BUYER_APP_URL)
    } else if (authStore.user?.role === 'admin') {
      router.replace({ name: 'admin.dashboard' })
    } else if (authStore.user) {
      router.replace({ name: 'user.dashboard', params: { username: authStore.user.username } })
    }
  }
})
</script>

<template>
  <main
    class="flex min-h-screen w-full font-sans text-custom-black bg-white dark:bg-surface dark:text-white"
  >
    <!-- Left Side: Brand panel (ink slab + HP-style blue chevrons, no photography) -->
    <div
      class="hidden lg:flex w-[50%] h-screen sticky top-0 flex-col justify-between p-16 bg-[#1a1a1a] text-white overflow-hidden"
    >
      <!-- Signature blue chevron decorations -->
      <div
        class="pointer-events-none absolute -right-24 top-24 h-[420px] w-40 bg-primary -skew-x-12 opacity-90"
      ></div>
      <div
        class="pointer-events-none absolute right-16 top-24 h-[420px] w-24 bg-primary-bright -skew-x-12 opacity-40"
      ></div>

      <img
        src="@/assets/images/logos/blukios_logo.png"
        class="relative h-9 w-fit brightness-0 invert"
        alt="Blukios"
      />

      <div class="relative flex flex-col gap-4 max-w-md">
        <h2 class="text-4xl font-medium leading-tight">
          Marketplace untuk gadget & elektronik.
        </h2>
        <p class="text-base text-white/60 leading-relaxed">
          Belanja aman, jual mudah. Semua kebutuhan digitalmu di satu tempat.
        </p>
      </div>

      <p class="relative text-sm text-white/40">© Blukios</p>
    </div>

    <!-- Right Side: Form Container -->
    <div
      class="flex w-full lg:w-[50%] min-h-screen items-center justify-center p-6 lg:p-24 relative overflow-y-auto bg-white dark:bg-surface"
    >
      <div class="w-full max-w-[480px]">
        <RouterView v-slot="{ Component }">
          <Transition name="auth-fade" mode="out-in">
            <component :is="Component" />
          </Transition>
        </RouterView>
      </div>
    </div>
  </main>
</template>

<style scoped>
/* Smooth crossfade + subtle rise when switching auth pages (login <-> register, dst) */
.auth-fade-enter-active {
  transition:
    opacity 260ms ease-out,
    transform 260ms ease-out;
}

.auth-fade-leave-active {
  transition:
    opacity 160ms ease-in,
    transform 160ms ease-in;
}

.auth-fade-enter-from {
  opacity: 0;
  transform: translateY(8px);
}

.auth-fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

@media (prefers-reduced-motion: reduce) {
  .auth-fade-enter-active,
  .auth-fade-leave-active {
    transition: none;
  }
  .auth-fade-enter-from,
  .auth-fade-leave-to {
    transform: none;
  }
}
</style>
