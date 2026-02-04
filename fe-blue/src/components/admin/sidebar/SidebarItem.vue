<script setup>
import { can, hasRole } from '@/helpers/permissionHelper'
import { computed, ref, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const props = defineProps({
  item: {
    type: Object,
    required: true
  }
})

const isActive = computed(() => {
  return route.path === props.item.path || route.path.startsWith(`${props.item.path}/`)
})

const hasActiveChild = computed(() => {
  if (!props.item.children) return false
  return props.item.children.some((child) => {
    return route.path === child.path || route.path.startsWith(`${child.path}/`)
  })
})

const isOpen = ref(false)

onMounted(() => {
  if (hasActiveChild.value) {
    isOpen.value = true
  }
})

watch(route, () => {
  if (hasActiveChild.value) {
    isOpen.value = true
  }

  if (!hasActiveChild.value) {
    isOpen.value = false
  }
})
</script>

<template>
  <li v-if="!item.children && can(item.permission) && hasRole(item.role)" class="group" :class="{ active: isActive }"
    v-bind="$attrs">
    <RouterLink v-if="item.path" :to="item.path"
      class="flex items-center w-full min-h-14 gap-2 rounded-2xl overflow-hidden py-[10px] pl-4 group-[&.active]:bg-custom-blue/10 dark:group-[&.active]:bg-blue-500/20 transition-300">
      <div class="relative flex size-6 shrink-0">
        <img :src="item.iconDefault"
          class="size-6 absolute opacity-100 group-[&.active]:opacity-0 transition-300 dark:invert" alt="icon" />
        <img :src="item.iconActive" class="size-6 absolute opacity-0 group-[&.active]:opacity-100 transition-300"
          alt="icon" />
      </div>
      <p
        class="font-medium group-[&.active]:text-custom-blue dark:text-white dark:group-[&.active]:text-blue-400 transition-300 w-full">
        {{ item.label }}
      </p>
      <div v-if="item.badge"
        class="text-white text-[10px] font-bold px-1.5 h-5 min-w-5 flex items-center justify-center rounded-full ml-auto mr-2"
        style="background-color: red">
        {{ item.badge > 99 ? '99+' : item.badge }}
      </div>
      <div class="w-2 h-9 shrink-0 rounded-l-xl bg-custom-blue hidden group-[&.active]:flex transition-300"></div>
    </RouterLink>
    <div v-else
      class="flex items-center w-full min-h-14 gap-2 rounded-2xl overflow-hidden py-[10px] pl-4 group-[&.active]:bg-custom-blue/10 dark:group-[&.active]:bg-blue-500/20 transition-300 cursor-pointer">
      <div class="relative flex size-6 shrink-0">
        <img :src="item.iconDefault"
          class="size-6 absolute opacity-100 group-[&.active]:opacity-0 transition-300 dark:invert" alt="icon" />
        <img :src="item.iconActive" class="size-6 absolute opacity-0 group-[&.active]:opacity-100 transition-300"
          alt="icon" />
      </div>
      <p class="font-medium group-[&.active]:text-custom-blue transition-300 w-full">
        {{ item.label }}
      </p>
      <div v-if="item.badge"
        class="text-white text-[10px] font-bold px-1.5 h-5 min-w-5 flex items-center justify-center rounded-full ml-auto mr-2"
        style="background-color: red">
        {{ item.badge > 99 ? '99+' : item.badge }}
      </div>
      <div class="w-2 h-9 shrink-0 rounded-l-xl bg-custom-blue hidden group-[&.active]:flex transition-300"></div>
    </div>
  </li>

  <li v-if="
    item.children && item.children.some((child) => can(child.permission) && hasRole(child.role))
  " class="group flex flex-col" v-bind="$attrs">
    <button data-accordion-type="nav"
      :data-expand="`accordion-${(item.path || item.label || 'default').replace(/[\/\s]/g, '-')}`"
      class="flex items-center w-full min-h-14 gap-2 rounded-2xl overflow-hidden py-[10px] pl-4 group-[&.active]:bg-custom-blue/10 dark:group-[&.active]:bg-blue-500/20 transition-300"
      :class="{ opened: isOpen }" @click="isOpen = !isOpen">
      <div class="relative flex size-6 shrink-0">
        <img :src="item.iconDefault"
          class="size-6 absolute opacity-100 group-[&.active]:opacity-0 transition-300 dark:invert" alt="icon" />
        <img :src="item.iconActive" class="size-6 absolute opacity-0 group-[&.active]:opacity-100 transition-300"
          alt="icon" />
      </div>
      <p
        class="font-medium group-[&.active]:text-custom-blue dark:text-white dark:group-[&.active]:text-blue-400 transition-300 w-full text-left">
        {{ item.label }}
      </p>
      <img src="@/assets/images/icons/arrow-circle-up-black.svg" class="size-6 flex shrink-0 transition-300 dark:invert"
        :class="{ 'rotate-180': isOpen }" alt="icon" />
    </button>
    <div v-show="isOpen" :id="`accordion-${(item.path || item.label || 'default').replace(/[\/\s]/g, '-')}`"
      class="flex">
      <div class="flex w-[56px] shrink-0 justify-end items-start relative">
        <div v-for="n in Math.max(
          0,
          item.children.filter((child) => can(child.permission) && hasRole(child.role)).length - 1
        )" :key="n"
          class="w-[2px] h-full bg-gray-200 dark:bg-white/10 absolute top-0 left-1/2 -translate-x-1/2 rounded-[1px] last:h-[55%]">
        </div>
      </div>
      <ul class="flex flex-col gap-1 w-full">
        <li v-for="child in item.children" :key="child.path" class="group relative"
          :class="{ active: route.path.startsWith(child.path) }">
          <div v-if="can(child.permission) && hasRole(child.role)"
            class="absolute left-0 top-0 w-[28px] h-[32px] border-l-2 border-b-2 border-gray-200 dark:border-white/10 rounded-bl-2xl bg-transparent z-[1] -translate-x-[104%]">
          </div>
          <RouterLink v-if="can(child.permission) && hasRole(child.role)" :to="child.path"
            class="flex items-center w-full min-h-14 gap-2 rounded-2xl overflow-hidden py-[10px] pl-4 group-[&.active]:bg-custom-blue/10 dark:group-[&.active]:bg-blue-500/20 transition-300"
            :class="{ active: route.path.startsWith(child.path) }">
            <div class="relative flex size-6 shrink-0">
              <img :src="child.iconDefault"
                class="size-6 absolute opacity-100 group-[&.active]:opacity-0 transition-300 dark:invert" alt="icon" />
              <img :src="child.iconActive" class="size-6 absolute opacity-0 group-[&.active]:opacity-100 transition-300"
                alt="icon" />
            </div>
            <p
              class="font-medium text-custom-grey dark:text-gray-400 group-[&.active]:text-custom-blue dark:group-[&.active]:text-blue-400 transition-300 w-full">
              {{ child.label }}
            </p>
            <div class="w-2 h-9 shrink-0 rounded-l-xl bg-custom-blue hidden group-[&.active]:flex transition-300"></div>
          </RouterLink>
        </li>
      </ul>
    </div>
  </li>
</template>
