<script setup>
import { ref, onMounted, computed } from 'vue'
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

const COURIER_TRACKING_URLS = {
  'jne':       { url: 'https://www.jne.co.id/id/tracking/trace/', suffix: false },
  'j&t':       { url: 'https://jet.co.id/track/', suffix: false },
  'sicepat':   { url: 'https://www.sicepat.com/checkAwb/', suffix: false },
  'tiki':      { url: 'https://www.tiki.id/id/tracking?awb=', suffix: false },
  'anteraja':  { url: 'https://anteraja.id/tracking/', suffix: false },
  'pos':       { url: 'https://www.posindonesia.co.id/en/tracking/', suffix: false },
  'ninja':     { url: 'https://www.ninjaxpress.co/id-id/tracking?awb=', suffix: false },
  'lion':      { url: 'https://lionparcel.com/tracking?resi=', suffix: false },
  'wahana':    { url: 'https://www.wahana.com/tracking?no_resi=', suffix: false },
  'ide':       { url: 'https://idexpress.com/tracking?awb=', suffix: false },
  'sap':       { url: 'https://www.sap-express.id/cek-resi?awb=', suffix: false },
}

const STATUS_LABELS = {
  'pending':    { label: 'Menunggu Konfirmasi', color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' },
  'processing': { label: 'Diproses Penjual',    color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
  'delivering': { label: 'Dalam Pengiriman',    color: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' },
  'completed':  { label: 'Terkirim',            color: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' },
}

const props = defineProps({
  storeCity:      { type: String, default: '' },
  buyerCity:      { type: String, default: '' },
  storeLat:       { type: [Number, String], default: null },
  storeLng:       { type: [Number, String], default: null },
  destLat:        { type: [Number, String], default: null },
  destLng:        { type: [Number, String], default: null },
  trackingNumber: { type: String, default: '' },
  shipping:       { type: String, default: '' },
  deliveryStatus: { type: String, default: '' },
})

const zoom = ref(5)
const center = ref([-2.5, 118.0])
const originCoords = ref(null)
const destCoords = ref(null)
const polylineLatLngs = ref([])
const loadingMap = ref(false)

const courierTrackingUrl = computed(() => {
  if (!props.trackingNumber || !props.shipping) return null
  const key = Object.keys(COURIER_TRACKING_URLS).find(k =>
    props.shipping.toLowerCase().includes(k)
  )
  if (!key) return null
  return COURIER_TRACKING_URLS[key].url + props.trackingNumber
})

const statusInfo = computed(() => STATUS_LABELS[props.deliveryStatus] || null)

const geocodeCity = async (city) => {
  if (!city) return null
  try {
    const res = await axiosInstance.get('/shipment/geocode', { params: { city } })
    if (res.data?.lat && res.data?.lon) {
      return [parseFloat(res.data.lat), parseFloat(res.data.lon)]
    }
  } catch {
    // geocoding failed silently
  }
  return null
}

const loadMap = async () => {
  if (!props.storeCity && !props.buyerCity && props.storeLat == null) return
  loadingMap.value = true
  // Pakai koordinat persis (toko/alamat) kalau tersedia, fallback geocode nama kota
  const hasStoreCoords = props.storeLat != null && props.storeLng != null
  const hasDestCoords = props.destLat != null && props.destLng != null
  const [origin, dest] = await Promise.all([
    hasStoreCoords
      ? Promise.resolve([Number(props.storeLat), Number(props.storeLng)])
      : geocodeCity(props.storeCity),
    hasDestCoords
      ? Promise.resolve([Number(props.destLat), Number(props.destLng)])
      : geocodeCity(props.buyerCity),
  ])
  originCoords.value = origin
  destCoords.value = dest

  if (origin && dest) {
    polylineLatLngs.value = [origin, dest]
    center.value = [(origin[0] + dest[0]) / 2, (origin[1] + dest[1]) / 2]
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

onMounted(loadMap)
</script>

<template>
  <div class="flex flex-col gap-4">
    <!-- Map -->
    <div class="rounded-2xl overflow-hidden border border-gray-100 dark:border-white/10">
      <div class="flex items-center gap-2 px-4 py-3 bg-gray-50 dark:bg-white/5 border-b border-gray-100 dark:border-white/10">
        <svg class="size-4 text-custom-blue shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
        </svg>
        <span class="text-sm font-semibold text-custom-black dark:text-white">Peta Pengiriman</span>
        <span v-if="storeCity || buyerCity" class="ml-auto text-xs text-custom-grey dark:text-gray-400 truncate">
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

    <!-- Status + Tracking Link -->
    <div class="rounded-2xl border border-gray-100 dark:border-white/10 bg-gray-50 dark:bg-white/5 overflow-hidden">
      <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100 dark:border-white/10">
        <svg class="size-4 text-custom-blue shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        </svg>
        <span class="text-sm font-semibold text-custom-black dark:text-white">Info Pengiriman</span>
      </div>

      <div class="px-4 py-4 flex flex-col gap-3">
        <!-- Delivery status -->
        <div v-if="statusInfo" class="flex items-center justify-between">
          <span class="text-sm text-custom-grey dark:text-gray-400">Status</span>
          <span class="text-xs font-bold px-3 py-1.5 rounded-full" :class="statusInfo.color">
            {{ statusInfo.label }}
          </span>
        </div>

        <!-- Courier & AWB -->
        <div v-if="shipping" class="flex items-center justify-between">
          <span class="text-sm text-custom-grey dark:text-gray-400">Kurir</span>
          <span class="text-sm font-semibold text-custom-black dark:text-white">{{ shipping }}</span>
        </div>
        <div v-if="trackingNumber" class="flex items-center justify-between">
          <span class="text-sm text-custom-grey dark:text-gray-400">No. Resi</span>
          <span class="text-sm font-mono font-semibold text-custom-black dark:text-white">{{ trackingNumber }}</span>
        </div>

        <!-- Tracking link -->
        <a
          v-if="courierTrackingUrl"
          :href="courierTrackingUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="mt-1 flex items-center justify-center gap-2 h-10 w-full rounded-xl bg-custom-blue text-white text-sm font-semibold hover:bg-blue-700 transition-colors"
        >
          <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
          </svg>
          Lacak di Website {{ shipping }}
        </a>
        <p v-else-if="trackingNumber" class="text-xs text-custom-grey dark:text-gray-400 text-center">
          Salin nomor resi dan lacak di website kurir secara manual.
        </p>
      </div>
    </div>
  </div>
</template>
