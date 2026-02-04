<script setup>
import { computed, onMounted, watch, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useChatStore } from '@/stores/chat'
import { useRoute } from 'vue-router'
import SidebarContent from '@/components/admin/sidebar/SidebarContent.vue'
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

// Internal state for the Sheet
const sheetOpen = ref(props.isOpen)

// Sync prop change to internal state
watch(
  () => props.isOpen,
  (val) => {
    sheetOpen.value = val
  }
)

// Sync internal state change to parent (when Sheet closes itself via backdrop/drag)
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

// Ensure we listen if user logs in later or refreshes logic
watch(user, (newUser) => {
  if (newUser) {
    chatStore.fetchContacts()
    chatStore.initializeChatListener(newUser.id)
  }
})

// Auto-close sidebar on mobile when route changes
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
    class="hidden md:flex flex-col w-[280px] bg-white dark:bg-surface-card border-r border-custom-stroke dark:border-white/10 h-screen sticky top-0 overflow-hidden">
    <SidebarContent />
  </aside>

  <!-- Mobile Sidebar (Shadcn Sheet) -->
  <Sheet v-model:open="sheetOpen">
    <SheetContent side="left" class="p-0 border-r-0 w-[85%] sm:max-w-[300px] [&>button]:hidden">
      <SheetTitle class="sr-only">Navigation Menu</SheetTitle>
      <SheetDescription class="sr-only">Main sidebar navigation for the application</SheetDescription>
      <SidebarContent>
        <template #close-button>
          <!-- Close Button already handled by Sheet but we can add extra if needed -->
        </template>
      </SidebarContent>
    </SheetContent>
  </Sheet>
</template>
