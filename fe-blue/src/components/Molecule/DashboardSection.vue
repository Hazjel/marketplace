<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  link: { type: [String, Object], default: null },
  linkText: { type: String, default: 'Lihat Semua' }
})
</script>

<template>
  <div
    class="flex flex-col w-full rounded-2xl p-6 gap-6 bg-white dark:bg-gray-900 border border-gray-100 dark:border-white/10 shadow-sm"
  >
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h3 class="font-medium text-xl text-gray-900 dark:text-white">{{ title }}</h3>
        <p v-if="subtitle" class="text-gray-500 dark:text-gray-400 text-sm">{{ subtitle }}</p>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <slot name="actions" />
        <RouterLink v-if="link" :to="link" class="text-sm font-medium text-[#024ad8] hover:underline">
          {{ linkText }}
        </RouterLink>
      </div>
    </div>
    <slot />
  </div>
</template>
