<script setup>
defineProps({
  actions: {
    type: Array,
    default: () => [] // [{ label: 'Pending Orders', count: 5, route: '...', icon: '...' }]
  }
})
</script>

<template>
  <div class="flex flex-col w-full rounded-[20px] bg-white border border-gray-100 overflow-hidden">
    <div class="p-5 border-b border-gray-100 bg-gray-50/50">
      <h3 class="font-bold text-lg text-custom-black">Things to do</h3>
      <p class="text-xs text-custom-grey">Prioritize these tasks today</p>
    </div>

    <div class="flex flex-col">
      <template v-if="actions.length > 0">
        <RouterLink
          v-for="(action, index) in actions"
          :key="index"
          :to="action.route || '#'"
          class="flex items-center justify-between p-4 hover:bg-blue-50 transition-colors border-b last:border-0 border-gray-50 group"
        >
          <div class="flex items-center gap-3">
            <div
              class="size-10 rounded-full bg-white border border-gray-100 flex items-center justify-center group-hover:border-custom-blue group-hover:text-custom-blue text-custom-grey transition-colors"
            >
              <img
                :src="`/src/assets/images/icons/${action.icon}`"
                class="size-5 opacity-60 group-hover:opacity-100"
              />
            </div>
            <span class="font-medium text-custom-black text-sm">{{ action.label }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="font-bold text-custom-orange">{{ action.count }}</span>
            <i
              class="fa-solid fa-chevron-right text-xs text-gray-300 group-hover:text-custom-blue"
            ></i>
          </div>
        </RouterLink>
      </template>
      <div v-else class="p-8 flex flex-col items-center justify-center text-center gap-2">
        <div class="size-12 rounded-full bg-green-100 flex items-center justify-center mb-1">
          <i class="fa-solid fa-check text-custom-green text-xl"></i>
        </div>
        <p class="text-sm font-bold text-custom-black">All clared!</p>
        <p class="text-xs text-custom-grey">Great job, you have no pending tasks.</p>
      </div>
    </div>
  </div>
</template>
