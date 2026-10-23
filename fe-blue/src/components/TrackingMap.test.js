import { beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import TrackingMap from './TrackingMap.vue'

const { get, mapMounted } = vi.hoisted(() => ({ get: vi.fn(), mapMounted: vi.fn() }))
vi.mock('@/plugins/axios', () => ({ axiosInstance: { get } }))
vi.mock('leaflet', () => ({
  default: { Icon: { Default: { prototype: {}, mergeOptions: vi.fn() } } }
}))
vi.mock('@vue-leaflet/vue-leaflet', () => ({
  LMap: { template: '<div><slot /></div>', mounted: mapMounted },
  LTileLayer: { template: '<span />' },
  LMarker: { template: '<span />' },
  LTooltip: { template: '<span />' },
  LPolyline: { template: '<span />' }
}))

beforeEach(() => {
  get.mockReset()
  mapMounted.mockClear()
})

it('waits for geocoding before mounting the map, rather than removing a map that is still initializing', async () => {
  let resolve
  get.mockReturnValue(new Promise((done) => { resolve = done }))
  const wrapper = mount(TrackingMap, { props: { storeCity: 'Jakarta', buyerCity: 'Bandung' } })
  await flushPromises()
  expect(get).toHaveBeenCalledTimes(2)
  expect(mapMounted).not.toHaveBeenCalled()

  resolve({ data: { lat: -6.2, lon: 106.8 } })
  await flushPromises()
  expect(mapMounted).toHaveBeenCalledTimes(1)
  wrapper.unmount()
})

it('renders the default map without geocoding when an order has no location', async () => {
  const wrapper = mount(TrackingMap)
  await flushPromises()
  expect(get).not.toHaveBeenCalled()
  expect(mapMounted).toHaveBeenCalledTimes(1)
  wrapper.unmount()
})

it('mounts the default map once when geocoding is unavailable', async () => {
  get.mockRejectedValue(new Error('offline'))
  const wrapper = mount(TrackingMap, { props: { storeCity: 'Jakarta', buyerCity: 'Bandung' } })
  await flushPromises()
  expect(mapMounted).toHaveBeenCalledTimes(1)
  wrapper.unmount()
})
