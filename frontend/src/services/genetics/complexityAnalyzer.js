import { buildCompatibilityModel } from './compatibilityCalculator'
import { buildRbgiaTrace } from './rbgiaTrace'

const CLUTCH_STAGE_COUNT = 3

/**
 * Derives time/space complexity from the calculations that actually ran for this pair.
 * Does not invent loci, factors, or eggs — counts come from the stored RBGIA / GICA / clutch payload.
 *
 * @param {object} result computation-results API payload
 * @param {object} [trace] optional prebuilt RBGIA trace
 */
export function buildComplexityReport(result = {}, trace = null) {
  const presentation = result.result_presentation || {}
  const rbgia = trace || buildRbgiaTrace(result)
  const gicaModel = buildCompatibilityModel(presentation.gica || {}, presentation.species_compatibility || {})
  const clutch = presentation.clutch_simulation || result.clutch_simulation || null

  const locusRows = rbgia.loci.map((locus) => locusComplexity(locus))
  const joint = jointComplexity(locusRows, rbgia.joint)
  const gica = gicaComplexity(gicaModel)
  const clutchRow = clutchComplexity(clutch)

  const calculations = [...locusRows, joint, gica, clutchRow]
  const timeOps = calculations.reduce((sum, row) => sum + row.timeOps, 0)
  const spaceUnits = calculations.reduce((sum, row) => sum + row.spaceUnits, 0)

  const L = locusRows.filter((row) => row.calculated).length
  const C = locusRows.reduce((sum, row) => sum + row.timeOps, 0)
  const P = joint.productBound
  const R = joint.spaceUnits
  const F = gica.factorCount
  const E = clutchRow.eggCount
  const S = clutchRow.stageCount

  return {
    variables: { L, C, P, R, F, E, S },
    time: {
      bound: 'O(C + P + F + E·S)',
      substituted: substituteTime({ C, P, F, E, S }),
      measuredOps: timeOps,
      explanation:
        'C = Punnett cells across calculated loci (cock gametes × hen gametes). P = joint genotype product Π kᵢ. F = GICA factors. E·S = clutch eggs × viability stages.',
    },
    space: {
      bound: 'O(C + R + F + E)',
      substituted: substituteSpace({ C, R, F, E }),
      measuredUnits: spaceUnits,
      explanation:
        'C = Punnett cells kept per locus. R = retained joint offspring rows after sex-compatibility merge. F = GICA factor records. E = simulated eggs stored for this clutch.',
    },
    calculations,
    method: presentation.algorithm?.method || result.genetic_calculation?.method || 'AGAPORA-RBGIA-GICA-v3',
  }
}

function locusComplexity(locus) {
  const cockGametes = Array.isArray(locus.square?.rows) ? locus.square.rows.length : (locus.alleles?.cock?.length || 0)
  const henGametes = Array.isArray(locus.square?.cols) ? locus.square.cols.length : (locus.alleles?.hen?.length || 0)
  const cells = Number(locus.square?.total) || (cockGametes * henGametes)
  const unique = Array.isArray(locus.outcomes) ? locus.outcomes.length : 0
  const calculated = Boolean(locus.square?.valid) && (locus.status || 'calculated') === 'calculated'
  const timeOps = calculated ? cells : 0
  const spaceUnits = calculated ? cells + unique : 0

  return {
    id: `locus:${locus.key}`,
    group: 'RBGIA locus',
    name: locus.name,
    detail: calculated
      ? `${cockGametes} cock gametes × ${henGametes} hen gametes → ${cells} Punnett cells → ${unique} unique genotype${unique === 1 ? '' : 's'}`
      : (locus.reason || 'Locus was not calculated; no alleles were invented.'),
    calculated,
    time: calculated ? `O(${cockGametes}×${henGametes})` : 'O(1) skip',
    space: calculated ? `O(${cells}+${unique})` : 'O(1)',
    timeOps,
    spaceUnits,
    cockGametes,
    henGametes,
    cells,
    unique,
  }
}

