import { canonicalGenotypeKey, isWChromosome, normaliseGenotype } from './alleleEncoder'
import { gameteAxis } from './gameteGenerator'

/**
 * F3 — Punnett combination.
 * Builds the full square from the actual allele arrays. Every cell has probability
 * P(cock gamete) × P(hen gamete) = 1/(rows × cols). No randomness is involved.
 *
 * Autosomal: cock {a1,a2} × hen {b1,b2} → 4 cells, sex "both".
 * Z-linked (avian ZW): cock {Z1,Z2} × hen {Z, W}
 *   Z(cock) + Z(hen) = son  (ZZ)   → genotype Z1/Zhen
 *   Z(cock) + W(hen) = daughter (ZW) → genotype Z1/W  (hemizygous: no second Z allele)
 */

/**
 * @param {string[]} cockAlleles
 * @param {string[]} henAlleles
 * @param {{ sexLinked?: boolean }} [options]
 */
export function buildPunnettSquare(cockAlleles, henAlleles, options = {}) {
  const rows = gameteAxis(cockAlleles)
  const cols = gameteAxis(henAlleles)
  const sexLinked = Boolean(options.sexLinked) || cols.some(isWChromosome) || rows.some(isWChromosome)

  if (!rows.length || !cols.length) {
    return { rows, cols, cells: [], total: 0, sexLinked, valid: false, reason: 'A parental genotype is missing; the square cannot be built without inventing alleles.' }
  }

  const rowProbability = 1 / rows.length
  const colProbability = 1 / cols.length
  const cells = []

  rows.forEach((cockGamete, rowIndex) => {
    cols.forEach((henGamete, colIndex) => {
      const daughter = sexLinked && isWChromosome(henGamete)
      const sex = sexLinked ? (daughter ? 'hen' : 'cock') : 'both'
      cells.push({
        rowIndex,
        colIndex,
        fromCock: cockGamete,
        fromHen: henGamete,
        genotype: normaliseGenotype(cockGamete, henGamete),
        genotypeKey: canonicalGenotypeKey([cockGamete, henGamete]),
        sex,
        probability: rowProbability * colProbability,
        path: {
          cockProbability: rowProbability,
          henProbability: colProbability,
          expression: `${formatProbability(rowProbability)} × ${formatProbability(colProbability)} = ${formatProbability(rowProbability * colProbability)}`,
        },
      })
    })
  })

  return { rows, cols, cells, total: cells.length, sexLinked, valid: true }
}

export function formatProbability(value, digits = 2) {
  if (!Number.isFinite(value)) return '—'
  return value.toFixed(digits)
}
