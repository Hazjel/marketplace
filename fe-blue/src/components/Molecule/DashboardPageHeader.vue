<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  // Contoh: 'from-indigo-600 to-blue-600'
  gradient: { type: String, default: 'from-indigo-600 to-blue-600' },
  // Warna teks subtitle, ikut keluarga warna gradient. Contoh: 'text-green-100'
  subtitleColor: { type: String, default: 'text-blue-100' }
})
</script>

<template>
  <div class="rounded-xl bg-gradient-to-r p-6 shadow-sm" :class="gradient">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <div class="flex size-12 items-center justify-center rounded-xl bg-white/20 shrink-0">
          <slot name="icon" />
        </div>
        <div>
          <h1 class="text-2xl font-medium text-white">{{ title }}</h1>
          <p v-if="subtitle" :class="subtitleColor">{{ subtitle }}</p>
        </div>
      </div>
      <slot name="actions" />
    </div>
  </div>
</template>