function jointComplexity(locusRows, joint) {
  const calculated = locusRows.filter((row) => row.calculated)
  const ks = calculated.map((row) => Math.max(row.unique, 1))
  const productBound = ks.length ? ks.reduce((acc, k) => acc * k, 1) : 0
  let prefix = 1
  let expansions = 0
  const steps = []
  calculated.forEach((row) => {
    const k = Math.max(row.unique, 1)
    const work = prefix * k
    expansions += work
    steps.push(`${row.name}: ${prefix} × ${k} = ${work}`)
    prefix = work
  })
  const retained = Array.isArray(joint?.rows) ? joint.rows.length : 0

  return {
    id: 'joint',
    group: 'RBGIA joint distribution',
    name: 'Cartesian product of calculated loci',
    detail: calculated.length
      ? `Π kᵢ = ${ks.join(' × ') || '1'} = ${productBound} combinations before merge. Retained after sex-compatibility filter: ${retained}. ${steps.join(' → ')}`
      : 'No calculated loci, so no joint product was formed.',
    calculated: calculated.length > 0,
    time: productBound ? `O(${ks.join('×')})` : 'O(1)',
    space: retained ? `O(${retained})` : 'O(1)',
    timeOps: expansions,
    spaceUnits: retained,
    productBound,
    factorCount: 0,
    eggCount: 0,
    stageCount: 0,
  }
}

function gicaComplexity(model) {
  const factors = Array.isArray(model.factors) ? model.factors : []
  const F = factors.length
  const names = factors.map((factor) => factor.name).filter(Boolean)

  return {
    id: 'gica',
    group: 'GICA score',
    name: `${F} weighted compatibility factor${F === 1 ? '' : 's'}`,
    detail: F
      ? `Score = Σ (Wᵢ × Rawᵢ) over ${F} factor${F === 1 ? '' : 's'}: ${names.join(', ') || 'unnamed'}. Each factor is O(1).`
      : 'No GICA breakdown was stored for this result.',
    calculated: F > 0,
    time: F ? `O(${F})` : 'O(1)',
    space: F ? `O(${F})` : 'O(1)',
    timeOps: F,
    spaceUnits: F,
    factorCount: F,
  }
}

function clutchComplexity(clutch) {
  const eggs = Array.isArray(clutch?.eggs) ? clutch.eggs : []
  const E = eggs.length || Number(clutch?.clutch_size) || 0
  const stageCounts = eggs.map((egg) => (Array.isArray(egg.stages) ? egg.stages.length : CLUTCH_STAGE_COUNT))
  const S = stageCounts.length ? Math.max(...stageCounts, CLUTCH_STAGE_COUNT) : (E ? CLUTCH_STAGE_COUNT : 0)
  const stageOps = eggs.reduce((sum, egg) => sum + (Array.isArray(egg.stages) ? egg.stages.length : S), 0)
  const living = eggs.filter((egg) => egg.status === 'living_chick' || egg.egg_status === 'living_chick').length

  return {
    id: 'clutch',
    group: 'Clutch simulation',
    name: E ? `${E} simulated egg${E === 1 ? '' : 's'}` : 'No clutch simulation stored',
    detail: E
      ? `Each egg runs Fertilization → Development → Hatching (${S} stages). ${living} living chick${living === 1 ? '' : 's'} then sample the unchanged RBGIA distribution (O(1) per chick).`
      : 'This result has no Layer-2 clutch simulation.',
    calculated: E > 0,
    time: E ? `O(${E}×${S})` : 'O(1)',
    space: E ? `O(${E})` : 'O(1)',
    timeOps: stageOps || (E * S),
    spaceUnits: E,
    eggCount: E,
    stageCount: S,
    factorCount: 0,
    productBound: 0,
  }
}

function substituteTime({ C, P, F, E, S }) {
  if (!C && !P && !F && !E) return 'O(1) — no calculations stored'
  const clutch = E && S ? `${E}·${S}` : String(E || 0)
  return `O(${C} + ${P} + ${F} + ${clutch})`
}

function substituteSpace({ C, R, F, E }) {
  if (!C && !R && !F && !E) return 'O(1) — no calculations stored'
  return `O(${C} + ${R} + ${F} + ${E})`
}
