<script setup>
import { computed } from 'vue'

const props = defineProps({
  product: {
    type: Object,
    required: true,
    default: () => ({})
  }
})

const specs = computed(() => [
  { label: 'Kondisi', value: props.product?.condition },
  { label: 'Berat Satuan', value: props.product?.weight ? `${props.product.weight} kg` : '-' },
  {
    label: 'Kategori',
    value: props.product?.product_category?.name,
    isLink: true,
    slug: props.product?.product_category?.slug
  },
  { label: 'Etalase', value: 'Semua Etalase', isLink: true } // Placeholder for now
])
</script>

<template>
  <div class="flex flex-col gap-4">
    <h3 class="font-bold text-lg">Spesifikasi Produk</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3">
      <div
        v-for="(spec, index) in specs"
        :key="index"
        class="flex flex-col sm:flex-row sm:items-baseline border-b border-gray-50 pb-2 last:border-0 last:pb-0"
      >
        <span class="text-sm text-custom-grey w-32 shrink-0">{{ spec.label }}</span>
        <RouterLink
          v-if="spec.isLink && spec.slug"
          :to="{ name: 'app.browse-category', params: { slug: spec.slug } }"
          class="text-sm font-medium text-custom-blue hover:underline text-left"
        >
          {{ spec.value }}
        </RouterLink>
        <span
          v-else-if="spec.isLink"
          class="text-sm font-medium text-custom-blue cursor-pointer hover:underline text-left"
        >
          {{ spec.value }}
        </span>
        <span v-else class="text-sm font-medium text-custom-black text-left">{{ spec.value }}</span>
      </div>
    </div>
  </div>
</template>
