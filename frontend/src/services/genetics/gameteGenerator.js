import { isWChromosome } from './alleleEncoder'

/**
 * F2 — Gamete formation.
 * Each allele of a diploid parent is passed to a gamete with equal probability
 * (Mendel's law of segregation). For the hen at a Z-linked locus the two gametes are
 * the Z allele and the W chromosome. Probabilities are exact rationals (count / total).
 */

/**
 * @param {string[]} alleles two alleles of one parent
 * @returns {{ gametes: Array<{ allele: string, count: number, total: number, probability: number, isW: boolean }>, total: number }}
 */
export function buildGametes(alleles) {
  const list = (alleles || []).filter((a) => a != null && a !== '')
  const total = list.length
  if (!total) return { gametes: [], total: 0 }
  const counts = new Map()
  list.forEach((allele) => counts.set(allele, (counts.get(allele) || 0) + 1))
  const gametes = [...counts.entries()].map(([allele, count]) => ({
    allele,
    count,
    total,
    probability: count / total,
    isW: isWChromosome(allele),
  }))
  return { gametes, total }
}

/** Expanded gamete list (one entry per allele copy) — the raw Punnett axis. */
export function gameteAxis(alleles) {
  return (alleles || []).filter((a) => a != null && a !== '')
}
