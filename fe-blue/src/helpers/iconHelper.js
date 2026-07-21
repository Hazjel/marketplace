// Resolve nama file icon dinamis (mis. dari data/props) ke URL yang valid
// di production build. Path string manual seperti `/src/assets/...` cuma
// jalan di dev-server Vite — hilang total setelah build (asset di-hash & pindah
// folder). import.meta.glob eager scan semua svg saat build supaya bundler
// tetap bisa resolve referensi dinamis ini.
const icons = import.meta.glob('@/assets/images/icons/*.svg', {
  eager: true,
  import: 'default'
})

export function resolveIconUrl(filename) {
  if (!filename) return ''
  const entry = Object.entries(icons).find(([path]) => path.endsWith(`/${filename}`))
  return entry ? entry[1] : ''
}
