<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  size: { type: String, default: 'md' } // 'sm' | 'md'
})
</script>

<template>
  <div
    class="flex flex-col items-center justify-center text-center gap-3"
    :class="size === 'sm' ? 'py-8' : 'py-12'"
  >
    <div
      class="flex items-center justify-center rounded-full bg-gray-100 dark:bg-white/5"
      :class="size === 'sm' ? 'size-12' : 'size-20'"
    >
      <slot name="icon">
        <svg
          class="text-gray-400"
          :class="size === 'sm' ? 'size-6' : 'size-10'"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="1.5"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      </slot>
    </div>
    <div>
      <p class="font-medium text-custom-black dark:text-white" :class="size === 'sm' ? 'text-sm' : 'text-lg'">
        {{ title }}
      </p>
      <p v-if="subtitle" class="text-custom-grey dark:text-gray-400 text-sm mt-1">{{ subtitle }}</p>
    </div>
  </div>
</template>
