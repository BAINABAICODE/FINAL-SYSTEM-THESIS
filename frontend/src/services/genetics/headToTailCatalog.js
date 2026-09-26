const REGIONS = ['eyes', 'head', 'neck', 'body', 'wings', 'rump', 'tail']

export function normalizeMutationName(name) {
  return String(name || '')
    .toLowerCase()
    .replace(/[’`]/g, "'")
    .replace(/[^a-z0-9*+\s'-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

export function composeHeadToTail(catalog, speciesId, mutationNames = [], baseColor = null) {
  if (!catalog || speciesId == null) return null
  const identity = (catalog.species_identities || []).find((row) => Number(row.species_id) === Number(speciesId))
  if (!identity) return null

  const map = Object.fromEntries(REGIONS.map((key) => [key, identity[key] || '']))
  const overlays = catalog.overlays || {}
  const notes = [identity.pigment_notes].filter(Boolean)
  const visible = []
  const names = (mutationNames || []).filter(Boolean)
  const hasIno = names.some((name) => {
    const key = normalizeMutationName(name)
    return key === 'sl ino' || key === 'nsl ino'
  })
  const isBlue = /\b(blue|cobalt|mauve)\b/i.test(String(baseColor || ''))

  names.forEach((name) => {
    const key = normalizeMutationName(name)
    if (!key) return
    if (hasIno && (key === 'violet' || key === 'double violet')) {
      notes.push('Ino covers the violet wash, so violet is not a separate visible color.')
      return
    }
    if (isBlue && (key === 'orange face' || key === 'pale headed' || key === 'pale headed df')) {
      notes.push(`${name} is not a visible mutation on a blue ground.`)
      return
    }
    const seeded = names.length === 1
      ? (catalog.visual_mutations || []).find((row) => (
        Number(row.species_id) === Number(speciesId) && normalizeMutationName(row.mutation_name) === key
      ))
      : null
    if (seeded) {
      REGIONS.forEach((region) => {
        if (seeded[region]) map[region] = seeded[region]
      })
      if (seeded.pigment_notes) notes.push(seeded.pigment_notes)
      visible.push(name)
      return
    }
    const overlay = overlays[key]
    if (!overlay) return
    REGIONS.forEach((region) => {
      if (overlay[region]) map[region] = overlay[region]
    })
    if (overlay.pigment_notes) notes.push(overlay.pigment_notes)
    visible.push(name)
  })

  return {
    ...map,
    pigment_notes: [...new Set(notes)].join(' '),
    species_id: Number(speciesId),
    visual_mutations: visible,
  }
}

export function attachHeadToTail(source, catalog, speciesId) {
  if (!source || typeof source !== 'object') return source
  if (hasCompleteMap(source.head_to_tail) || hasCompleteMap(source)) return source
  const composed = composeHeadToTail(
    catalog,
    speciesId ?? source.species_id ?? source.species?.id ?? source.head_to_tail?.species_id,
    source.visual_mutations || source.visualMutations || [],
    source.base_color || source.baseColor,
  )
  if (!composed) return source
  return { ...source, ...composed, head_to_tail: composed }
}

function hasCompleteMap(map) {
  if (!map || typeof map !== 'object') return false
  return REGIONS.every((key) => {
    const value = String(map[key] || '').trim()
    return value !== '' && !/not specified/i.test(value)
  })
}
