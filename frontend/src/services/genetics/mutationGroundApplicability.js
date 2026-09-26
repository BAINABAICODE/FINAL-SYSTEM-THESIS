export function groundBlockReason(baseColor, mutation) {
  if (!baseColor || !mutation || !isBlueGround(baseColor) || !changesFacialPsittacin(mutation)) {
    return ''
  }

  const colorName = baseColor.name || 'Blue'
  const mutationName = mutation.name || 'This mutation'

  return `${mutationName} is not a visible mutation on ${colorName}. A blue bird has no facial pigment for this mutation to change.`
}

export function visualBlockReason(baseColor, mutation, selected = []) {
  const ground = groundBlockReason(baseColor, mutation)
  if (ground) return ground

  return epistasisReason(mutation, selected)
}

export function keepApplicableMutationIds(ids, mutations, baseColor) {
  const records = (ids || [])
    .map((id) => (mutations || []).find((item) => String(item.id) === String(id)) || null)
    .filter(Boolean)
  const hasIno = records.some(isInoAllele)

  return (ids || []).filter((id) => {
    const mutation = (mutations || []).find((item) => String(item.id) === String(id))
    if (!mutation) return true
    if (groundBlockReason(baseColor, mutation)) return false
    if (hasIno && isVioletDose(mutation)) return false
    return true
  })
}

function epistasisReason(mutation, selected) {
  if (!isVioletDose(mutation)) return ''

  const ino = (selected || []).find((item) => item && String(item.id) !== String(mutation.id) && isInoAllele(item))
  if (!ino) return ''

  const violetName = mutation.name || 'Violet'
  const inoName = ino.name || 'Ino'

  return `${violetName} is not a visible mutation with ${inoName}. Ino covers the violet wash, so the bird shows ${inoName}.`
}

function isInoAllele(mutation) {
  const name = String(mutation?.name || '').trim().toLowerCase()
  return name === 'sl ino' || name === 'nsl ino'
}

function isVioletDose(mutation) {
  const name = String(mutation?.name || '').trim().toLowerCase()
  return name === 'violet' || name === 'double violet'
}

function isBlueGround(baseColor) {
  return String(baseColor.series || '').trim().toLowerCase() === 'blue'
}

function changesFacialPsittacin(mutation) {
  if (String(mutation.series || '').trim().toLowerCase() === 'psittacin') return true

  const phenotype = String(mutation.phenotype || '').toLowerCase()
  return /facial psittacin|red mask|facial effect/.test(phenotype)
}
