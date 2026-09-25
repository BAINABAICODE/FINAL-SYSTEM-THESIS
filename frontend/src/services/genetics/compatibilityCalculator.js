import { COMPATIBILITY_CLASSIFICATION, GICA_FACTOR_SYMBOLS, GICA_TOTAL_POINTS } from './constants'

/**
 * Presents the backend GICA computation as an explicit weighted formula.
 *
 * GicaAnalyzer awards `points` out of `max_points` per factor and sums them to 100.
 * That is algebraically the weighted model
 *   Score = Σ (W_i × Raw_i),   W_i = max_i / Σ max,   Raw_i = points_i / max_i × 100
 * so every weight and contribution shown here is derived from the API payload; nothing
 * is re-scored on the client. The result is cross-checked against `gica.score` and any
 * mismatch is surfaced instead of hidden.
 */

export function parseClassificationScale(scale) {
  if (!scale || typeof scale !== 'object') return null
  const bands = Object.entries(scale)
    .map(([label, range]) => {
      const match = String(range).match(/(\d+)\s*[–-]\s*(\d+)/)
      if (!match) return null
      const fallback = COMPATIBILITY_CLASSIFICATION.find((band) => band.label === label)
      return { label, min: Number(match[1]), max: Number(match[2]), tone: fallback?.tone || 'moderate', guidance: fallback?.guidance || label }
    })
    .filter(Boolean)
    .sort((a, b) => b.min - a.min)
  return bands.length ? bands : null
}

export function classifyCompatibility(score, scale) {
  const bands = parseClassificationScale(scale) || COMPATIBILITY_CLASSIFICATION
  if (score == null || !Number.isFinite(Number(score))) return { label: 'Not documented', tone: 'muted', guidance: 'Not documented', bands }
  const value = Math.round(Number(score))
  const band = bands.find((b) => value >= b.min && value <= b.max) || bands[bands.length - 1]
  return { ...band, bands }
}

/**
 * @param {object} gica `result_presentation.gica`
 * @param {object} [species] `result_presentation.species_compatibility`
 */
export function buildCompatibilityModel(gica = {}, species = {}) {
  const rawBreakdown = Array.isArray(gica.breakdown) ? gica.breakdown : []
  const breakdown = rawBreakdown.filter((row) => Number(row?.max_points) > 0)
  const legacy = rawBreakdown.length > 0 && breakdown.length === 0
  const totalMax = breakdown.reduce((sum, row) => sum + (Number(row.max_points) || 0), 0) || GICA_TOTAL_POINTS

  const factors = breakdown.map((row, index) => {
    const max = Number(row.max_points) || 0
    const points = Number(row.points ?? row.contribution) || 0
    const weight = totalMax ? max / totalMax : 0
    const raw = max ? (points / max) * 100 : 0
    const weighted = raw * weight
    return {
      key: row.key || slugify(row.factor) || `factor-${index}`,
      name: row.factor || `Factor ${index + 1}`,
      symbol: GICA_FACTOR_SYMBOLS[row.key] || `W(${row.key})`,
      value: row.value ?? null,
      detail: row.detail ?? null,
      max,
      points,
      weight,
      weightPercent: weight * 100,
      raw,
      weighted,
      formula: `${format(raw)} × ${format(weight)} = ${format(weighted)}`,
    }
  })

  const computedTotal = factors.reduce((sum, f) => sum + f.weighted, 0)
  const backendScore = Number.isFinite(Number(gica.score)) ? Number(gica.score) : null
  const rawBeforeCap = Number.isFinite(Number(gica.raw_score_before_cap)) ? Number(gica.raw_score_before_cap) : null
  const blocking = Boolean(gica.blocking)
  const capped = blocking && backendScore != null && Math.round(computedTotal) > backendScore
  const consistent = !factors.length || backendScore == null || Math.abs(Math.round(computedTotal) - backendScore) <= 1 || capped
  const displayScore = backendScore ?? Math.round(computedTotal)
  const classification = classifyCompatibility(displayScore, gica.classification_scale)

  return {
    factors,
    legacy,
    legacyRows: legacy ? rawBreakdown : [],
    totalMax,
    weightsSumPercent: factors.reduce((sum, f) => sum + f.weightPercent, 0),
    computedTotal,
    backendScore,
    rawBeforeCap,
    blocking,
    capped,
    consistent,
    score: displayScore,
    label: gica.label || classification.label,
    classification,
    summary: gica.summary || null,
    recommendation: gica.recommendation || null,
    methodology: gica.methodology || null,
    why: gica.why || { positives: [], warnings: [] },
    risk: gica.risk || null,
    diversity: gica.diversity || null,
    mutation: gica.mutation_compatibility || null,
    species: describeSpeciesPairing(species),
  }
}

export function describeSpeciesPairing(species = {}) {
  const p1 = species.parent_1 || {}
  const p2 = species.parent_2 || {}
  const sameSpecies = species.same_species === true
    || String(species.status || '').toUpperCase() === 'SAME_SPECIES'
    || (Boolean(p1.species) && p1.species === p2.species && species.same_species !== false)
  const interspecific = !sameSpecies && Boolean(p1.species || p2.species)
  return {
    sameSpecies,
    interspecific,
    hybrid: Boolean(species.hybrid),
    compatible: species.compatible !== false,
    predictionAllowed: species.prediction_allowed !== false && species.can_predict !== false,
    status: species.status || null,
    label: species.label || (sameSpecies ? 'Same-Species Pair' : interspecific ? 'Interspecific / Hybrid consideration' : 'Not documented'),
    breedingType: species.breeding_type || null,
    scorePercent: Number.isFinite(Number(species.score)) ? Math.round(Number(species.score) * 100) : null,
    basis: species.scientific_basis || null,
    methodology: species.methodology || null,
    warning: species.warning_message || species.warning || null,
    errors: species.errors || [],
    warnings: species.warnings || [],
    parent1: p1,
    parent2: p2,
    confidence: sameSpecies ? 'Normal' : 'Reduced',
  }
}

function format(value) {
  return Number.isFinite(value) ? value.toFixed(2) : '—'
}

function slugify(value) {
  return String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '')
}
