import { INHERITANCE_MODES, INHERITANCE_MODE_LABELS } from './constants'

/**
 * Maps the dataset's free-text `inheritance_type` (e.g. "Sex-linked recessive",
 * "Intermediate dominant", "Autosomal recessive, multiple alleles") to a discrete mode.
 * Mirrors the backend rule: a locus is sex-linked iff the string contains "sex-linked".
 */
export function resolveInheritanceMode(inheritanceType, category) {
  const text = String(inheritanceType || '').toLowerCase().trim()
  if (category === 'chromosomal_sex') return build(INHERITANCE_MODES.CHROMOSOMAL_SEX, inheritanceType)
  if (!text || text.includes('needs verification') || text.includes('unknown')) {
    return build(INHERITANCE_MODES.UNKNOWN, inheritanceType)
  }
  const sexLinked = text.includes('sex-linked') || text.includes('sex linked') || text.includes('z-linked')
  const incomplete = text.includes('incomplete') || text.includes('intermediate') || text.includes('partial') || text.includes('co-dominant') || text.includes('codominant')
  const recessive = text.includes('recessive')
  const dominant = text.includes('dominant')

  if (sexLinked && recessive) return build(INHERITANCE_MODES.SEX_LINKED_RECESSIVE, inheritanceType)
  if (sexLinked && incomplete) return build(INHERITANCE_MODES.SEX_LINKED_INCOMPLETE_DOMINANT, inheritanceType)
  if (sexLinked && dominant) return build(INHERITANCE_MODES.SEX_LINKED_DOMINANT, inheritanceType)
  if (sexLinked) return build(INHERITANCE_MODES.SEX_LINKED_RECESSIVE, inheritanceType)
  if (text.includes('wild type') || text.includes('wild-type')) return build(INHERITANCE_MODES.WILD_TYPE, inheritanceType)
  if (incomplete) return build(INHERITANCE_MODES.INCOMPLETE_DOMINANT, inheritanceType)
  if (recessive) return build(INHERITANCE_MODES.AUTOSOMAL_RECESSIVE, inheritanceType)
  if (dominant) return build(INHERITANCE_MODES.AUTOSOMAL_DOMINANT, inheritanceType)
  return build(INHERITANCE_MODES.UNKNOWN, inheritanceType)
}

function build(key, source) {
  return {
    key,
    label: INHERITANCE_MODE_LABELS[key],
    source: source || null,
    sexLinked: key.startsWith('sex_linked'),
    recessive: key.endsWith('recessive'),
    incomplete: key.includes('incomplete'),
    dominant: key.endsWith('dominant') && !key.includes('incomplete'),
    unknown: key === INHERITANCE_MODES.UNKNOWN,
  }
}

export function isSexLinkedMode(mode) {
  return Boolean(mode?.sexLinked)
}
