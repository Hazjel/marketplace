<script setup>
import { computed } from 'vue'

const props = defineProps({
  review: {
    type: Object,
    required: true
  }
})

const userAvatar = computed(() => {
  if (props.review.is_anonymous) return 'https://ui-avatars.com/api/?name=A&background=random'
  return (
    props.review.user?.profile_picture ||
    `https://ui-avatars.com/api/?name=${props.review.user?.name}&background=random`
  )
})

const userName = computed(() => {
  return props.review.is_anonymous ? 'Pengguna Anonim' : props.review.user?.name || 'Unknown User'
})

const formattedDate = computed(() => {
  return new Date(props.review.created_at).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
})
</script>

<template>
  <div
    class="flex flex-col gap-4 p-4 md:p-6 bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow"
  >
    <!-- Header: User & Rating -->
    <div class="flex items-start justify-between">
      <div class="flex items-center gap-3">
        <img
          :src="userAvatar"
          class="w-10 h-10 rounded-full object-cover border border-gray-100"
          alt="Avatar"
        />
        <div class="flex flex-col">
          <span class="font-bold text-sm text-custom-black">{{ userName }}</span>
          <span class="text-xs text-custom-grey">{{ formattedDate }}</span>
        </div>
      </div>
      <!-- Stars -->
      <div class="flex gap-0.5">
        <img
          v-for="i in 5"
          :key="i"
          :src="
            i <= review.rating
              ? '/src/assets/images/icons/Star-pointy.svg'
              : '/src/assets/images/icons/Star-pointy-outline.svg'
          "
          class="w-4 h-4"
          :alt="i <= review.rating ? 'Full Star' : 'Empty Star'"
        />
      </div>
    </div>

    <!-- Review Text -->
    <p class="text-custom-black text-sm leading-relaxed whitespace-pre-line">
      {{ review.review }}
    </p>

    <!-- Attachments -->
    <div
      v-if="review.attachments && review.attachments.length > 0"
      class="flex flex-wrap gap-2 mt-1"
    >
      <div
        v-for="(media, idx) in review.attachments"
        :key="idx"
        class="w-16 h-16 rounded-lg overflow-hidden border border-gray-100 cursor-pointer"
      >
        <img
          v-if="media.file_type === 'image'"
          :src="media.file_path"
          class="w-full h-full object-cover hover:scale-110 transition-transform"
        />
        <video v-else :src="media.file_path" class="w-full h-full object-cover"></video>
      </div>
    </div>

    <!-- Product Context -->
    <div v-if="review.product" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl mt-1">
      <img
        :src="review.product.product_images?.[0]?.image || '/src/assets/images/photos/photo-1.png'"
        class="w-10 h-10 rounded-lg object-cover bg-white border border-gray-100"
        alt="Product"
      />
      <div class="flex flex-col min-w-0">
        <span class="text-xs font-bold text-custom-black truncate">{{ review.product.name }}</span>
        <span class="text-[10px] text-custom-grey">Varian: Default</span>
      </div>
    </div>
  </div>
</template>
