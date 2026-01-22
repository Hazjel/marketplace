<script setup>
import { useProductStore } from '@/stores/product';
import { useProductCategoryStore } from '@/stores/productCategory';
import { useAuthStore } from '@/stores/auth';
import { storeToRefs } from 'pinia';
import { onMounted, ref, watch } from 'vue';
import PlaceHolder from '@/assets/images/icons/gallery-grey.svg'
import { RouterLink, useRoute } from 'vue-router';
import { formatRupiah, parseRupiah } from '@/helpers/format';

const route = useRoute()

const productStore = useProductStore()
const { loading, error } = storeToRefs(productStore)
const { createProduct, fetchProductById, updateProduct } = productStore


const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const productCategoryStore = useProductCategoryStore()
const { productCategories } = storeToRefs(productCategoryStore)
const { fetchProductCategories } = productCategoryStore

const subCategories = ref([])

const product = ref({
    store_id: null,
    parent_product_category_id: null,
    product_category_id: null,
    name: null,
    description: null,
    condition: null,
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
        // ... (truncated for brevity in diff, but keep original)
    ],

})

// Dynamic Variant Options
const optionGroups = ref([])
const newOptionName = ref('')

const addOptionGroup = () => {
    if (newOptionName.value && !optionGroups.value.includes(newOptionName.value)) {
        optionGroups.value.push(newOptionName.value)
        newOptionName.value = ''
        // Add new key to existing variants
        product.value.variants.forEach(v => {
            if (!v.variant_attributes) v.variant_attributes = {}
        })
    }
}

const addVariant = () => {
    const attributes = {}
    optionGroups.value.forEach(opt => attributes[opt] = '')
    product.value.variants.push({
        _temp_id: Date.now() + Math.random(),
        name: '',
        price: product.value.price || 0, // Default to product price
        stock: 0,
        variant_attributes: attributes
    })
}

const autoGenerateName = (variant) => {
    if (variant.variant_attributes) {
        const parts = Object.values(variant.variant_attributes).filter(val => val)
        if (parts.length > 0) {
            variant.name = parts.join(' - ')
        } else {
            variant.name = ''
        }
    }
}

const fetchData = async () => {
    const response = await fetchProductById(route.params.id)

    product.value = response

    // Populate variants if they exist
    if (response.variants && response.variants.length > 0) {
        product.value.has_variants = true
        // Ensure every variant has variant_attributes object
        product.value.variants = response.variants.map(v => ({
            ...v,
            variant_attributes: v.variant_attributes || {}
        }))

        // EXTRACT EXISTING OPTIONS FROM VARIANTS
        const groups = new Set()
        product.value.variants.forEach(v => {
            if (v.variant_attributes) {
                Object.keys(v.variant_attributes).forEach(k => groups.add(k))
            }
        })
        optionGroups.value = Array.from(groups)

    } else {
        product.value.has_variants = false
        product.value.variants = []
    }

    // ensure store_id stays set (response may not include it)
    if (!product.value.store_id && user.value?.store?.id) {
        product.value.store_id = user.value.store.id
    }

    product.value.product_images.forEach((image) => {
        image.id = image.id
        image.url = image.image
        image.image = null
    })

    product.value.parent_product_category_id = product.value.product_category.parent_id
    product.value.product_category_id = product.value.product_category.id
}

const deletedImages = ref([])

const handleSubmit = async () => {
    // If variants are disabled, clear the array before sending
    const variantsData = product.value.has_variants ? product.value.variants : []

    await updateProduct({
        ...product.value,
        variants: variantsData,
        price: parseRupiah(product.value.price),
        product_images: product.value.product_images.filter(image => image.image).map(image => {
            return {
                id: null,
                image: image.image,
                is_thumbnail: image.is_thumbnail ? 1 : 0
            }
        }),
        deleted_product_images: deletedImages.value
    })
}

const handleImageChange = (event, index) => {
    const file = event.target.files[0]

    // If there was an ID (existing image), mark it for deletion
    if (product.value.product_images[index].id) {
        deletedImages.value.push(product.value.product_images[index].id)
        product.value.product_images[index].id = null // treat as new
    }

    product.value.product_images[index].image = file
    product.value.product_images[index].url = URL.createObjectURL(file)
}

watch(() => product.value.parent_product_category_id, async (newValue) => {
    const category = productCategories.value.find(category => category.id === newValue).childrens

    subCategories.value = category
})


onMounted(async () => {
    // Set store_id from user's store
    if (user.value?.store?.id) {
        product.value.store_id = user.value.store.id
    }

    await fetchProductCategories({
        is_parent: true,
    })

    await fetchData()
})

