<script setup>
import { ref, watch, computed } from 'vue'
import { LMap, LTileLayer, LMarker } from '@vue-leaflet/vue-leaflet'
import 'leaflet/dist/leaflet.css'
import L from 'leaflet'
import { axiosInstance } from '@/plugins/axios'
import { logger } from '@/utils/logger'
import { debounce } from 'lodash'

// Fix Leaflet default marker icon broken in bundlers
delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).href,
  iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).href,
  shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).href
})

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({ latitude: null, longitude: null })
  },
  height: { type: String, default: 'h-72' }
})

const emit = defineEmits(['update:modelValue', 'address'])

const INDONESIA_CENTER = [-2.5, 118.0]

const hasCoords = computed(
  () => props.modelValue?.latitude != null && props.modelValue?.longitude != null
)

const zoom = ref(hasCoords.value ? 16 : 5)
const center = ref(
  hasCoords.value ? [props.modelValue.latitude, props.modelValue.longitude] : INDONESIA_CENTER
)
const markerLatLng = ref(
  hasCoords.value ? [props.modelValue.latitude, props.modelValue.longitude] : null
)
const locating = ref(false)
const resolving = ref(false)
const geoError = ref('')
const mapRef = ref(null)

// Sinkron kalau parent mengisi koordinat belakangan (mis. mode edit selesai fetch)
watch(
  () => [props.modelValue?.latitude, props.modelValue?.longitude],
  ([lat, lng]) => {
    if (lat == null || lng == null) return
    const current = markerLatLng.value
    if (current && current[0] === lat && current[1] === lng) return
    markerLatLng.value = [lat, lng]
    center.value = [lat, lng]
    if (zoom.value < 15) zoom.value = 16
  }
)

const reverseGeocode = debounce(async ([lat, lng]) => {
  resolving.value = true
  try {
    const res = await axiosInstance.get('/shipment/reverse-geocode', {
      params: { lat, lon: lng }
    })
    if (res.data?.data) emit('address', res.data.data)
  } catch (err) {
    logger.error('Reverse geocode failed', err)
  } finally {
    resolving.value = false
  }
}, 800)

const setMarker = (lat, lng, { pan = false } = {}) => {
  const rounded = [Number(lat.toFixed(7)), Number(lng.toFixed(7))]
  markerLatLng.value = rounded
  if (pan) {
    center.value = rounded
    if (zoom.value < 15) zoom.value = 16
  }
  emit('update:modelValue', { latitude: rounded[0], longitude: rounded[1] })
  reverseGeocode(rounded)
}

const handleMapClick = (e) => {
  if (!e?.latlng) return
  setMarker(e.latlng.lat, e.latlng.lng)
}

const handleMarkerDragEnd = (e) => {
  const pos = e?.target?.getLatLng?.()
  if (!pos) return
  setMarker(pos.lat, pos.lng)
}

const useMyLocation = () => {
  geoError.value = ''
  if (!navigator.geolocation) {
    geoError.value = 'Browser tidak mendukung geolokasi.'
    return
  }
  locating.value = true
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      locating.value = false
      setMarker(pos.coords.latitude, pos.coords.longitude, { pan: true })
    },
    (err) => {
      locating.value = false
      geoError.value =
        err.code === err.PERMISSION_DENIED
          ? 'Izin lokasi ditolak. Klik peta untuk menandai lokasi secara manual.'
          : 'Gagal mengambil lokasi. Klik peta untuk menandai lokasi secara manual.'
    },
    { enableHighAccuracy: true, timeout: 10000 }
  )
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <div
      class="relative rounded-2xl overflow-hidden border border-gray-200 dark:border-white/10"
      :class="height"
    >
      <LMap
        ref="mapRef"
        v-model:zoom="zoom"
        v-model:center="center"
        :use-global-leaflet="false"
        class="h-full w-full z-0"
        @click="handleMapClick"
      >
        <LTileLayer
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        />
        <LMarker
          v-if="markerLatLng"
          :lat-lng="markerLatLng"
          :draggable="true"
          @dragend="handleMarkerDragEnd"
        />
      </LMap>

      <!-- Locate button -->
      <button
        type="button"
        :disabled="locating"
        class="absolute top-3 right-3 z-[500] flex items-center gap-2 h-9 px-3 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-white/10 shadow-md text-xs font-medium text-custom-black dark:text-white hover:border-custom-blue transition-colors disabled:opacity-60"
        @click="useMyLocation"
      >
        <div
          v-if="locating"
          class="size-4 border-2 border-custom-blue border-t-transparent rounded-full animate-spin"
        ></div>
        <svg v-else class="size-4 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <span>{{ locating ? 'Mencari...' : 'Gunakan Lokasi Saya' }}</span>
      </button>

      <!-- Resolving address indicator -->
      <div
        v-if="resolving"
        class="absolute bottom-3 left-3 z-[500] flex items-center gap-2 h-8 px-3 rounded-full bg-white/90 dark:bg-gray-800/90 border border-gray-200 dark:border-white/10 shadow-md text-xs font-medium text-custom-grey dark:text-gray-300"
      >
        <div class="size-3.5 border-2 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
        Mengambil alamat...
      </div>
    </div>

    <p v-if="geoError" class="text-xs font-medium text-red-500 px-1">{{ geoError }}</p>
    <p v-else class="text-xs text-custom-grey dark:text-gray-400 px-1">
      {{ markerLatLng ? 'Geser pin atau klik peta untuk menyesuaikan titik lokasi.' : 'Klik peta atau gunakan lokasi saya untuk menandai titik pengiriman.' }}
    </p>
  </div>
</template>
