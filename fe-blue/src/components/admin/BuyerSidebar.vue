<script setup>
import { onMounted, watch, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useChatStore } from '@/stores/chat'
import { useRoute } from 'vue-router'
import BuyerSidebarContent from '@/components/admin/sidebar/BuyerSidebarContent.vue'
import { Sheet, SheetContent, SheetTitle, SheetDescription } from '@/components/ui/sheet'

const route = useRoute()
const authStore = useAuthStore()
const chatStore = useChatStore()
const { user } = storeToRefs(authStore)

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'update:isOpen'])

const sheetOpen = ref(props.isOpen)

watch(
  () => props.isOpen,
  (val) => {
    sheetOpen.value = val
  }
)

watch(sheetOpen, (val) => {
  if (!val) {
    emit('close')
  }
})

onMounted(() => {
  if (user.value) {
    chatStore.fetchContacts()
    chatStore.initializeChatListener(user.value.id)
  }
})

watch(user, (newUser) => {
  if (newUser) {
    chatStore.fetchContacts()
    chatStore.initializeChatListener(newUser.id)
  }
})

watch(
  () => route.fullPath,
  () => {
    if (window.innerWidth < 768 && sheetOpen.value) {
      sheetOpen.value = false
      emit('close')
    }
  }
)
</script>

<template>
  <!-- Desktop Sidebar (Hidden on Mobile) -->
  <aside
    class="hidden md:flex flex-col w-[280px] bg-white dark:bg-surface-card border-r border-custom-stroke dark:border-white/10 h-screen sticky top-0 overflow-hidden"
  >
    <BuyerSidebarContent />
  </aside>

  <!-- Mobile Sidebar (Shadcn Sheet) -->
  <Sheet v-model:open="sheetOpen">
    <SheetContent side="left" class="p-0 border-r-0 w-[85%] sm:max-w-[300px] [&>button]:hidden">
      <SheetTitle class="sr-only">Navigation Menu</SheetTitle>
      <SheetDescription class="sr-only">Main sidebar navigation for the application</SheetDescription>
      <BuyerSidebarContent>
        <template #close-button>
          <!-- Close Button already handled by Sheet but we can add extra if needed -->
        </template>
      </BuyerSidebarContent>
    </SheetContent>
  </Sheet>
</template>
