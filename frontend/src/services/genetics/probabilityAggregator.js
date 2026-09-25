/**
 * F5 — Probability aggregation.
 * Equivalent Punnett cells (same genotype and same sex class) are merged:
 *   P(outcome) = (number of equivalent cells) ÷ (total valid cells)
 * which is identical to Σ P(gamete₁) × P(gamete₂) over the contributing paths.
 */

export function gcd(a, b) {
  let x = Math.abs(Math.round(a))
  let y = Math.abs(Math.round(b))
  while (y) [x, y] = [y, x % y]
  return x || 1
}

export function simplifyFraction(count, total) {
  if (!total) return '0/1'
  const divisor = gcd(count, total)
  return `${count / divisor}/${total / divisor}`
}

/**
 * @param {Array<{ genotype: string, sex: string, probability: number, fromCock: string, fromHen: string, path: object }>} cells
 */
export function aggregateCells(cells) {
  const total = cells.length
  const groups = new Map()
  cells.forEach((cell) => {
    const key = `${cell.sex}::${cell.genotypeKey || cell.genotype}`
    if (!groups.has(key)) {
      groups.set(key, { genotype: cell.genotype, sex: cell.sex, count: 0, paths: [] })
    }
    const group = groups.get(key)
    group.count += 1
    group.paths.push({ fromCock: cell.fromCock, fromHen: cell.fromHen, probability: cell.probability, expression: cell.path?.expression })
  })

  const outcomes = [...groups.values()].map((group) => ({
    ...group,
    total,
    probability: total ? group.count / total : 0,
    fraction: simplifyFraction(group.count, total),
    formula: `${group.count} / ${total} = ${percent(total ? group.count / total : 0)}`,
  }))

  return sortOutcomes(outcomes)
}

export function sortOutcomes(outcomes) {
  const sexOrder = { both: 0, cock: 1, hen: 2 }
  return [...outcomes].sort((a, b) => {
    if (b.probability !== a.probability) return b.probability - a.probability
    if ((sexOrder[a.sex] ?? 9) !== (sexOrder[b.sex] ?? 9)) return (sexOrder[a.sex] ?? 9) - (sexOrder[b.sex] ?? 9)
    return String(a.genotype).localeCompare(String(b.genotype))
  })
}

export function percent(value, digits = 1) {
  if (!Number.isFinite(value)) return '—'
  const scaled = value * 100
  const rounded = Math.round(scaled * 10 ** digits) / 10 ** digits
  return `${Number.isInteger(rounded) ? rounded : rounded.toFixed(digits)}%`
}

export function sumProbabilities(rows, key = 'probability') {
  return (rows || []).reduce((sum, row) => sum + (Number(row?.[key]) || 0), 0)
}

/** Expected count range for `clutchSize` eggs at probability `p` (mean ± one binomial SD). */
export function expectedRange(clutchSize, p) {
  const n = Math.max(0, Math.round(clutchSize))
  const prob = Math.min(1, Math.max(0, Number(p) || 0))
  const mean = n * prob
  const sd = Math.sqrt(n * prob * (1 - prob))
  const low = Math.max(0, Math.floor(mean - sd + 1e-9))
  const high = Math.min(n, Math.ceil(mean + sd - 1e-9))
  return { mean, sd, low, high, exact: Number.isInteger(mean) && sd === 0 }
}
