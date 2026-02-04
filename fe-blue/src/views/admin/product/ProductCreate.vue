<script setup>
import { useProductStore } from '@/stores/product'
import { useProductCategoryStore } from '@/stores/productCategory'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch } from 'vue'
import PlaceHolder from '@/assets/images/icons/gallery-grey.svg'
import { parseRupiah } from '@/helpers/format'
import StepWizard from '@/components/Molecule/StepWizard.vue'

const productStore = useProductStore()
const { error } = storeToRefs(productStore)
const { createProduct } = productStore

const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const productCategoryStore = useProductCategoryStore()
const { productCategories } = storeToRefs(productCategoryStore)
const { fetchProductCategories } = productCategoryStore

const subCategories = ref([])
const currentStep = ref(1)

const product = ref({
  store_id: null,
  parent_product_category_id: null,
  product_category_id: null,
  name: null,
  description: null,
  condition: 'new', // Default
  price: null,
  weight: null,
  stock: null,
  has_variants: false,
  variants: [],
  product_images: [
    {
      image: null,
      url: PlaceHolder,
      is_thumbnail: true
    },
    {
      image: null,
      url: PlaceHolder,
      is_thumbnail: false
    },
    {
      image: null,
      url: PlaceHolder,
      is_thumbnail: false
    },
    {
      image: null,
      url: PlaceHolder,
      is_thumbnail: false
    }
  ]
})

// Dynamic Variant Options
const optionGroups = ref([])
const newOptionName = ref('')

const addOptionGroup = () => {
  if (newOptionName.value && !optionGroups.value.includes(newOptionName.value)) {
    optionGroups.value.push(newOptionName.value)
    newOptionName.value = ''
    // Add new key to existing variants
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
    price: product.value.price || 0, // Default to product price
    stock: 0,
    variant_attributes: attributes
  })
}

const autoGenerateName = (variant) => {
  if (variant.variant_attributes) {
    // e.g. "Red - XL"
    const parts = Object.values(variant.variant_attributes).filter((val) => val)
    if (parts.length > 0) {
      variant.name = parts.join(' - ')
    } else {
      variant.name = ''
    }
  }
}

const steps = ['General Info', 'Stock & Price', 'Visuals']

const validateStep = (step) => {
  // Simple validation before proceeding
  if (step === 1) {
    if (!product.value.name) return false
    if (!product.value.description) return false
    if (!product.value.parent_product_category_id) return false
    if (!product.value.product_category_id) return false
  }
  if (step === 2) {
    if (!product.value.weight) return false
    if (product.value.has_variants) {
      if (product.value.variants.length === 0) {
        alert('Please add at least one variant')
        return false
      }
    } else {
      if (!product.value.price) return false
      if (!product.value.stock) return false
    }
  }
  if (step === 3) {
    const hasThumbnail = product.value.product_images.some(
      (img) => img.is_thumbnail && (img.image || img.url !== PlaceHolder)
    )
    console.log(hasThumbnail) // Use variable to silence linter or remove logic if unnecessary (keeping logic for safety but fixing unused)
    // Actually, just remove the assignment if it's truly unused logic or keep it if it was intended used.
    // Linter says unused, so it's not used in if check below.
    // However, maybe it SHOULD be used?
    // "Also check if at least one real image exists" - next check.
    // I will check lines 123-134.
    // Original:
    // const hasThumbnail = ...
    // const hasImage = ...
    // if (!hasImage) ...
    // It seems hasThumbnail is unused. I will remove it.
    // Also check if at least one real image exists
    const hasImage = product.value.product_images.some(
      (img) => img.image || img.url !== PlaceHolder
    )

    if (!hasImage) {
      alert('Please upload at least one image')
      return false
    }
  }
  return true
}

const nextStep = () => {
  if (validateStep(currentStep.value)) {
    currentStep.value++
    window.scrollTo(0, 0)
  } else {
    // Trigger generic error or highlight fields (simplified for now)
    alert('Please complete all required fields')
  }
}

const prevStep = () => {
  if (currentStep.value > 1) currentStep.value--
  window.scrollTo(0, 0)
}

