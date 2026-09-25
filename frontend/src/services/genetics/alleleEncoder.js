/**
 * F1 — Allele encoding.
 * Parses AGAPORA genotype strings (e.g. "bl+/bl+", "op+/W", "D+/D") into allele arrays
 * and describes each allele. Wild-type alleles carry a trailing "+" by dataset convention;
 * "W" is the hen's W chromosome. Nothing here invents alleles: unparsable input yields
 * an explicit `valid: false` result.
 */

export const W_CHROMOSOME = 'W'

export function isWildType(allele) {
  return typeof allele === 'string' && allele.trim().endsWith('+')
}

export function isWChromosome(allele) {
  return typeof allele === 'string' && allele.trim().toUpperCase() === W_CHROMOSOME
}

export function alleleClass(allele) {
  if (isWChromosome(allele)) return 'w'
  if (isWildType(allele)) return 'wild'
  return 'mutant'
}

/**
 * @param {string|null|undefined} genotype
 * @returns {{ raw: string, alleles: string[], valid: boolean, hemizygous: boolean, zAlleles: string[], reason?: string }}
 */
export function parseGenotype(genotype) {
  const raw = genotype == null ? '' : String(genotype).trim()
  if (!raw) {
    return { raw, alleles: [], zAlleles: [], valid: false, hemizygous: false, reason: 'Not provided' }
  }
  const alleles = raw.split('/').map((part) => part.trim()).filter(Boolean)
  if (alleles.length !== 2) {
    return { raw, alleles, zAlleles: alleles.filter((a) => !isWChromosome(a)), valid: false, hemizygous: false, reason: 'Genotype must contain exactly two alleles' }
  }
  const hemizygous = alleles.some(isWChromosome)
  return {
    raw,
    alleles,
    zAlleles: alleles.filter((a) => !isWChromosome(a)),
    valid: true,
    hemizygous,
  }
}

/** Splits a multi-locus base-colour code such as "bl+/bl+|D+/D" into individual segments. */
export function splitGeneticCode(code) {
  if (code == null || code === '') return []
  return String(code).split('|').map((part) => part.trim()).filter(Boolean)
}

/**
 * Orders a diploid pair so the display matches the backend convention
 * (wild-type allele first, W chromosome last).
 */
export function normaliseGenotype(a, b) {
  if (isWChromosome(a)) return `${b}/${a}`
  if (isWChromosome(b)) return `${a}/${b}`
  if (a === b) return `${a}/${b}`
  if (isWildType(b) && !isWildType(a)) return `${b}/${a}`
  return `${a}/${b}`
}

/** Order-independent key for a diploid pair (W chromosome always last). */
export function canonicalGenotypeKey(alleles) {
  const list = (alleles || []).filter((a) => a != null && a !== '')
  const w = list.filter(isWChromosome)
  const rest = list.filter((a) => !isWChromosome(a)).sort()
  return [...rest, ...w].join('/')
}

export function describeAllele(allele, locusName) {
  const cls = alleleClass(allele)
  if (cls === 'w') return { allele, kind: 'w', text: 'W chromosome (no Z-linked allele carried)' }
  if (cls === 'wild') return { allele, kind: 'wild', text: `${allele} = wild-type allele${locusName ? ` at ${locusName}` : ''}` }
  return { allele, kind: 'mutant', text: `${allele} = mutant allele${locusName ? ` for ${locusName}` : ''}` }
}

/**
 * Encodes one parent for a locus.
 * @param {{ code?: string, record?: string, confidence?: string, assumed_non_carrier?: boolean }} contribution
 */
export function encodeParentLocus(contribution, locusName) {
  const parsed = parseGenotype(contribution?.code)
  return {
    record: contribution?.record ?? 'Not provided',
    code: parsed.raw || 'Not provided',
    confidence: contribution?.confidence ?? null,
    assumedNonCarrier: Boolean(contribution?.assumed_non_carrier),
    alleles: parsed.alleles,
    zAlleles: parsed.zAlleles,
    hemizygous: parsed.hemizygous,
    valid: parsed.valid,
    reason: parsed.reason,
    interpretation: parsed.alleles.map((allele) => describeAllele(allele, locusName)),
  }
}
