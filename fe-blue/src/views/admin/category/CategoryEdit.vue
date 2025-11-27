<script setup>
import { useProductCategoryStore } from '@/stores/productCategory';
import { storeToRefs } from 'pinia';
import { onMounted, ref } from 'vue';
import PlaceHolder from '@/assets/images/icons/gallery-grey.svg'
import { RouterLink } from 'vue-router';
import { useRoute } from 'vue-router';



const route = useRoute()

const productCategoryStore = useProductCategoryStore()
const { loading, error } = storeToRefs(productCategoryStore)
const { fetchProductCategoryById, updateProductCategory } = productCategoryStore

const productCategory = ref({
    parent_id: null,
    parent_name: null,
    image: null,
    image_url: PlaceHolder,
    name: null,
    tagline: null,
    description: null
})

const fetchData = async () => {
    const response = await fetchProductCategoryById(route.params.id)

    productCategory.value = response
    productCategory.value.image_url = response.image
    productCategory.value.image = null
}

const handleSubmit = async () => {
    await updateProductCategory(productCategory.value)
}

const handleImageChange = (e) => {
    const file = e.target.files[0]

    productCategory.value.image = file
    productCategory.value.image_url = URL.createObjectURL(file)
}

onMounted(async () => {
    fetchData()

    if (route.query.parent_id) {
        const response = await fetchProductCategoryById(route.query.parent_id)

        productCategory.value.parent_id = response.id
        productCategory.value.parent_name = response.name
    }
})
</script>

<template>
    <form @submit.prevent="handleSubmit" class="flex flex-col w-full rounded-3xl p-5 gap-5 bg-white">
        <h2 class="font-bold text-xl capitalize">Complete the form</h2>
        <div class="flex items-center justify-between">
            <p class="font-semibold text-custom-grey">Category Image</p>
            <div class="flex items-center justify-between w-1/2">
                <div class="group relative flex size-[100px] p-[26px] rounded-2xl overflow-hidden items-center justify-center bg-custom-background">
                    <img id="Thumbnail" :src="productCategory.image_url" data-default="@/assets/images/icons/gallery-grey.svg" class="size-full object-contain" alt="icon"/>
                    <input type="file" id="File-Input" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer" @change="handleImageChange"/>
                </div>
                <button type="button" id="Add-Photo" class="flex items-center justify-center rounded-2xl py-4 px-6 bg-custom-black text-white font-semibold text-lg">
                    Add Photo
                </button>
            </div>
        </div>
        <div class="flex items-center justify-between" v-if="productCategory.parent_id">
            <p class="font-semibold text-custom-grey">Category Parent</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.parent_id }">
                <label class="group relative">
                    <div class="input-icon">
                        <img src="@/assets/images/icons/note-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <p class="input-placeholder">
                        Enter Category Parent
                    </p>
                    <input type="text" class="custom-input" placeholder="" v-model="productCategory.parent_name" readonly>
                </label>
                <span class="input-error" v-if="error?.parent_id">{{ error?.parent_id?.join(', ') }}</span>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <p class="font-semibold text-custom-grey">Category Name</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.name }">
                <label class="group relative">
                    <div class="input-icon">
                        <img src="@/assets/images/icons/note-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <p class="input-placeholder">
                        Enter Category Name
                    </p>
                    <input type="text" class="custom-input" placeholder="" v-model="productCategory.name">
                </label>
                <span class="input-error" v-if="error?.name">{{ error?.name?.join(', ') }}</span>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <p class="font-semibold text-custom-grey">Category Tagline</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.tagline }">
                <label class="group relative">
                    <div class="input-icon">
                        <img src="@/assets/images/icons/stickynote-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <p class="input-placeholder">
                        Enter Tagline Category
                    </p>
                    <input type="text" class="custom-input" placeholder="" v-model="productCategory.tagline">
                </label>
                <span class="input-error" v-if="error?.tagline">{{ error?.tagline?.join(', ') }}</span>
            </div>
        </div>
        <div class="flex justify-between">
            <p class="font-semibold text-custom-grey mt-5">Category Description</p>
            <div class="group/errorState flex flex-col gap-2 w-1/2" :class="{ 'invalid': error?.description }">
                <label class="group flex py-4 px-6 rounded-3xl border-[2px] border-custom-border focus-within:border-custom-black transition-300 w-full group-[&.invalid]/errorState:border-custom-red">
                    <div class="flex h-full pr-4 pt-2 border-r-[1.5px] border-custom-border ">
                        <img src="@/assets/images/icons/document-text-grey.svg" class="flex size-6 shrink-0" alt="icon">
                    </div>
                    <div class="flex flex-col gap-[6px] pl-4 w-full">
                        <p class="placeholder font-semibold text-custom-grey text-sm group-has-[:placeholder-shown]:text-base group-has-[:placeholder-shown]:text-custom-black group-has-[:placeholder-shown]:font-bold transition-300">
                            Enter Category Description
                        </p>
                        <textarea class="appearance-none outline-none w-full font-semibold leading-[160%]" rows="3" placeholder="" v-model="productCategory.description"></textarea>
                    </div>
                </label>
                <span class="font-semibold text-lg text-custom-red leading-none group-[&.invalid]/errorState:block" v-if="error?.description">{{ error?.description?.join(', ') }}</span>
            </div>
        </div>
        <div class="flex items-center justify-end gap-4">
            <RouterLink :to="{ name: 'admin.category.detail', params: { id: productCategory?.parent_id }}" class="flex items-center justify-center h-14 rounded-full py-4 px-6 gap-2 bg-custom-red text-white font-semibold text-lg" v-if="productCategory?.parent_id">
                Cancel
            </RouterLink>
            <RouterLink :to="{ name: 'admin.category' }" class="flex items-center justify-center h-14 rounded-full py-4 px-6 gap-2 bg-custom-red text-white font-semibold text-lg" v-if="!productCategory.parent_id">
                Cancel
            </RouterLink>
            <button type="submit" class="flex items-center justify-center h-14 rounded-full py-4 px-6 gap-2 bg-custom-blue text-white font-semibold text-lg">
                Update Now
            </button>
        </div>
    </form>
</template>