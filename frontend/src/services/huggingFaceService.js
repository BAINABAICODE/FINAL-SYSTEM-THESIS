/**
 * Chick image API client.
 * Calls Laravel only — never talks to OpenRouter/Hugging Face and never carries API keys.
 */
import api from '../api/client'

function mutationNames(value) {
  if (!value) return []
  if (Array.isArray(value)) {
    return value
      .map((item) => (typeof item === 'string' ? item : item?.name || item?.label || ''))
      .map((name) => String(name).trim())
      .filter(Boolean)
  }
  if (typeof value === 'string') {
    return value
      .split(',')
      .map((part) => part.trim())
      .filter(Boolean)
  }
  return []
}

function speciesText(species) {
  if (!species) return 'lovebird'
  if (typeof species === 'string') return species
  return species.common_name || species.name || species.scientific_name || 'lovebird'
}

function scientificName(species, fallback) {
  if (fallback) return fallback
  if (species && typeof species === 'object') return species.scientific_name || null
  return null
}

function sexText(egg) {
  if (egg?.collected_traits?.sex) return egg.collected_traits.sex
  if (egg?.sex_label) return egg.sex_label
  if (egg?.sex === 'cock' || egg?.sex === 'male') return 'Male'
  if (egg?.sex === 'hen' || egg?.sex === 'female') return 'Female'
  return egg?.sex ? String(egg.sex) : 'Not recorded'
}

export function buildChickImagePayload(egg, computationResultId, { force = false } = {}) {
  const traits = egg?.collected_traits || {}
  return {
    egg_number: Number(egg?.egg_number) || 1,
    species: speciesText(traits.species || egg?.species),
    scientific_name: scientificName(egg?.species, traits.scientific_name || egg?.scientific_name),
    sex: sexText(egg),
    base_color: traits.base_color || egg?.base_color || 'Not recorded',
    visual_mutations: mutationNames(traits.visual_mutations || egg?.visual_mutations),
    split_genes: mutationNames(
      traits.split_hidden_genes || egg?.split_hidden_genes || egg?.split_genes,
    ),
    computation_result_id: computationResultId || undefined,
    outcome_key: egg?.outcome_key || undefined,
    force: Boolean(force),
  }
}

export async function generateChickImage(egg, computationResultId, options = {}) {
  const payload = buildChickImagePayload(egg, computationResultId, options)
  const { data } = await api.post('/outcomes/generate-image', payload)
  return data
}

export async function generateAllChickImages(computationResultId, { force = false } = {}) {
  const { data } = await api.post(
    `/computation-results/${computationResultId}/generate-all-chick-images`,
    { force: Boolean(force) },
  )
  return data
}
