import { describe, expect, it } from 'vitest'
import { buildInheritanceRows } from '../PairInheritanceBoard'

describe('buildInheritanceRows', () => {
  it('maps cock and hen alleles onto chick odds', () => {
    const rows = buildInheritanceRows([
      {
        key: 'violet',
        name: 'Violet',
        mode: { label: 'Incomplete dominant' },
        alleles: { cock: ['V', 'V+'], hen: ['V+', 'V+'] },
        parents: { cock: { code: 'V/V+' }, hen: { code: 'V+/V+' } },
        outcomes: [
          { genotype: 'V/V+', probability: 0.5, phenotype: { visualMutations: ['Violet'], visual: true } },
          { genotype: 'V+/V+', probability: 0.5, phenotype: { expressionLabel: 'No visual change', visual: false } },
        ],
        status: 'calculated',
        square: { valid: true },
      },
    ])

    expect(rows[0].gene).toBe('Violet')
    expect(rows[0].cock.code).toBe('V/V+')
    expect(rows[0].hen.code).toBe('V+/V+')
    expect(rows[0].chicks[0]).toMatchObject({ look: 'Violet', percent: 50 })
  })
})
