export const LIVING_CHICK = 'living_chick'

const STATUS_TONE = Object.freeze({
  living_chick: 'ok',
  unfertilized: 'warn',
  failed_to_develop: 'bad',
  failed_to_hatch: 'bad',
})

const STATUS_SHORT = Object.freeze({
  living_chick: 'Living chick',
  unfertilized: 'Unfertilized',
  failed_to_develop: 'Failed to develop',
  failed_to_hatch: 'Failed to hatch',
})

export function eggStatusTone(status) {
  return STATUS_TONE[status] || 'muted'
}

export function eggStatusShort(status, fallback) {
  return STATUS_SHORT[status] || fallback || status || '—'
}

export function isLivingEgg(egg) {
  return !egg?.egg_status || egg.egg_status === LIVING_CHICK
}
