const REGIONS = [
  { key: 'eyes', label: 'Eyes', tokens: ['eyes', 'eye'] },
  { key: 'head', label: 'Head', tokens: ['head', 'face', 'mask', 'forehead', 'crown', 'cheeks', 'cheek'] },
  { key: 'neck', label: 'Neck', tokens: ['neck', 'collar', 'nape'] },
  { key: 'body', label: 'Body', tokens: ['body', 'breast', 'chest', 'belly', 'abdomen', 'plumage'] },
  { key: 'wings', label: 'Wings', tokens: ['wings', 'wing'] },
  { key: 'rump', label: 'Rump', tokens: ['rump'] },
  { key: 'tail', label: 'Tail', tokens: ['tail'] },
]

const TOKEN_TO_REGION = Object.fromEntries(
  REGIONS.flatMap((region) => region.tokens.map((token) => [token, region.key])),
)

const PART = 'eyes?|head|face|mask|forehead|crown|cheeks?|neck|collar|nape|body|breast|chest|belly|abdomen|plumage|wings?|rump|tail'
const SKIP_PREFIX = /^(the|a|an|this|that|same|one|two|not|no|its|their|his|her|any|each|every|both)$/i
const MISSING = /not specified|uncertain|n\/a|^—$|^-$/i

export const PHENOTYPE_REGION_ORDER = REGIONS.map((region) => region.key)

export function buildPhenotypeRegions(source = {}) {
  const catalogMap = readStructured(source.head_to_tail || {})
  const parsed = parsePhenotypeText(collectText(source))
  const structured = readStructured(source)
  const hints = hintsFromTraits(source)

  return REGIONS.map((region) => {
    const value = firstUsable(catalogMap[region.key], structured[region.key], parsed[region.key], hints[region.key])
    return {
      key: region.key,
      label: region.label,
      value: value || 'Not specified',
      specified: Boolean(value),
    }
  })
}

function hintsFromTraits(source) {
  const mutations = [...(source.visualMutations || source.visual_mutations || [])]
  const base = String(source.baseColor || source.base_color || '')
  const visible = [base, ...mutations].filter(Boolean).join(' · ')
  const hints = {}

  const color = visible.match(/turquoise|cobalt|mauve|seagreen|dark green|olive aqua|olive|aqua|\bblue\b|lutino|albino|\byellow\b|\bgreen\b|\bpeach\b/i)
  if (color) hints.body = color[0].replace(/^\w/, (letter) => letter.toUpperCase())

  if (/sl ino|nsl ino|lutino|albino|bronze fallow|pale fallow|\bfallow\b/i.test(visible)) hints.eyes = 'Red'
  if (/(sl ino|nsl ino|lutino)/i.test(visible) && /green/i.test(visible)) hints.body = 'Yellow'
  if (/(sl ino|nsl ino|albino)/i.test(visible) && /blue|turquoise/i.test(visible)) hints.body = 'White'
  if (/pale headed/i.test(visible)) hints.head = 'Reduced mask'
  if (/orange face/i.test(visible)) hints.head = 'Orange face'
  if (/\bpeach\b/i.test(visible) && !/pale headed/i.test(visible)) hints.head = hints.head || 'Peach'
  if (/violet/i.test(visible)) hints.body = joinHint(hints.body, 'Violet wash')
  if (/misty/i.test(visible)) hints.body = joinHint(hints.body, 'Misty')
  if (/greywing/i.test(visible)) hints.wings = 'Greywing'
  if (/opaline/i.test(visible)) {
    hints.head = joinHint(hints.head, 'Mask spreads over the head')
    hints.rump = 'Rump pattern changes'
  }
  if (/cinnamon/i.test(visible)) hints.body = joinHint(hints.body, 'Yellow-green')
  return hints
}

function joinHint(current, extra) {
  if (!current) return extra
  if (String(current).toLowerCase().includes(extra.toLowerCase())) return current
  return `${current} · ${extra}`
}

