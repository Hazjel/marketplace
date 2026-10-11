import numeral from 'numeral'
import { DateTime } from 'luxon'

// Indonesian grouping ("18.100"), matching the emails. Input is a number or
// a plain numeric string from the API ("18100" / "18100.00").
export function formatRupiah(value) {
  const number = Number(value)
  return (Number.isFinite(number) ? number : 0).toLocaleString('id-ID', {
    maximumFractionDigits: 2
  })
}

// Reads back what formatRupiah wrote into a text input ("1.250.000"): rupiah
// are whole, so every non-digit is a separator. Numbers pass through as-is.
export function parseRupiah(value) {
  if (typeof value === 'number') return value
  return Number(String(value ?? '').replace(/\D/g, '')) || 0
}

export function formatPercentage(value) {
  return numeral(value).format('0,0[.]00%')
}

export function formatDate(date) {
  const options = { day: 'numeric', month: 'long', year: 'numeric' }
  return new Date(date).toLocaleDateString('id-ID', options)
}

export function formatDateTime(dateTime) {
  const options = {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: 'numeric',
    minute: 'numeric'
  }
  return new Date(dateTime).toLocaleDateString('id-ID', options)
}

export function formatToClientTimeZone(date) {
  const originalDate = DateTime.fromISO(date, { zone: 'utc' })

  const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone

  return originalDate.setZone(timezone).setLocale('id').toFormat('dd LLLL yyyy, HH:mm')
}

export function ucFirst(string) {
  return string ? string.charAt(0).toUpperCase() + string.slice(1) : ''
}
