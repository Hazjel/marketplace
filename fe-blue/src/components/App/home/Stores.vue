<script setup>
import { onMounted } from 'vue'
import { useStoreStore } from '@/stores/store'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'

const storeStore = useStoreStore()
const { stores, loading } = storeToRefs(storeStore)
const { fetchStores } = storeStore

onMounted(async () => {
  await fetchStores({
    limit: 6,
    random: true
  })
})
</script>

<template>
  <section class="flex flex-col gap-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-lg md:text-xl font-bold text-gray-900 dark:text-white">Toko Official</h2>
      <RouterLink
        :to="{ name: 'app.all-stores' }"
        class="text-sm font-semibold text-custom-blue dark:text-blue-400 hover:underline"
      >
        Lihat Semua
      </RouterLink>
    </div>

    <!-- Store Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 md:gap-4">
      <RouterLink
        v-for="store in stores"
        :key="store.id"
        :to="{ name: 'app.store-detail', params: { username: store.username } }"
        class="group flex flex-col items-center gap-3 p-4 bg-white dark:bg-surface-card rounded-xl border border-gray-100 dark:border-white/5 hover:border-custom-blue/20 dark:hover:border-blue-400/30 hover:shadow-md transition-all duration-200"
      >
        <!-- Logo -->
        <div class="size-14 md:size-16 rounded-full overflow-hidden bg-gray-100 dark:bg-white/10 border-2 border-gray-50 dark:border-white/5 group-hover:border-custom-blue/30 transition-colors">
          <img
            :src="store.logo"
            class="w-full h-full object-cover"
            loading="lazy"
            :alt="store.name"
          />
        </div>

        <!-- Info -->
        <div class="flex flex-col items-center gap-1 text-center w-full">
          <h3 class="text-sm font-semibold text-gray-800 dark:text-white truncate w-full group-hover:text-custom-blue dark:group-hover:text-blue-400 transition-colors">
            {{ store.name }}
          </h3>
          <div v-if="store.is_verified" class="flex items-center gap-1">
            <svg class="size-3 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <span class="text-[10px] font-bold text-blue-500 uppercase">Official</span>
          </div>
          <span class="text-xs text-gray-400 dark:text-gray-500">{{ store.city || 'Indonesia' }}</span>
        </div>
      </RouterLink>
    </div>
  </section>
</template>
