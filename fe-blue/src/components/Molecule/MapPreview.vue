<script setup>
import { computed } from 'vue'
import { LMap, LTileLayer, LMarker } from '@vue-leaflet/vue-leaflet'
import 'leaflet/dist/leaflet.css'
import L from 'leaflet'

// Fix Leaflet default marker icon broken in bundlers
delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).href,
  iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).href,
  shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).href
})

const props = defineProps({
  latitude: { type: [Number, String], required: true },
  longitude: { type: [Number, String], required: true },
  zoom: { type: Number, default: 15 },
  height: { type: String, default: 'h-40' }
})

const position = computed(() => [Number(props.latitude), Number(props.longitude)])
</script>

<template>
  <div class="rounded-xl overflow-hidden border border-gray-200 dark:border-white/10" :class="height">
    <LMap
      :zoom="zoom"
      :center="position"
      :use-global-leaflet="false"
      :options="{ scrollWheelZoom: false, dragging: false, zoomControl: false, doubleClickZoom: false, touchZoom: false }"
      class="h-full w-full z-0 pointer-events-none"
    >
      <LTileLayer
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
      />
      <LMarker :lat-lng="position" />
    </LMap>
  </div>
</template>
