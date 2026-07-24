<script setup>
import { RouterLink } from 'vue-router'
import Container from '@/components/Molecule/Container.vue'

// Hero halaman ala HP: slab ink #1a1a1a + motif chevron biru HP.
// Ganti semua hero gradient warna-warni (ungu/cyan/emerald/pink) di listing pages
// dengan satu bahasa visual yang konsisten.
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  // Array breadcrumb: [{ label, to }] — item terakhir tanpa `to` = halaman aktif.
  breadcrumb: { type: Array, default: () => [] }
})
</script>

<template>
  <!-- bg pakai hex eksplisit #1a1a1a supaya tetap gelap di dark mode
       (token custom-black flip jadi terang di dark) -->
  <div class="relative overflow-hidden bg-[#1a1a1a] text-white">
    <!-- Chevron dekorasi biru HP (signature), hilang di mobile -->
    <div
      class="pointer-events-none absolute -right-16 -top-8 hidden h-[140%] w-32 -skew-x-12 bg-primary opacity-90 md:block"
    ></div>
    <div
      class="pointer-events-none absolute right-24 -top-8 hidden h-[140%] w-20 -skew-x-12 bg-primary-bright opacity-40 md:block"
    ></div>

    <Container class="relative py-10 md:py-16">
      <!-- Breadcrumb -->
      <nav v-if="breadcrumb.length" class="mb-3 flex items-center gap-2 text-sm text-white/50">
        <template v-for="(item, i) in breadcrumb" :key="i">
          <RouterLink
            v-if="item.to"
            :to="item.to"
            class="transition-colors hover:text-white"
          >
            {{ item.label }}
          </RouterLink>
          <span v-else class="text-white">{{ item.label }}</span>
          <span v-if="i < breadcrumb.length - 1" class="text-white/30">/</span>
        </template>
      </nav>

      <h1 class="text-3xl font-medium leading-tight md:text-4xl">{{ title }}</h1>
      <p v-if="subtitle" class="mt-2 max-w-xl text-base text-white/60">{{ subtitle }}</p>
      <slot name="actions" />
    </Container>
  </div>
</template>
