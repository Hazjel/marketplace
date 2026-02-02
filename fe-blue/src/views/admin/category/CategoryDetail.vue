<script setup>
import { useProductCategoryStore } from '@/stores/productCategory'
import { storeToRefs } from 'pinia'
import { onMounted, ref, watch } from 'vue'
import PlaceHolder from '@/assets/images/icons/gallery-grey.svg'
import { RouterLink } from 'vue-router'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'

const toast = useToast()
const route = useRoute()

const productCategoryStore = useProductCategoryStore()
const { loading, error, success } = storeToRefs(productCategoryStore)
const { fetchProductCategoryById, deleteProductCategory } = productCategoryStore

const productCategory = ref({})

const fetchData = async () => {
  const response = await fetchProductCategoryById(route.params.id)

  productCategory.value = response
}
async function handleDelete(id) {
  await deleteProductCategory(id)

  fetchData()
}

onMounted(fetchData)

watch(success, (value) => {
  if (value) {
    toast.success(value)
    productCategoryStore.success = null
  }
})
watch(error, (value) => {
  if (value) {
    toast.error(value)
    productCategoryStore.error = null
  }
})
</script>

<template>
  <div class="flex flex-col gap-5">
    <div class="flex gap-5">
      <section class="flex gap-5 flex-1">
        <div class="card flex flex-col h-fit w-full rounded-[20px] p-5 gap-5 bg-white">
          <div class="flex items-center gap-[14px]">
            <div
              class="flex size-[92px] shrink-0 items-center justify-center rounded-2xl bg-custom-background p-[22px] overflow-hidden"
            >
              <img :src="productCategory?.image" class="size-12 object-contain" alt="icon" />
            </div>
            <div class="flex flex-col flex-1 min-w-0 gap-[6px] overflow-hidden">
              <p class="font-bold text-lg truncate">{{ productCategory?.name }}</p>
              <p class="font-semibold text-custom-grey leading-none truncate">
                {{ productCategory?.tagline }}
              </p>
            </div>
          </div>
          <hr class="border-custom-stroke" />
          <div class="flex items-center gap-[10px] w-[260px]">
            <div
              class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
            >
              <img
                src="@/assets/images/icons/box-black.svg"
                class="flex size-6 shrink-0"
                alt="icon"
              />
            </div>
            <div class="flex flex-col gap-1">
              <p class="font-bold text-lg leading-none">{{ productCategory?.product_count }}</p>
              <p class="font-semibold text-custom-grey">Total Products</p>
            </div>
          </div>
          <div class="flex items-center gap-[10px] w-[260px]">
            <div
              class="flex size-14 shrink-0 rounded-full bg-custom-icon-background overflow-hidden items-center justify-center"
            >
              <img
                src="@/assets/images/icons/bag-black.svg"
                class="flex size-6 shrink-0"
                alt="icon"
              />
            </div>
            <div class="flex flex-col gap-1">
              <p class="font-bold text-lg leading-none">{{ productCategory?.children_count }}</p>
              <p class="font-semibold text-custom-grey">Sub Category</p>
            </div>
          </div>
        </div>
      </section>
      <section class="flex flex-col w-[580px] shrink-0 rounded-[20px] py-5 gap-5 bg-white">
        <p class="font-bold text-xl leading-none mx-5">List Sub Categories</p>
        <RouterLink
          :to="{ name: 'admin.category.create', query: { parent_id: productCategory?.id } }"
          class="flex h-14 items-center justify-center rounded-2xl py-4 px-6 bg-custom-blue gap-[6px] mx-5"
        >
          <span class="font-semibold text-lg text-white leading-none">Add New Sub Category</span>
          <img
            src="@/assets/images/icons/add-circle-white.svg"
            class="flex size-6 shrink-0"
            alt="icon"
          />
        </RouterLink>
        <hr class="border-custom-stroke" />
        <div id="List-Subcategory" class="flex flex-col gap-5 px-5">
          <template v-for="childrens in productCategory?.childrens">
            <div class="row flex items-center gap-5">
              <div class="flex items-center gap-[10px] w-full">
                <div
                  class="flex size-[72px] p-[15px] shrink-0 bg-white overflow-hidden items-center justify-center"
                >
                  <img
                    :src="childrens.image ?? PlaceHolder"
                    class="flex size-full object-contain"
                    alt="icon"
                  />
                </div>
                <div class="flex flex-col gap-1">
                  <p class="font-bold text-lg">{{ childrens?.name }}</p>
                  <p class="font-semibold text-custom-grey leading-none">
                    {{ childrens?.product_count }} Products
                  </p>
                </div>
              </div>
              <div class="flex items-center gap-[14px]">
                <button
                  class="flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-red/10"
                  @click="handleDelete(childrens.id)"
                >
                  <img
                    src="@/assets/images/icons/trash-red.svg"
                    class="flex size-6 shrink-0"
                    alt="icon"
                  />
                </button>
                <RouterLink
                  :to="{
                    name: 'admin.category.edit',
                    params: { id: childrens.id },
                    query: { parent_id: productCategory.id }
                  }"
                  class="flex items-center justify-center size-14 shrink-0 rounded-2xl p-4 gap-2 bg-custom-black"
                >
                  <img
                    src="@/assets/images/icons/edit-white.svg"
                    class="flex size-6 shrink-0"
                    alt="icon"
                  />
                </RouterLink>
              </div>
            </div>
            <hr class="border-custom-stroke last:hidden" />
          </template>
        </div>
      </section>
    </div>
  </div>
</template>
