export function percentText(value, digits = 1) {
  if (!Number.isFinite(Number(value))) return '—'
  const scaled = Number(value) <= 1 ? Number(value) * 100 : Number(value)
  const rounded = Math.round(scaled * 10 ** digits) / 10 ** digits
  return `${Number.isInteger(rounded) ? rounded : rounded.toFixed(digits)}%`
}
