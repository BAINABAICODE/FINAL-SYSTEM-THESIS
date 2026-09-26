import { describe, expect, it } from 'vitest'
import { groundBlockReason, keepApplicableMutationIds, visualBlockReason } from '../mutationGroundApplicability.js'

const orangeFace = {
  id: 1,
  name: 'Orange Face',
  series: 'Psittacin',
  phenotype: 'Facial psittacin becomes orange.',
}

const paleHeaded = {
  id: 2,
  name: 'Pale Headed',
  series: 'Incomplete dominant',
  phenotype: 'Alters facial psittacin. One Ph reduces the red mask.',
}

const paleHeadedDf = {
  id: 3,
  name: 'Pale Headed DF',
  series: 'Incomplete dominant',
  phenotype: 'Same pale-headed mutation, homozygous. Stronger facial effect than one Ph.',
}

const slIno = {
  id: 4,
  name: 'SL Ino',
  series: 'Sex-linked ino locus',
  phenotype: 'On green this is lutino. Albino is this same allele on a blue ground, not a separate mutation.',
}

const violet = { id: 5, name: 'Violet', series: 'Incomplete dominant', phenotype: 'Adds a violet wash.' }
const doubleViolet = { id: 6, name: 'Double Violet', series: 'Incomplete dominant', phenotype: 'Two violet factors.' }
const pallid = { id: 7, name: 'Pallid', series: 'Sex-linked ino locus', phenotype: 'Partial reduction of melanin.' }
const nslIno = { id: 8, name: 'NSL Ino', series: 'NSL ino locus', phenotype: 'Removes melanin.' }

const blue = { id: 10, name: 'Blue', series: 'Blue' }
const cobalt = { id: 11, name: 'Cobalt', series: 'Blue' }
const green = { id: 12, name: 'Green', series: 'Green' }
const aqua = { id: 13, name: 'Aqua', series: 'Aqua' }
const turquoise = { id: 14, name: 'Turquoise', series: 'Turquoise' }

describe('mutation ground applicability', () => {
  it('blocks facial-psittacin mutations on a blue ground', () => {
    expect(groundBlockReason(blue, orangeFace)).toMatch(/Orange Face is not a visible mutation on Blue/)
    expect(groundBlockReason(blue, paleHeaded)).toMatch(/Pale Headed/)
    expect(groundBlockReason(cobalt, paleHeadedDf)).toMatch(/Cobalt/)
  })

  it('keeps mutations that are visual on blue, and facial mutations on grounds that still have pigment', () => {
    expect(groundBlockReason(blue, slIno)).toBe('')
    expect(groundBlockReason(green, orangeFace)).toBe('')
    expect(groundBlockReason(aqua, orangeFace)).toBe('')
    expect(groundBlockReason(turquoise, paleHeaded)).toBe('')
    expect(groundBlockReason(null, orangeFace)).toBe('')
  })

  it('drops a blocked mutation when the base color changes to blue', () => {
    expect(keepApplicableMutationIds([1, 4], [orangeFace, slIno], blue)).toEqual([4])
    expect(keepApplicableMutationIds([1, 4], [orangeFace, slIno], green)).toEqual([1, 4])
  })

  it('covers violet when ino is selected and leaves pallid with violet', () => {
    expect(visualBlockReason(green, violet, [slIno, violet])).toMatch(/Violet is not a visible mutation with SL Ino/)
    expect(visualBlockReason(green, doubleViolet, [nslIno, doubleViolet])).toMatch(/NSL Ino/)
    expect(visualBlockReason(green, slIno, [slIno, violet])).toBe('')
    expect(visualBlockReason(green, violet, [pallid, violet])).toBe('')
    expect(keepApplicableMutationIds([4, 5], [slIno, violet], green)).toEqual([4])
  })
})
