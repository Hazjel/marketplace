import { ref } from 'vue'

// Kelola opsi varian dinamis (mis. Warna, RAM) dan baris varian produk.
// Dipakai identik di ProductCreate.vue dan ProductEdit.vue.
export function useProductVariants(product) {
  const optionGroups = ref([])
  const newOptionName = ref('')

  const addOptionGroup = () => {
    if (newOptionName.value && !optionGroups.value.includes(newOptionName.value)) {
      optionGroups.value.push(newOptionName.value)
      newOptionName.value = ''
      product.value.variants.forEach((v) => {
        if (!v.variant_attributes) v.variant_attributes = {}
      })
    }
  }

  const addVariant = () => {
    const attributes = {}
    optionGroups.value.forEach((opt) => (attributes[opt] = ''))
    product.value.variants.push({
      name: '',
      price: product.value.price || 0,
      stock: 0,
      variant_attributes: attributes
    })
  }

  const autoGenerateName = (variant) => {
    if (variant.variant_attributes) {
      const parts = Object.values(variant.variant_attributes).filter((val) => val)
      variant.name = parts.length > 0 ? parts.join(' - ') : ''
    }
  }

  // Dipakai ProductEdit.vue saat memuat produk yang sudah punya varian:
  // deteksi has_variants + rekonstruksi optionGroups dari data existing.
  const loadExistingVariants = (variants) => {
    if (variants && variants.length > 0) {
      product.value.has_variants = true
      product.value.variants = variants.map((v) => ({
        ...v,
        variant_attributes: v.variant_attributes || {}
      }))

      const groups = new Set()
      product.value.variants.forEach((v) => {
        if (v.variant_attributes) {
          Object.keys(v.variant_attributes).forEach((k) => groups.add(k))
        }
      })
      optionGroups.value = Array.from(groups)
    } else {
      product.value.has_variants = false
      product.value.variants = []
    }
  }

  return {
    optionGroups,
    newOptionName,
    addOptionGroup,
    addVariant,
    autoGenerateName,
    loadExistingVariants
  }
}