function collectText(source) {
  const chunks = [
    source.phenotype,
    source.phenotype_structured?.description,
    source.phenotype_structured?.display,
    source.visual_description,
    source.pattern,
    source.markings,
    source.other_visual_characteristics,
    source.other_calculated_visual_characteristics,
    source.ai_visual_description?.summary,
    ...(source.visualMutations || source.visual_mutations || []),
    source.baseColor || source.base_color,
  ]
  return chunks.filter((item) => typeof item === 'string' && item.trim() && !MISSING.test(item)).join('. ')
}

function readStructured(source = {}) {
  const ai = source.ai_visual_description || source.ai_interpretation || {}
  const keys = ['eyes', 'head', 'neck', 'body', 'wings', 'rump', 'tail']
  const out = {}
  keys.forEach((key) => {
    const value = firstUsable(source[key], ai[key], source.visualization?.[key])
    if (value) out[key] = value
  })
  return out
}

function firstUsable(...values) {
  for (const value of values) {
    if (!isUsableField(value)) continue
    if (String(value).length > 90) {
      const parsed = parsePhenotypeText(value)
      const hit = Object.values(parsed)[0]
      if (hit) return hit
      continue
    }
    return tidyValue(value)
  }
  return null
}

function isUsableField(value) {
  if (value == null) return false
  const text = String(value).trim()
  return text !== '' && !MISSING.test(text)
}

export function parsePhenotypeText(text) {
  const found = {}
  if (!text) return found
  const src = String(text)

  applyMatches(found, src, new RegExp(`\\b(${PART})\\s*[:\\-]\\s*([^,.;]+)`, 'gi'), (part, detail) => (
    { region: regionFor(part), value: tidyValue(detail, regionFor(part)) }
  ))

  applyMatches(found, src, /\b(red|dark|pale|brown)[-\s]?eyed\b/gi, (color) => (
    { region: 'eyes', value: tidyValue(color, 'eyes') }
  ))
  if (/\bred eyes\b/i.test(src)) found.eyes = found.eyes || 'Red'
  if (/\bdark eyes\b/i.test(src)) found.eyes = found.eyes || 'Dark'

  applyMatches(found, src, new RegExp(`\\b([A-Za-z][A-Za-z-]*(?:\\s+[A-Za-z][A-Za-z-]*){0,2})\\s+(${PART})\\b`, 'gi'), (detail, part) => {
    if (SKIP_PREFIX.test(detail.trim().split(/\s+/)[0] || '')) return null
    if (/^(is|are|looks?|rather|than|from|with|and|or)$/i.test(detail.trim())) return null
    return { region: regionFor(part), value: tidyValue(`${detail} ${part}`, regionFor(part)) }
  })

  applyMatches(found, src, new RegExp(`\\b([A-Za-z][A-Za-z-]+)\\s+on the\\s+(${PART})\\b`, 'gi'), (detail, part) => (
    { region: regionFor(part), value: tidyValue(detail, regionFor(part)) }
  ))

  return found
}

function applyMatches(found, src, pattern, pick) {
  const regex = new RegExp(pattern.source, pattern.flags)
  let match = regex.exec(src)
  while (match) {
    const picked = pick(...match.slice(1))
    if (picked?.region && picked.value && !found[picked.region]) {
      found[picked.region] = picked.value
    }
    match = regex.exec(src)
  }
}

function regionFor(token) {
  return TOKEN_TO_REGION[String(token || '').toLowerCase()] || null
}

function tidyValue(raw, region) {
  let value = String(raw || '').replace(/\s+/g, ' ').trim().replace(/[.,;:]+$/, '')
  if (!value) return null
  if (region) {
    const tokens = REGIONS.find((item) => item.key === region)?.tokens || []
    const tail = new RegExp(`\\s+(${tokens.join('|')})$`, 'i')
    value = value.replace(tail, '').trim()
  }
  if (!value || value.length > 48) return null
  return value.replace(/^\w/, (letter) => letter.toUpperCase())
}
