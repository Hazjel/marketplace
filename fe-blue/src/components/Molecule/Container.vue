<script setup>
// Wrapper lebar konten seragam ala HP — satu sumber max-width + gutter.
// Ganti pola lama `max-w-[1280px] px-4 md:px-[52px]` yang diulang inline
// dengan gutter berbeda-beda di banyak view.
defineProps({
  as: { type: String, default: 'div' },
  class: { type: String, default: '' }
})
</script>

<template>
  <component :is="as" :class="['w-full max-w-[1366px] mx-auto px-4 md:px-8', $props.class]">
    <slot />
  </component>
</template>
