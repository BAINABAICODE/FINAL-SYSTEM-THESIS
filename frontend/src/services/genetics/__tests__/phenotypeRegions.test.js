import { describe, expect, it } from 'vitest'
import { buildPhenotypeRegions, parsePhenotypeText } from '../phenotypeRegions'

describe('parsePhenotypeText', () => {
  it('reads lutino as yellow body and red eyes', () => {
    const parsed = parsePhenotypeText('On green this is lutino: yellow body, red eyes.')
    expect(parsed.eyes).toBe('Red')
    expect(parsed.body).toBe('Yellow')
  })

  it('reads wild-type peach-faced regions', () => {
    const parsed = parsePhenotypeText('Genetic default. Grass-green body, peach mask, sky-blue rump.')
    expect(parsed.body).toBe('Grass-green')
    expect(parsed.head).toBe('Peach')
    expect(parsed.rump).toBe('Sky-blue')
  })

  it('reads already labeled parts', () => {
    const parsed = parsePhenotypeText('Eye: red. Head: yellow.')
    expect(parsed.eyes).toBe('Red')
    expect(parsed.head).toBe('Yellow')
  })
})

describe('buildPhenotypeRegions', () => {
  it('maps stored color and mutation names onto body parts', () => {
    const regions = buildPhenotypeRegions({
      base_color: 'Ground color (Turquoise / Other Blue-Series Forms)',
      visual_mutations: ['Double Violet', 'Misty DF', 'SL Greywing'],
      phenotype: 'Same misty mutation, homozygous. Same violet mutation, homozygous. Partial eumelanin reduction.',
    })
    expect(regions.find((row) => row.key === 'body').value).toMatch(/Turquoise/)
    expect(regions.find((row) => row.key === 'body').value).toMatch(/Violet/)
    expect(regions.find((row) => row.key === 'wings').value).toBe('Greywing')
    expect(regions.find((row) => row.key === 'eyes').specified).toBe(false)
  })

  it('does not treat a split ino mention as red eyes', () => {
    const regions = buildPhenotypeRegions({
      base_color: 'Turquoise',
      visual_mutations: ['Double Violet'],
      split_hidden_genes: ['Split NSL Ino'],
      phenotype: 'Carrier / split state for Split NSL Ino (not necessarily visual).',
    })
    expect(regions.find((row) => row.key === 'eyes').specified).toBe(false)
    expect(regions.find((row) => row.key === 'body').value).toMatch(/Turquoise/)
  })

  it('keeps head-to-tail order and uses stored fields first', () => {
    const regions = buildPhenotypeRegions({
      eyes: 'red',
      head: 'yellow',
      phenotype: 'Grass-green body, sky-blue rump.',
    })
    expect(regions.map((row) => row.label)).toEqual(['Eyes', 'Head', 'Neck', 'Body', 'Wings', 'Rump', 'Tail'])
    expect(regions.find((row) => row.key === 'eyes')).toMatchObject({ value: 'Red', specified: true })
    expect(regions.find((row) => row.key === 'head')).toMatchObject({ value: 'Yellow', specified: true })
    expect(regions.find((row) => row.key === 'body')).toMatchObject({ value: 'Grass-green', specified: true })
    expect(regions.find((row) => row.key === 'wings').specified).toBe(false)
  })

  it('prefers a stored head-to-tail catalog map including neck', () => {
    const regions = buildPhenotypeRegions({
      phenotype: 'Grass-green body.',
      head_to_tail: {
        eyes: 'Dark brown/black with a white eye ring',
        head: 'Black/brown mask',
        neck: 'Bright yellow collar',
        body: 'Green with a yellow breast',
        wings: 'Green',
        rump: 'Blue',
        tail: 'Green',
      },
    })
    expect(regions.find((row) => row.key === 'neck')).toMatchObject({
      value: 'Bright yellow collar',
      specified: true,
    })
    expect(regions.find((row) => row.key === 'head').value).toMatch(/Black/)
  })
})
