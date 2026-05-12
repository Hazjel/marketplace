<script setup>
import { useProductCategoryStore } from '@/stores/productCategory'
import { storeToRefs } from 'pinia'
import { onMounted, ref } from 'vue'
import PlaceHolder from '@/assets/images/icons/gallery-grey.svg'
import { RouterLink, useRoute } from 'vue-router'
import Button from '@/components/Atom/Button.vue'
import Input from '@/components/Atom/Input.vue'

const route = useRoute()

const productCategoryStore = useProductCategoryStore()
const { loading, error } = storeToRefs(productCategoryStore)
const { createProductCategory, fetchProductCategoryById } = productCategoryStore

const productCategory = ref({
  parent_id: null,
  parent_name: null,
  image: null,
  image_url: PlaceHolder,
  name: null,
  tagline: null,
  description: null
})

const handleSubmit = async () => {
  await createProductCategory(productCategory.value)
}

const handleImageChange = (e) => {
  const file = e.target.files[0]

  productCategory.value.image = file
  productCategory.value.image_url = URL.createObjectURL(file)
}

onMounted(async () => {
  if (route.query.parent_id) {
    const response = await fetchProductCategoryById(route.query.parent_id)

    productCategory.value.parent_id = response.id
    productCategory.value.parent_name = response.name
  }
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- Page Header -->
    <div class="rounded-2xl bg-gradient-to-r from-indigo-600 to-blue-600 p-6 shadow-sm">
      <div class="flex items-center gap-4">
        <div class="flex size-12 items-center justify-center rounded-xl bg-white/20">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-white">Tambah Kategori</h1>
          <p class="text-blue-100">Buat kategori produk baru</p>
        </div>
      </div>
    </div>

    <!-- Form -->
    <form class="flex flex-col w-full rounded-2xl border border-gray-100 dark:border-white/10 bg-white dark:bg-surface-card shadow-sm p-6 gap-6" @submit.prevent="handleSubmit">
      <div class="flex items-center gap-3">
        <div class="flex size-10 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
          </svg>
        </div>
        <h2 class="font-bold text-lg dark:text-white">Lengkapi Formulir</h2>
      </div>

      <!-- Image Upload -->
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
        <p class="font-medium text-sm text-gray-600 dark:text-gray-300">Gambar Kategori</p>
        <div class="flex items-center justify-between w-full md:w-1/2 gap-4">
          <div class="group relative flex size-[100px] p-[26px] rounded-2xl overflow-hidden items-center justify-center bg-gray-100 dark:bg-white/5 border-2 border-dashed border-gray-300 dark:border-white/20 hover:border-indigo-400 transition-colors duration-200">
            <img
              id="Thumbnail"
              :src="productCategory.image_url"
              class="size-full object-contain"
              alt="icon" />
            <input
              id="File-Input"
              type="file"
              accept="image/*"
              class="absolute inset-0 opacity-0 cursor-pointer"
              @change="handleImageChange" />
          </div>
          <Button id="Add-Photo" type="button" variant="secondary"> Unggah Foto </Button>
        </div>
      </div>

      <!-- Parent Category -->
      <div v-if="productCategory.parent_id" class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
        <p class="font-medium text-sm text-gray-600 dark:text-gray-300">Kategori Induk</p>
        <div class="w-full md:w-1/2">
          <Input
            v-model="productCategory.parent_name"
            label="Kategori Induk"
            readonly
            :error="error?.parent_id">
            <template #icon>
              <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
              </svg>
            </template>
          </Input>
        </div>
      </div>

      <!-- Name -->
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
        <p class="font-medium text-sm text-gray-600 dark:text-gray-300">Nama Kategori</p>
        <div class="w-full md:w-1/2">
          <Input v-model="productCategory.name" label="Masukkan Nama Kategori" :error="error?.name">
            <template #icon>
              <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
              </svg>
            </template>
          </Input>
        </div>
      </div>

      <!-- Tagline -->
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
        <p class="font-medium text-sm text-gray-600 dark:text-gray-300">Tagline Kategori</p>
        <div class="w-full md:w-1/2">
          <Input
            v-model="productCategory.tagline"
            label="Masukkan Tagline"
            :error="error?.tagline">
            <template #icon>
              <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
              </svg>
            </template>
          </Input>
        </div>
      </div>

      <!-- Description -->
      <div class="flex flex-col md:flex-row items-start justify-between gap-3">
        <p class="font-medium text-sm text-gray-600 dark:text-gray-300 mt-3">Deskripsi</p>
        <div class="group/errorState flex flex-col gap-2 w-full md:w-1/2" :class="{ invalid: error?.description }">
          <label class="group flex py-4 px-5 rounded-2xl border-2 border-gray-200 dark:border-white/10 focus-within:border-indigo-500 dark:focus-within:border-indigo-400 transition-all duration-200 w-full group-[&.invalid]/errorState:border-red-500">
            <div class="flex h-full pr-4 pt-1 border-r border-gray-200 dark:border-white/10">
              <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
              </svg>
            </div>
            <div class="flex flex-col gap-1 pl-4 w-full">
              <p class="text-xs font-medium text-gray-400 dark:text-gray-500">Masukkan Deskripsi Kategori</p>
              <textarea
                v-model="productCategory.description"
                class="appearance-none outline-none w-full font-medium text-sm leading-relaxed bg-transparent dark:text-white resize-none"
                rows="3"
                placeholder=""></textarea>
            </div>
          </label>
          <span
            v-if="error?.description"
            class="font-medium text-sm text-red-500">{{ error?.description?.join(', ') }}</span>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-3 pt-2">
        <Button
          v-if="productCategory?.parent_id"
          variant="danger"
          :to="{ name: 'admin.category.detail', params: { id: productCategory?.parent_id } }">
          Batal
        </Button>
        <Button v-if="!productCategory.parent_id" variant="danger" :to="{ name: 'admin.category' }">
          Batal
        </Button>
        <Button type="submit" variant="primary" :loading="loading"> Buat Sekarang </Button>
      </div>
    </form>
  </div>
</template>
