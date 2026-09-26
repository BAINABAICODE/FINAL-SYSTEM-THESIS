import { describe, expect, it } from 'vitest'
import { attachHeadToTail, composeHeadToTail } from '../headToTailCatalog'

const catalog = {
  species_identities: [
    {
      species_id: 3,
      eyes: 'Dark brown/black with a white eye ring',
      head: 'Black/brown mask',
      neck: 'Bright yellow collar',
      body: 'Green with a yellow breast',
      wings: 'Green',
      rump: 'Blue',
      tail: 'Green',
    },
  ],
  visual_mutations: [
    {
      species_id: 3,
      mutation_name: 'NSL Ino',
      eyes: 'Red with a white eye ring',
      head: 'Yellow (black mask lost); white eye ring remains',
      neck: 'Yellow collar remains (psittacin)',
      body: 'Yellow on green (lutino); white on blue (albino)',
      wings: 'Yellow / clear',
      rump: 'Pale yellow or white',
      tail: 'Yellow / pale',
    },
  ],
  overlays: {
    cinnamon: {
      body: 'Yellow-green (brown eumelanin)',
      wings: 'Brown-green',
    },
  },
}

describe('composeHeadToTail', () => {
  it('keeps the masked yellow collar on wild type', () => {
    const map = composeHeadToTail(catalog, 3, [])
    expect(map.neck).toBe('Bright yellow collar')
    expect(map.head).toMatch(/Black/)
  })

  it('uses the seeded ino map for a single mutation', () => {
    const map = composeHeadToTail(catalog, 3, ['NSL Ino'])
    expect(map.eyes).toMatch(/Red/)
    expect(map.neck).toMatch(/Yellow collar/)
  })
})

describe('attachHeadToTail', () => {
  it('does not overwrite a complete stored map', () => {
    const source = {
      eyes: 'Red',
      head: 'Yellow',
      neck: 'Yellow collar',
      body: 'Yellow',
      wings: 'Yellow',
      rump: 'Pale',
      tail: 'Yellow',
    }
    expect(attachHeadToTail(source, catalog, 3)).toBe(source)
  })
})
