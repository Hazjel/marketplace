<script setup>
import logo from '@/assets/images/logos/blukios_logo.svg'
import logoWhite from '@/assets/images/logos/blukios_logo_white.svg'

// Lockup navy hanya terbaca di latar terang; mode gelap memakai versi teks putih.
defineOptions({ inheritAttrs: false })
</script>

<template>
  <img :src="logo" alt="Blukios" v-bind="$attrs" class="dark:hidden" />
  <img :src="logoWhite" alt="Blukios" v-bind="$attrs" class="hidden dark:block" />
</template>
