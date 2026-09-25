import blackCheeked from './black-cheeked.png'
import blackCollared from './black-collared.png'
import blackWingedCock from './black-winged-cock.png'
import blackWingedHen from './black-winged-hen.png'
import fischers from './fischers.png'
import greyHeadedCock from './grey-headed-cock.png'
import greyHeadedHen from './grey-headed-hen.png'
import lilians from './lilians.png'
import redHeaded from './red-headed.png'
import rosyFaced from './rosy-faced.png'
import yellowCollared from './yellow-collared.png'

/** Form preview assets. Dimorphic species keep a default plus Hen/Cock variants. */
export const speciesFormImages = {
  1: { default: rosyFaced },
  2: { default: fischers },
  3: { default: yellowCollared },
  4: { default: blackCheeked },
  5: { default: lilians },
  6: { default: blackWingedCock, hen: blackWingedHen, cock: blackWingedCock },
  7: { default: redHeaded },
  8: { default: greyHeadedCock, hen: greyHeadedHen, cock: greyHeadedCock },
  9: { default: blackCollared },
}

export function getSpeciesFormPreview(option, sex) {
  if (!option) return null

  const entry = speciesFormImages[option.id]
  if (!entry) return null

  const normalized = sex === 'hen' || sex === 'cock' ? sex : null
  const src = (normalized && entry[normalized]) || entry.default || entry.cock || entry.hen
  if (!src) return null

  const name = option.common_name || 'Lovebird'
  const sexNote = normalized === 'hen' ? 'hen' : normalized === 'cock' ? 'cock' : 'species'
  return { src, alt: `${name} ${sexNote} preview` }
}