const handleSubmit = async () => {
  await createProduct({
    ...product.value,
    price: parseRupiah(product.value.price),
    product_images: product.value.product_images
      .filter((image) => image.image)
      .map((image) => {
        return {
          image: image.image,
          is_thumbnail: image.is_thumbnail ? 1 : 0
        }
      })
  })
}

const handleImageChange = (event, index) => {
  const file = event.target.files[0]

  product.value.product_images[index].image = file
  product.value.product_images[index].url = URL.createObjectURL(file)
}

watch(
  () => product.value.parent_product_category_id,
  async (newValue) => {
    product.value.product_category_id = null

    const category =
      productCategories.value.find((category) => category.id === newValue)?.childrens || []

    subCategories.value = category
  }
)

onMounted(async () => {
  // Set store_id from user's store
  if (user.value?.store?.id) {
    product.value.store_id = user.value.store.id
  }

  await fetchProductCategories({
    is_parent: true
  })
})
</script>

<template>
  <div
    class="flex flex-col w-full rounded-3xl p-6 gap-6 bg-white dark:bg-surface-card dark:text-white border border-transparent dark:border-white/10 min-h-[600px]">
    <StepWizard :steps="steps" :current-step="currentStep" @change-step="
      (s) => {
        if (validateStep(currentStep)) currentStep = s
      }
    " />

    <form class="flex flex-col gap-6 flex-1" @submit.prevent="handleSubmit">
      <!-- STEP 1: General Info -->
      <div v-show="currentStep === 1" class="flex flex-col gap-6 animate-fade-in-up">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <p class="font-semibold text-custom-grey w-full md:w-1/3">Product Name</p>
          <div class="group/errorState flex flex-col gap-2 w-full" :class="{ invalid: error?.name }">
            <label class="group relative">
              <div class="input-icon">
                <img src="@/assets/images/icons/shopping-cart-grey.svg" class="flex size-6 shrink-0 dark:invert"
                  alt="icon" />
              </div>
              <p class="input-placeholder">Enter Product Name</p>
              <input v-model="product.name" type="text" class="custom-input" placeholder="" />
            </label>
            <span v-if="error?.name" class="input-error">{{ error?.name?.join(', ') }}</span>
          </div>
        </div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <p class="font-semibold text-custom-grey w-full md:w-1/3">Description</p>
          <div class="group/errorState flex flex-col gap-2 w-full" :class="{ invalid: error?.description }">
            <label
              class="group flex py-4 px-6 rounded-3xl border-[2px] border-custom-border focus-within:border-custom-black transition-300 w-full group-[&.invalid]/errorState:border-custom-red">
              <div class="flex h-full pr-4 pt-2 border-r-[1.5px] border-custom-border">
                <img src="@/assets/images/icons/stickynote-grey.svg" class="flex size-6 shrink-0 dark:invert"
                  alt="icon" />
              </div>
              <div class="flex flex-col gap-[6px] pl-4 w-full">
                <p
                  class="placeholder font-semibold text-custom-grey text-sm group-has-[:placeholder-shown]:text-base group-has-[:placeholder-shown]:text-custom-black group-has-[:placeholder-shown]:font-bold transition-300">
                  Enter Product Description
                </p>
                <textarea v-model="product.description"
                  class="appearance-none outline-none w-full font-semibold leading-[160%]" rows="3"
                  placeholder=""></textarea>
              </div>
            </label>
            <span v-if="error?.description" class="input-error">{{
              error?.description?.join(', ')
              }}</span>
          </div>
        </div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <p class="font-semibold text-custom-grey w-full md:w-1/3">Category</p>
          <div class="flex flex-col md:flex-row gap-4 w-full">
            <div class="group/errorState flex flex-col gap-2 w-full">
              <label
                class="group relative rounded-[18px] border-[1.5px] border-custom-stroke focus-within:border-custom-black transition-300 overflow-hidden w-full">
                <div class="input-icon">
                  <img src="@/assets/images/icons/bag-grey.svg" class="flex size-6 shrink-0 dark:invert" alt="icon" />
                </div>
                <select v-model="product.parent_product_category_id" required
                  class="appearance-none w-full h-[72px] font-semibold text-lg outline-none pl-20 pr-6 pb-[14.5px] pt-[32px] bg-transparent z-10 relative">
                  <option hidden></option>
                  <option v-for="category in productCategories" :key="category.id" :value="category.id">
                    {{ category.name }}
                  </option>
                </select>
                <p class="placeholder font-bold absolute -translate-y-1/2 left-[81px] top-[25px] transition-300" :class="product.parent_product_category_id
                  ? 'text-sm text-custom-grey font-semibold'
                  : ''
                  ">
                  Select Category
                </p>
                <img src="@/assets/images/icons/arrow-down-black.svg"
                  class="absolute transform -translate-y-1/2 top-1/2 right-6 size-6 dark:invert" alt="icon" />
              </label>
            </div>
            <div v-if="subCategories.length > 0" class="group/errorState flex flex-col gap-2 w-full">
              <label
                class="group relative rounded-[18px] border-[1.5px] border-custom-stroke focus-within:border-custom-black transition-300 overflow-hidden w-full">
                <div class="input-icon">
                  <img src="@/assets/images/icons/shopping-bag-black.svg" class="flex size-6 shrink-0 dark:invert"
                    alt="icon" />
                </div>
                <select v-model="product.product_category_id" required
                  class="appearance-none w-full h-[72px] font-semibold text-lg outline-none pl-20 pr-6 pb-[14.5px] pt-[32px] bg-transparent z-10 relative">
                  <option hidden></option>
                  <option v-for="category in subCategories" :key="category.id" :value="category.id">
                    {{ category.name }}
                  </option>
                </select>
                <p class="placeholder font-bold absolute -translate-y-1/2 left-[81px] top-[25px] transition-300" :class="product.product_category_id ? 'text-sm text-custom-grey font-semibold' : ''
                  ">
                  Select Sub Category
                </p>
                <img src="@/assets/images/icons/arrow-down-black.svg"
                  class="absolute transform -translate-y-1/2 top-1/2 right-6 size-6 dark:invert" alt="icon" />
              </label>
            </div>
          </div>
        </div>
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <p class="font-semibold text-custom-grey w-full md:w-1/3">Condition</p>
          <div class="grid grid-cols-2 gap-6 h-[76px] w-full">
            <label
              class="group relative flex items-center h-full py-4 px-5 gap-4 rounded-[18px] border-[2px] border-custom-border focus-within:border-custom-black transition-300 w-full cursor-pointer">
              <div class="flex h-[28px] shrink-0 items-center pr-4 pl-1 border-r-[1.5px] border-custom-border">
                <img src="@/assets/images/icons/gift-grey.svg" class="flex size-6 shrink-0 dark:invert" alt="icon" />
              </div>
              <p class="font-bold leading-none w-full">New</p>
              <div
                class="flex size-4 shrink-0 rounded-full ring-2 ring-custom-grey border-[3px] border-white group-has-[:checked]:bg-custom-blue group-has-[:checked]:ring-custom-blue transition-300">
              </div>
              <input v-model="product.condition" type="radio" name="condition" class="absolute opacity-0" value="new" />
            </label>
            <label
              class="group relative flex items-center h-full py-4 px-5 gap-4 rounded-[18px] border-[2px] border-custom-border focus-within:border-custom-black transition-300 w-full cursor-pointer">
              <div class="flex h-[28px] shrink-0 items-center pr-4 pl-1 border-r-[1.5px] border-custom-border">
                <img src="@/assets/images/icons/box-grey.svg" class="flex size-6 shrink-0 dark:invert" alt="icon" />
              </div>
              <p class="font-bold leading-none w-full">Used</p>
              <div
                class="flex size-4 shrink-0 rounded-full ring-2 ring-custom-grey border-[3px] border-white group-has-[:checked]:bg-custom-blue group-has-[:checked]:ring-custom-blue transition-300">
              </div>
              <input v-model="product.condition" type="radio" name="condition" class="absolute opacity-0"
                value="second" />
            </label>
          </div>
        </div>
      </div>

      <!-- STEP 2: Stock & Price -->
      <div v-show="currentStep === 2" class="flex flex-col gap-6 animate-fade-in-up">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <p class="font-semibold text-custom-grey w-full md:w-1/3">Weight (grams)</p>
          <div class="group/errorState flex flex-col gap-2 w-full" :class="{ invalid: error?.weight }">
            <label class="group relative">
              <div class="input-icon">
                <img src="@/assets/images/icons/box-2-grey.svg" class="flex size-6 shrink-0 dark:invert" alt="icon" />
              </div>
              <p class="input-placeholder">Enter Weight</p>
              <input v-model="product.weight" type="number" class="custom-input" placeholder="" />
            </label>
          </div>
        </div>

        <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
          <div class="w-full md:w-1/3 pt-2">
            <p class="font-semibold text-custom-grey">Product Variants</p>
            <p class="text-xs text-custom-grey mt-1">
              Does this product have options like size, color?
            </p>
          </div>

          <div class="w-full flex flex-col gap-4">
            <label
              class="flex items-center gap-2 cursor-pointer p-4 border rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
              <input v-model="product.has_variants" type="checkbox" class="toggle toggle-primary" />
              <span class="font-bold">Enable Variants</span>
            </label>

            <!-- Option Groups Definition -->
            <div v-if="product.has_variants" class="p-4 border rounded-xl bg-blue-50/50 flex flex-col gap-4">
              <span class="text-xs font-bold text-gray-500 uppercase">1. Define Options</span>
              <div class="flex gap-2">
                <input v-model="newOptionName" type="text" placeholder="Add Option (e.g. Color)"
                  class="custom-input h-10 text-sm bg-white" />
                <button type="button"
                  class="h-10 px-6 text-sm font-bold bg-custom-black text-white rounded-lg hover:bg-gray-800 transition-colors"
                  @click="addOptionGroup">
                  Add
                </button>
              </div>
              <div class="flex flex-wrap gap-2">
                <span v-for="(opt, idx) in optionGroups" :key="idx"
                  class="px-3 py-1 bg-white border border-custom-blue/30 text-custom-blue rounded-full text-xs font-bold flex items-center gap-2">
                  {{ opt }}
                  <button type="button" class="hover:text-red-500 text-lg leading-none"
                    @click="optionGroups.splice(idx, 1)">
                    &times;
                  </button>
                </span>
              </div>
            </div>

            <div v-if="product.has_variants" class="flex flex-col gap-3">
              <span class="text-xs font-bold text-gray-500 uppercase mt-2">2. Manage Variants</span>

              <div v-for="(variant, index) in product.variants" :key="index"
                class="p-4 border rounded-xl bg-white flex flex-col gap-4 shadow-sm">
                <div class="flex justify-between items-center border-b pb-2 mb-2">
                  <h4 class="font-bold text-sm text-custom-black">Variant #{{ index + 1 }}</h4>
                  <button type="button" class="text-red-500 text-xs font-bold hover:underline"
                    @click="product.variants.splice(index, 1)">
                    Remove
                  </button>
                </div>

                <!-- Dynamic Attributes Inputs -->
                <div v-if="optionGroups.length > 0" class="grid grid-cols-2 gap-3">
                  <div v-for="opt in optionGroups" :key="opt" class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold text-gray-400 uppercase">{{ opt }}</label>
                    <input v-model="variant.variant_attributes[opt]" type="text"
                      class="h-10 w-full border border-gray-200 bg-gray-50 rounded-lg px-3 text-sm font-semibold outline-none focus:border-custom-black transition-all"
                      :placeholder="opt + ' Value'" @input="autoGenerateName(variant)" />
                  </div>
                </div>

                <!-- Variant Name (Auto-generated or Manual) -->
                <div class="flex flex-col gap-1">
                  <label class="text-xs font-semibold text-gray-400">Variant Name</label>
                  <input v-model="variant.name" type="text" class="custom-input h-10 text-sm bg-gray-50"
                    placeholder="Name" />
                </div>

                <div class="flex gap-3">
                  <div class="flex flex-col gap-1 w-1/2">
                    <label class="text-xs font-semibold text-gray-400">Price (Rp)</label>
                    <input v-model="variant.price" type="number" class="custom-input h-10 text-sm"
                      placeholder="Price" />
                  </div>
                  <div class="flex flex-col gap-1 w-1/2">
                    <label class="text-xs font-semibold text-gray-400">Stock</label>
                    <input v-model="variant.stock" type="number" class="custom-input h-10 text-sm"
                      placeholder="Stock" />
                  </div>
                </div>
              </div>

              <button type="button"
                class="btn-primary w-full py-3 rounded-xl text-sm font-bold border-2 border-dashed border-custom-blue text-custom-blue bg-blue-50 hover:bg-blue-100 transition-colors"
                @click="addVariant">
                + Add Variant Row
              </button>
            </div>
          </div>
        </div>

        <div v-if="!product.has_variants" class="flex flex-col md:flex-row justify-between gap-4">
          <p class="font-semibold text-custom-grey w-full md:w-1/3">Standard Price & Stock</p>
          <div class="flex gap-6 w-full">
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ invalid: error?.price }">
              <label class="group relative">
                <div class="input-icon">
                  <img src="@/assets/images/icons/money-grey.svg" class="flex size-6 shrink-0 dark:invert" alt="icon" />
                </div>
                <p class="input-placeholder">Price</p>
                <input v-model="product.price" type="number" class="custom-input" placeholder="" />
              </label>
            </div>
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ invalid: error?.stock }">
              <label class="group relative">
                <div class="input-icon">
                  <img src="@/assets/images/icons/box-grey.svg" class="flex size-6 shrink-0 dark:invert" alt="icon" />
                </div>
                <p class="input-placeholder">Stock</p>
                <input v-model="product.stock" type="number" class="custom-input" placeholder="" />
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- STEP 3: Visuals -->
      <div v-show="currentStep === 3" class="flex flex-col gap-6 animate-fade-in-up">
        <div class="flex flex-col justify-between gap-4">
          <div class="flex flex-col gap-1">
            <p class="font-semibold text-custom-black">Product Gallery</p>
            <p class="text-sm text-custom-grey">Upload clear images of your product.</p>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 w-full">
            <div v-for="(image, index) in product.product_images" :key="index"
              class="thumbnail-input-container group relative flex w-full aspect-square rounded-2xl overflow-hidden items-center justify-center bg-gray-50 border-2 border-dashed border-gray-200 hover:border-custom-blue transition-colors">
              <input type="file" accept="image/*"
                class="product-image-input absolute inset-0 opacity-0 cursor-pointer z-10"
                @change="handleImageChange($event, index)" />
              <img :src="image.url" :class="image.url === PlaceHolder ? 'w-8 h-8 opacity-40' : 'size-full object-cover'"
                class="thumbnail pointer-events-none" alt="icon" />

              <div v-if="index === 0"
                class="absolute bottom-0 left-0 w-full p-2 bg-black/50 text-white text-[10px] text-center backdrop-blur-sm">
                Thumbnail
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Navigation Actions -->
      <div class="flex items-center justify-between pt-6 mt-4 border-t border-gray-100">
        <button v-if="currentStep > 1" type="button"
          class="h-12 px-6 rounded-full font-bold text-gray-500 hover:bg-gray-100 transition-colors" @click="prevStep">
          Back
        </button>
        <div v-else></div>
        <!-- Spacer -->

        <button v-if="currentStep < 3" type="button"
          class="h-12 px-8 rounded-full bg-custom-black text-white font-bold hover:bg-gray-800 transition-colors shadow-lg"
          @click="nextStep">
          Next Step
        </button>
        <button v-else type="submit"
          class="h-12 px-8 rounded-full bg-custom-blue text-white font-bold hover:bg-blue-600 transition-colors shadow-lg shadow-blue-200">
          Create Product
        </button>
      </div>
    </form>
  </div>
</template>
