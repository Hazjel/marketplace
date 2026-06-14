<script setup>
import { ref, onMounted, watch } from 'vue'
import { LMap, LTileLayer, LMarker, LTooltip, LPolyline } from '@vue-leaflet/vue-leaflet'
import 'leaflet/dist/leaflet.css'
import L from 'leaflet'
import { axiosInstance } from '@/plugins/axios'

// Fix Leaflet default marker icon broken in bundlers
delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).href,
  iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).href,
  shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).href,
})

// RajaOngkir courier codes
const COURIER_MAP = {
  'JNE': 'jne',
  'J&T': 'jnt',
  'J&T Express': 'jnt',
  'SiCepat': 'sicepat',
  'SICEPAT': 'sicepat',
  'TIKI': 'tiki',
  'AnterAja': 'anteraja',
  'Pos Indonesia': 'pos',
  'Pos': 'pos',
  'Ninja Express': 'ninja',
  'Lion Parcel': 'lion',
  'ID Express': 'ide',
  'SAP Express': 'sap',
  'Wahana': 'wahana',
  'RPX': 'rpx',
}

const props = defineProps({
  storeCity: { type: String, default: '' },
  buyerCity: { type: String, default: '' },
  trackingNumber: { type: String, default: '' },
  shipping: { type: String, default: '' },
})

const zoom = ref(5)
const center = ref([-2.5, 118.0])

const originCoords = ref(null)
const destCoords = ref(null)
const polylineLatLngs = ref([])

const trackingEvents = ref([])
const loadingTracking = ref(false)
const loadingMap = ref(false)
const trackingError = ref('')

const courierCode = () => {
  if (!props.shipping) return ''
  for (const [key, code] of Object.entries(COURIER_MAP)) {
    if (props.shipping.toLowerCase().includes(key.toLowerCase())) return code
  }
  return props.shipping.toLowerCase().replace(/\s+/g, '')
}

const geocodeCity = async (city) => {
  if (!city) return null
  try {
    const res = await axiosInstance.get('/shipment/geocode', { params: { city } })
    if (res.data?.lat && res.data?.lon) {
      return [parseFloat(res.data.lat), parseFloat(res.data.lon)]
    }
  } catch {
    // geocoding failed, map shows without pin
  }
  return null
}

const fetchTracking = async () => {
  if (!props.trackingNumber) return
  const courier = courierCode()
  if (!courier) return

  loadingTracking.value = true
  trackingError.value = ''
  try {
    const res = await axiosInstance.get('/shipment/tracking', {
      params: { awb: props.trackingNumber, courier },
    })
    const data = res.data
    if (data?.data?.history) {
      trackingEvents.value = data.data.history
    } else {
      trackingError.value = 'Data tracking belum tersedia.'
    }
  } catch {
    trackingError.value = 'Gagal memuat data tracking. Coba lagi nanti.'
  } finally {
    loadingTracking.value = false
  }
}

const loadMap = async () => {
  if (!props.storeCity && !props.buyerCity) return
  loadingMap.value = true
  const [origin, dest] = await Promise.all([
    geocodeCity(props.storeCity),
    geocodeCity(props.buyerCity),
  ])
  originCoords.value = origin
  destCoords.value = dest

  if (origin && dest) {
    polylineLatLngs.value = [origin, dest]
    const midLat = (origin[0] + dest[0]) / 2
    const midLon = (origin[1] + dest[1]) / 2
    center.value = [midLat, midLon]
    zoom.value = 6
  } else if (origin) {
    center.value = origin
    zoom.value = 8
  } else if (dest) {
    center.value = dest
    zoom.value = 8
  }
  loadingMap.value = false
}

onMounted(() => {
  loadMap()
  fetchTracking()
})

watch(() => props.trackingNumber, fetchTracking)
</script>

<template>
  <div class="flex flex-col gap-4">
    <!-- Map -->
    <div class="rounded-2xl overflow-hidden border border-gray-100 dark:border-white/10 bg-gray-50 dark:bg-white/5">
      <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100 dark:border-white/10">
        <svg class="size-4 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
        </svg>
        <span class="text-sm font-semibold text-custom-black dark:text-white">Peta Pengiriman</span>
        <span v-if="storeCity || buyerCity" class="ml-auto text-xs text-custom-grey dark:text-gray-400">
          {{ storeCity }} → {{ buyerCity }}
        </span>
      </div>

      <div class="relative h-64 w-full">
        <div v-if="loadingMap" class="absolute inset-0 flex items-center justify-center bg-gray-50 dark:bg-white/5 z-10">
          <div class="size-6 border-2 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
        </div>

        <LMap
          v-if="!loadingMap"
          :zoom="zoom"
          :center="center"
          :use-global-leaflet="false"
          class="h-full w-full z-0"
        >
          <LTileLayer
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
          />
          <LPolyline
            v-if="polylineLatLngs.length"
            :lat-lngs="polylineLatLngs"
            color="#3b82f6"
            :weight="3"
            :dash-array="'6 4'"
          />
          <LMarker v-if="originCoords" :lat-lng="originCoords">
            <LTooltip :options="{ permanent: true, direction: 'top' }">
              <span class="text-xs font-semibold">Asal: {{ storeCity }}</span>
            </LTooltip>
          </LMarker>
          <LMarker v-if="destCoords" :lat-lng="destCoords">
            <LTooltip :options="{ permanent: true, direction: 'top' }">
              <span class="text-xs font-semibold">Tujuan: {{ buyerCity }}</span>
            </LTooltip>
          </LMarker>
        </LMap>
      </div>
    </div>

    <!-- Tracking Timeline -->
    <div class="rounded-2xl border border-gray-100 dark:border-white/10 bg-gray-50 dark:bg-white/5 overflow-hidden">
      <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100 dark:border-white/10">
        <svg class="size-4 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
        <span class="text-sm font-semibold text-custom-black dark:text-white">Riwayat Pengiriman</span>
        <span v-if="trackingNumber" class="ml-auto text-xs font-mono text-custom-grey dark:text-gray-400">{{ trackingNumber }}</span>
      </div>

      <div class="px-4 py-3">
        <!-- Loading -->
        <div v-if="loadingTracking" class="flex items-center gap-2 text-sm text-custom-grey dark:text-gray-400 py-2">
          <div class="size-4 border-2 border-custom-blue border-t-transparent rounded-full animate-spin"></div>
          Memuat data tracking...
        </div>

        <!-- Error / empty -->
        <div v-else-if="trackingError || !trackingEvents.length" class="text-sm text-custom-grey dark:text-gray-400 py-2">
          {{ trackingError || 'Belum ada riwayat pengiriman.' }}
        </div>

        <!-- Timeline events -->
        <div v-else class="flex flex-col gap-0">
          <div
            v-for="(event, index) in trackingEvents"
            :key="index"
            class="flex gap-3 relative"
          >
            <!-- Line -->
            <div class="flex flex-col items-center">
              <div
                class="size-2.5 rounded-full mt-1 shrink-0 z-10"
                :class="index === 0 ? 'bg-custom-blue' : 'bg-gray-300 dark:bg-white/20'"
              ></div>
              <div v-if="index < trackingEvents.length - 1" class="w-px flex-1 bg-gray-200 dark:bg-white/10 my-1"></div>
            </div>
            <!-- Content -->
            <div class="pb-3 flex-1 min-w-0">
              <p class="text-sm font-semibold text-custom-black dark:text-white leading-snug" :class="index === 0 ? 'text-custom-blue' : ''">
                {{ event.desc || event.description }}
              </p>
              <p class="text-xs text-custom-grey dark:text-gray-400 mt-0.5">{{ event.date }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