</script>


<template>
    <form @submit.prevent="handleSubmit" class="flex flex-col w-full rounded-3xl p-5 gap-5 bg-white">
        <h2 class="font-bold text-xl capitalize">Complete the form</h2>
        <div class="flex items-center justify-between">
            <p class="font-semibold text-custom-grey">Product Image</p>
            <div class="flex items-center justify-between w-1/2">
                <div class="thumbnail-input-container group relative flex size-[100px] rounded-2xl overflow-hidden items-center justify-center bg-custom-background"
                    v-for="(image, index) in product.product_images">
                    <input type="file" accept="image/*"
                        class="product-image-input absolute inset-0 opacity-0 cursor-pointer z-10"
                        @change="handleImageChange($event, index)" />
                    <img :src="image.url" data-default="@/assets/images/icons/gallery-add-photo.svg"
                        class="thumbnail size-full object-contain pointer-events-none" alt="icon" />
                </div>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <p class="font-semibold text-custom-grey">Product Name</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.name }">
                <label class="group relative">
                    <div class="input-icon">
                        <img src="@/assets/images/icons/shopping-cart-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <p class="input-placeholder">
                        Enter Product Name
                    </p>
                    <input type="text" class="custom-input" placeholder="" v-model="product.name">
                </label>
                <span class="input-error" v-if="error?.name">{{ error?.name?.join(', ') }}</span>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <p class="font-semibold text-custom-grey">Product Weight</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.weight }">
                <div class="relative">
                    <label class="group relative h-full block">
                        <div class="input-icon">
                            <img src="@/assets/images/icons/box-2-grey.svg" class="flex size-6 shrink-0" alt="icon">
                        </div>
                        <p class="input-placeholder">
                            Enter Product Weight
                        </p>
                        <input type="text" class="custom-input !pr-24" placeholder="" v-model="product.weight">
                    </label>
                    <div class="absolute transform -translate-y-1/2 top-1/2 right-5 w-fit h-fit">
                        <select name="" id=""
                            class="rounded-lg py-[10px] px-4 bg-custom-blue/10 text-custom-blue font-extrabold text-sm h-[38px] w-[71px] appearance-none leading-none">
                            <option value="" selected>KG</option>
                        </select>
                        <img src="@/assets/images/icons/arrow-up-triangle-blue.svg"
                            class="rotate-180 size-4 absolute transform -translate-y-1/2 top-1/2 right-[10px]"
                            alt="icon">
                    </div>
                </div>
                <span class="input-error" v-if="error?.weight">{{ error?.weight?.join(', ') }}</span>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <p class="font-semibold text-custom-grey">Product Type</p>
            <div class="grid grid-cols-2 gap-6 h-[76px] w-1/2">
                <label
                    class="group relative flex items-center h-full py-4 px-5 gap-4 rounded-[18px] border-[2px] border-custom-border focus-within:border-custom-black transition-300 w-full">
                    <div class="flex h-[28px] shrink-0 items-center pr-4 pl-1 border-r-[1.5px] border-custom-border ">
                        <img src="@/assets/images/icons/gift-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <p class="font-bold leading-none w-full">
                        New Item
                    </p>
                    <div
                        class="flex size-4 shrink-0 rounded-full ring-2 ring-custom-grey border-[3px] border-white group-has-[:checked]:bg-custom-blue group-has-[:checked]:ring-custom-blue transition-300">
                    </div>
                    <input type="radio" name="type" id="" class="absolute opacity-0" value="new"
                        v-model="product.condition">
                </label>
                <label
                    class="group relative flex items-center h-full py-4 px-5 gap-4 rounded-[18px] border-[2px] border-custom-border focus-within:border-custom-black transition-300 w-full">
                    <div class="flex h-[28px] shrink-0 items-center pr-4 pl-1 border-r-[1.5px] border-custom-border ">
                        <img src="@/assets/images/icons/box-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <p class="font-bold leading-none w-full">
                        Used Item
                    </p>
                    <div
                        class="flex size-4 shrink-0 rounded-full ring-2 ring-custom-grey border-[3px] border-white group-has-[:checked]:bg-custom-blue group-has-[:checked]:ring-custom-blue transition-300">
                    </div>
                    <input type="radio" name="type" id="" class="absolute opacity-0" value="second"
                        v-model="product.condition">
                </label>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <p class="font-semibold text-custom-grey">Product Variants</p>
            <div class="w-1/2 flex flex-col gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="product.has_variants" class="toggle toggle-primary" />
                    <span class="font-semibold">Enable Variants (Size, Color, etc.)</span>
                </label>

                <!-- Option Groups Definition -->
                <div v-if="product.has_variants" class="p-3 border rounded-xl bg-blue-50/50 flex flex-col gap-2">
                    <span class="text-xs font-bold text-gray-500 uppercase">1. Define Options</span>
                    <div class="flex gap-2">
                        <input type="text" v-model="newOptionName" placeholder="Add Option (e.g. Color)"
                            class="custom-input h-9 text-sm">
                        <button type="button" @click="addOptionGroup"
                            class="btn-primary h-9 px-4 text-xs bg-custom-black text-white rounded-lg">Add</button>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-1">
                        <span v-for="(opt, idx) in optionGroups" :key="idx"
                            class="px-3 py-1 bg-white border rounded-full text-xs font-bold flex items-center gap-2">
                            {{ opt }}
                            <button type="button" @click="optionGroups.splice(idx, 1)"
                                class="text-red-500 hover:text-red-700">&times;</button>
                        </span>
                    </div>
                </div>

                <div v-if="product.has_variants" class="flex flex-col gap-3">
                    <span class="text-xs font-bold text-gray-500 uppercase mt-2">2. Set Variants</span>

                    <div v-for="(variant, index) in product.variants" :key="variant.id || variant._temp_id || index"
                        class="p-4 border rounded-xl bg-gray-50 flex flex-col gap-3">
                        <div class="flex justify-between items-center">
                            <h4 class="font-bold text-sm text-gray-500">Variant #{{ index + 1 }}</h4>
                            <button type="button" @click="product.variants.splice(index, 1)"
                                class="text-red-500 text-xs font-bold hover:underline">Remove</button>
                        </div>

                        <!-- Dynamic Attributes Inputs -->
                        <div v-if="optionGroups.length > 0 && variant.variant_attributes"
                            class="grid grid-cols-2 gap-3 mb-2">
                            <div v-for="opt in optionGroups" :key="opt" class="flex flex-col gap-1">
                                <label class="text-[10px] font-bold text-gray-400 uppercase">{{ opt }}</label>
                                <input type="text" v-model="variant.variant_attributes[opt]"
                                    @input="autoGenerateName(variant)"
                                    class="h-9 w-full border border-custom-stroke rounded-lg px-3 text-sm font-semibold outline-none focus:border-custom-black transition-all"
                                    :placeholder="opt + ' Value'">
                            </div>
                        </div>

                        <!-- Variant Name (Auto-generated or Manual) -->
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold text-gray-400">Variant Name (Generated)</label>
                            <input type="text" v-model="variant.name" class="custom-input h-10 text-sm bg-gray-100"
                                placeholder="Name">
                        </div>

                        <div class="flex gap-3">
                            <!-- Variant Price -->
                            <div class="flex flex-col gap-1 w-1/2">
                                <label class="text-xs font-semibold text-gray-400">Price</label>
                                <input type="number" v-model="variant.price" class="custom-input h-10 text-sm"
                                    placeholder="Price">
                            </div>
                            <!-- Variant Stock -->
                            <div class="flex flex-col gap-1 w-1/2">
                                <label class="text-xs font-semibold text-gray-400">Stock</label>
                                <input type="number" v-model="variant.stock" class="custom-input h-10 text-sm"
                                    placeholder="Stock">
                            </div>
                        </div>
                    </div>

                    <button type="button" @click="addVariant"
                        class="btn-primary w-full py-2 rounded-xl text-sm border border-dashed border-custom-blue text-custom-blue bg-blue-50 hover:bg-blue-100">
                        + Add New Variant
                    </button>
                </div>
            </div>
        </div>

        <div class="flex justify-between" v-if="!product.has_variants">
            <p class="font-semibold text-custom-grey mt-5">Product Price & Stock</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.description }">
                <label
                    class="group flex py-4 px-6 rounded-3xl border-[2px] border-custom-border focus-within:border-custom-black transition-300 w-full group-[&.invalid]/errorState:border-custom-red">
                    <div class="flex h-full pr-4 pt-2 border-r-[1.5px] border-custom-border ">
                        <img src="@/assets/images/icons/stickynote-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <div class="flex flex-col gap-[6px] pl-4 w-full">
                        <p
                            class="placeholder font-semibold text-custom-grey text-sm group-has-[:placeholder-shown]:text-base group-has-[:placeholder-shown]:text-custom-black group-has-[:placeholder-shown]:font-bold transition-300">
                            Enter Product Description
                        </p>
                        <textarea class="appearance-none outline-none w-full font-semibold leading-[160%]" rows="3"
                            placeholder="" v-model="product.description"></textarea>
                    </div>
                </label>
                <span class="font-semibold text-lg text-custom-red leading-none group-[&.invalid]/errorState:block"
                    v-if="error?.description">{{ error?.description?.join(', ') }}</span>
            </div>
        </div>
        <div class="peer flex items-center justify-between">
            <p class="font-semibold text-custom-grey">Product Category</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2">
                <label
                    class="group relative rounded-[18px] border-[1.5px] border-custom-stroke focus-within:border-custom-black transition-300 overflow-hidden w-full group-[&.invalid]/errorState:border-custom-red">
                    <div class="input-icon">
                        <img src="@/assets/images/icons/bag-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <p
                        class="placeholder font-bold absolute -translate-y-1/2 left-[81px] top-[25px] group-has-[:invalid]:top-[38px] group-has-[:valid]:text-sm group-has-[:valid]:text-custom-grey group-has-[:valid]:font-semibold group-focus-within:top-[25px] transition-300">
                        Select Category
                    </p>
                    <select name="" id="" required
                        class="appearance-none w-full h-[72px] font-semibold text-lg outline-none pl-20 pr-6 pb-[14.5px] pt-[32px]"
                        v-model="product.parent_product_category_id">
                        <option hidden></option>
                        <option v-for="category in productCategories" :value="category.id">{{ category.name }}</option>
                    </select>
                    <img src="@/assets/images/icons/arrow-down-black.svg"
                        class="absolute transform -translate-y-1/2 top-1/2 right-6 size-6" alt="icon">
                    <span class="input-error" v-if="error?.parent_product_category_id">{{
                        error?.parent_product_category_id?.join(', ') }}</span>
                </label>
            </div>
        </div>
        <div class="peer-has-[:valid]:flex hidden items-center justify-between">
            <p class="font-semibold text-custom-grey">Product Sub Category</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.product_category_id }">
                <label
                    class="group relative rounded-[18px] border-[1.5px] border-custom-stroke focus-within:border-custom-black transition-300 overflow-hidden w-full group-[&.invalid]/errorState:border-custom-red">
                    <div class="input-icon">
                        <img src="@/assets/images/icons/shopping-bag-black.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <p
                        class="placeholder font-bold absolute -translate-y-1/2 left-[81px] top-[25px] group-has-[:invalid]:top-[38px] group-has-[:valid]:text-sm group-has-[:valid]:text-custom-grey group-has-[:valid]:font-semibold group-focus-within:top-[25px] transition-300">
                        Select Sub Category
                    </p>
                    <select name="" id="" required
                        class="appearance-none w-full h-[72px] font-semibold text-lg outline-none pl-20 pr-6 pb-[14.5px] pt-[32px]"
                        v-model="product.product_category_id">
                        <option hidden></option>
                        <option v-for="category in subCategories" :value="category.id">{{ category.name }}</option>
                    </select>
                    <img src="@/assets/images/icons/arrow-down-black.svg"
                        class="absolute transform -translate-y-1/2 top-1/2 right-6 size-6" alt="icon">
                </label>
                <span class="input-error" v-if="error?.product_category_id">{{ error?.product_category_id?.join(', ')
                }}</span>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <p class="font-semibold text-custom-grey">Product Price</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.product_price }">
                <label class="group relative">
                    <div class="input-icon">
                        <img src="@/assets/images/icons/money-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <p class="input-placeholder">
                        Enter Product Price
                    </p>
                    <input type="number" class="custom-input" placeholder="" v-model="product.price">
                </label>
                <span class="input-error" v-if="error?.product_price">{{ error?.product_price?.join(', ') }}</span>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <p class="font-semibold text-custom-grey">Product Stock</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.stock }">
                <label class="group relative">
                    <div class="input-icon">
                        <img src="@/assets/images/icons/box-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <p class="input-placeholder">
                        Enter Product Stock
                    </p>
                    <input type="number" class="custom-input" placeholder="" v-model="product.stock">
                </label>
                <span class="input-error" v-if="error?.stock">{{ error?.stock?.join(', ') }}</span>
            </div>
        </div>
        <div class="flex items-center justify-end gap-4">
            <RouterLink :to="{ name: 'admin.product' }"
                class="flex items-center justify-center h-14 rounded-full py-4 px-6 gap-2 bg-gray-100 text-custom-grey font-semibold text-lg hover:bg-gray-200 transition-300">
                Cancel
            </RouterLink>
            <button type="submit"
                class="flex items-center justify-center h-14 rounded-full py-4 px-6 gap-2 bg-custom-black text-white font-semibold text-lg hover:bg-black/80 transition-300">
                Update Product
            </button>
        </div>
    </form>
</template>