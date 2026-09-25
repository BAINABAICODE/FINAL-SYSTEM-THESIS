import { useEffect, useId, useState } from 'react'
import SearchableSelect from './SearchableSelect.jsx'
import { getSpeciesFormPreview } from '../../assets/species-form/index.js'
import './BirdFormModal.css'

const SEX_OPTIONS = [
  { value: 'hen', label: 'Hen — Female' },
  { value: 'cock', label: 'Cock — Male' },
]

const MAX_AGE_MONTHS = 180
const AGE_MONTH_OPTIONS = Array.from({ length: MAX_AGE_MONTHS + 1 }, (_, months) => months)

function ageMonthOptions(current) {
  const extra = Number(current)
  if (Number.isFinite(extra) && extra > MAX_AGE_MONTHS) {
    return [...AGE_MONTH_OPTIONS, extra]
  }
  return AGE_MONTH_OPTIONS
}

const GRANDPARENT_DEFS = [
  { key: 'paternal_grandfather', title: 'Paternal Grandfather' },
  { key: 'paternal_grandmother', title: 'Paternal Grandmother' },
  { key: 'maternal_grandfather', title: 'Maternal Grandfather' },
  { key: 'maternal_grandmother', title: 'Maternal Grandmother' },
]

export function createEmptyGrandparent() {
  return {
    species_id: null,
    base_color_id: null,
    visual_mutation_id: null,
    visual_mutation_ids: [],
    split_gene_id: null,
    split_gene_ids: [],
  }
}

export function createEmptyBirdForm() {
  return {
    bird_id: '',
    age_months: '',
    species_id: null,
    sex: '',
    base_color_id: null,
    visual_mutation_id: null,
    visual_mutation_ids: [],
    split_gene_id: null,
    split_gene_ids: [],
    grandparents: {
      paternal_grandfather: createEmptyGrandparent(),
      paternal_grandmother: createEmptyGrandparent(),
      maternal_grandfather: createEmptyGrandparent(),
      maternal_grandmother: createEmptyGrandparent(),
    },
  }
}

export function birdToForm(bird) {
  if (!bird) return createEmptyBirdForm()

  const grandparents = createEmptyBirdForm().grandparents

  GRANDPARENT_DEFS.forEach(({ key }) => {
    const record = bird.grandparents?.[key]
    grandparents[key] = record
      ? {
          species_id: record.species_id ?? null,
          base_color_id: record.base_color_id ?? null,
          visual_mutation_id: record.visual_mutation_id ?? null,
          visual_mutation_ids: mutationIdsFromRecord(record),
          split_gene_id: record.split_gene_id ?? null,
          split_gene_ids: geneIdsFromRecord(record),
        }
      : createEmptyGrandparent()
  })

  return {
    bird_id: bird.bird_id ?? '',
    age_months: bird.age_months ?? '',
    species_id: bird.species_id ?? null,
    sex: bird.sex ?? '',
    base_color_id: bird.base_color_id ?? null,
    visual_mutation_id: bird.visual_mutation_id ?? null,
    visual_mutation_ids: mutationIdsFromRecord(bird),
    split_gene_id: bird.split_gene_id ?? null,
    split_gene_ids: geneIdsFromRecord(bird),
    grandparents,
  }
}

function speciesLabel(option) {
  if (!option) return ''
  if (option.alternate_names) {
    return `${option.common_name} (${option.alternate_names})`
  }
  return option.common_name
}

function colorsForSpecies(baseColors, speciesId) {
  if (!speciesId) return []
  return baseColors.filter((color) => String(color.species_id) === String(speciesId))
}

function genesForBird(splitGenes, speciesId, sex) {
  if (!speciesId) return []
  return splitGenes.filter((gene) => {
    if (String(gene.species_id) !== String(speciesId)) return false
    if (sex === 'hen' && gene.hen_can_split === false) return false
    if (sex === 'cock' && gene.cock_can_split === false) return false
    return true
  })
}

function geneIdsFromRecord(record) {
  if (Array.isArray(record?.split_gene_ids) && record.split_gene_ids.length) {
    return record.split_gene_ids.filter((id) => id !== null && id !== '')
  }
  if (Array.isArray(record?.split_genes) && record.split_genes.length) {
    return record.split_genes.map((gene) => gene.id)
  }
  if (record?.split_gene_id) return [record.split_gene_id]
  return []
}

function keepGeneIds(ids, splitGenes, speciesId, sex) {
  const allowed = new Set(genesForBird(splitGenes, speciesId, sex).map((gene) => String(gene.id)))
  return (ids || []).filter((id) => allowed.has(String(id)))
}

function sexForGrandparent(role) {
  if (String(role).includes('grandmother')) return 'hen'
  if (String(role).includes('grandfather')) return 'cock'
  return null
}

function geneConflicts(candidate, selected) {
  if (!candidate || !selected?.length) return false
  return selected.some((current) => {
    if (String(current.id) === String(candidate.id)) return false
    return Boolean(
      candidate.carrier_locus &&
        current.carrier_locus &&
        candidate.carrier_locus === current.carrier_locus,
    )
  })
}

function mutationsForSpecies(visualMutations, speciesId) {
  if (!speciesId) return []
  return visualMutations.filter((mutation) => String(mutation.species_id) === String(speciesId))
}

function mutationIdsFromRecord(record) {
  if (Array.isArray(record?.visual_mutation_ids) && record.visual_mutation_ids.length) {
    return record.visual_mutation_ids.filter((id) => id !== null && id !== '')
  }
  if (Array.isArray(record?.visual_mutations) && record.visual_mutations.length) {
    return record.visual_mutations.map((mutation) => mutation.id)
  }
  if (record?.visual_mutation_id) return [record.visual_mutation_id]
  return []
}

function keepMutationIdsForSpecies(ids, visualMutations, speciesId) {
  const allowed = new Set(mutationsForSpecies(visualMutations, speciesId).map((mutation) => String(mutation.id)))
  return (ids || []).filter((id) => allowed.has(String(id)))
}

function isDocumentedCombination(left, right) {
  const leftNames = left.combination_names || []
  const rightNames = right.combination_names || []
  return leftNames.includes(right.name) || rightNames.includes(left.name)
}

function mutationConflicts(candidate, selected) {
  if (!candidate || !selected?.length) return false

  return selected.some((current) => {
    if (String(current.id) === String(candidate.id)) return false
    if (candidate.dosage_key && current.dosage_key && candidate.dosage_key === current.dosage_key) {
      return true
    }
    if (candidate.locus_key && current.locus_key && candidate.locus_key === current.locus_key) {
      return !isDocumentedCombination(candidate, current)
    }
    return false
  })
}

function GrandparentFields({
  title,
  sex,
  value,
  onChange,
  speciesOptions,
  baseColors,
  visualMutations,
  splitGenes,
  errors = {},
}) {
  return (
    <fieldset className="bird-modal__grandparent">
      <legend className="bird-modal__grandparent-title">{title}</legend>
      <div className="bird-modal__grid bird-modal__grid--gp">
        <SearchableSelect
          readable
          label="Species"
          options={speciesOptions}
          value={value.species_id}
          onChange={(next) =>
            onChange({
              ...value,
              species_id: next,
              base_color_id: colorsForSpecies(baseColors, next).some(
                (color) => String(color.id) === String(value.base_color_id),
              )
                ? value.base_color_id
                : null,
              visual_mutation_ids: keepMutationIdsForSpecies(
                value.visual_mutation_ids,
                visualMutations,
                next,
              ),
              visual_mutation_id: null,
              split_gene_ids: keepGeneIds(value.split_gene_ids, splitGenes, next, sex),
              split_gene_id: null,
            })
          }
          getOptionLabel={speciesLabel}
          getOptionValue={(option) => option.id}
          getOptionPreview={(option) => getSpeciesFormPreview(option, null)}
          allowEmpty
          emptyLabel="None"
          searchPlaceholder="Search species…"
          error={errors.species_id}
        />
        <SearchableSelect
          readable
          label="Base Color"
          options={colorsForSpecies(baseColors, value.species_id)}
          value={value.base_color_id}
          onChange={(next) => onChange({ ...value, base_color_id: next })}
          allowEmpty
          emptyLabel="None"
          searchPlaceholder="Search base colors…"
          error={errors.base_color_id}
        />
        <SearchableSelect
          readable
          multiple
          label="Visual Mutation"
          options={mutationsForSpecies(visualMutations, value.species_id)}
          value={value.visual_mutation_ids || []}
          onChange={(next) =>
            onChange({
              ...value,
              visual_mutation_ids: next,
              visual_mutation_id: next[0] ?? null,
            })
          }
          isOptionDisabled={(option) =>
            mutationConflicts(
              option,
              mutationsForSpecies(visualMutations, value.species_id).filter((item) =>
                (value.visual_mutation_ids || []).some((id) => String(id) === String(item.id)),
              ),
            )
          }
          allowEmpty
          emptyLabel="None"
          disabled={!value.species_id}
          searchPlaceholder={value.species_id ? 'Search mutations…' : 'Select a species first'}
          error={errors.visual_mutation_ids || errors.visual_mutation_id}
        />
        <SearchableSelect
          readable
          multiple
          label="Split Genes / Hidden Genes"
          options={genesForBird(splitGenes, value.species_id, sex)}
          value={value.split_gene_ids || []}
          onChange={(next) =>
            onChange({
              ...value,
              split_gene_ids: next,
              split_gene_id: next[0] ?? null,
            })
          }
          isOptionDisabled={(option) =>
            geneConflicts(
              option,
              genesForBird(splitGenes, value.species_id, sex).filter((item) =>
                (value.split_gene_ids || []).some((id) => String(id) === String(item.id)),
              ),
            )
          }
          allowEmpty
          emptyLabel="None"
          disabled={!value.species_id}
          searchPlaceholder={value.species_id ? 'Search genes…' : 'Select a species first'}
          error={errors.split_gene_ids || errors.split_gene_id}
        />
      </div>
    </fieldset>
  )
}

export default function BirdFormModal({
  open,
  mode = 'create',
  initialBird = null,
  speciesOptions = [],
  baseColors = [],
  visualMutations = [],
  splitGenes = [],
  submitting = false,
  onClose,
  onSubmit,
}) {
  const titleId = useId()
  const [form, setForm] = useState(createEmptyBirdForm)
  const [grandparentsOpen, setGrandparentsOpen] = useState(false)
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState('')

  useEffect(() => {
    if (!open) return
    setForm(birdToForm(initialBird))
    setGrandparentsOpen(
      Boolean(
        initialBird &&
          GRANDPARENT_DEFS.some((item) => {
            const gp = initialBird.grandparents?.[item.key]
            return (
              gp &&
              (gp.species_id ||
                gp.base_color_id ||
                gp.visual_mutation_id ||
                gp.visual_mutation_ids?.length ||
                gp.split_gene_id ||
                gp.split_gene_ids?.length)
            )
          }),
      ),
    )
    setErrors({})
    setFormError('')
  }, [open, initialBird])

  useEffect(() => {
    if (!open) return undefined
    const previous = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    const onKeyDown = (event) => {
      if (event.key === 'Escape') {
        if (document.querySelector('.search-select__menu')) return
        onClose()
      }
    }

    document.addEventListener('keydown', onKeyDown)
    return () => {
      document.body.style.overflow = previous
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [open, onClose])

  if (!open) return null

  const updateField = (field, value) => {
    setForm((prev) => ({ ...prev, [field]: value }))
  }

  const updateGrandparent = (role, next) => {
    setForm((prev) => ({
      ...prev,
      grandparents: {
        ...prev.grandparents,
        [role]: next,
      },
    }))
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setErrors({})
    setFormError('')

    const localErrors = {}
    if (!String(form.bird_id).trim()) localErrors.bird_id = ['Bird ID is required.']
    if (form.age_months === '' || form.age_months === null) localErrors.age_months = ['Age is required.']
    if (!form.species_id) localErrors.species_id = ['Species is required.']
    if (!form.sex) localErrors.sex = ['Sex is required.']

    if (Object.keys(localErrors).length) {
      setErrors(localErrors)
      setFormError('Please complete the required bird fields.')
      return
    }

    const payload = {
      bird_id: String(form.bird_id).trim(),
      age_months: Number(form.age_months),
      species_id: form.species_id,
      sex: form.sex,
      base_color_id: form.base_color_id || null,
      visual_mutation_id: form.visual_mutation_ids?.[0] || null,
      visual_mutation_ids: form.visual_mutation_ids || [],
      split_gene_id: form.split_gene_ids?.[0] || null,
      split_gene_ids: form.split_gene_ids || [],
      grandparents: Object.fromEntries(
        GRANDPARENT_DEFS.map(({ key }) => {
          const gp = form.grandparents[key]
          const mutationIds = keepMutationIdsForSpecies(gp.visual_mutation_ids, visualMutations, gp.species_id)
          const geneIds = keepGeneIds(gp.split_gene_ids, splitGenes, gp.species_id, sexForGrandparent(key))
          return [
            key,
            {
              ...gp,
              visual_mutation_ids: mutationIds,
              visual_mutation_id: mutationIds[0] ?? null,
              split_gene_ids: geneIds,
              split_gene_id: geneIds[0] ?? null,
            },
          ]
        }),
      ),
    }

    try {
      await onSubmit(payload)
    } catch (error) {
      const responseErrors = error?.response?.data?.errors
      const message = error?.response?.data?.message
      if (responseErrors) setErrors(responseErrors)
      setFormError(message || 'Unable to save bird. Please review the form and try again.')
    }
  }

  const firstError = (key) => {
    const value = errors?.[key]
    if (Array.isArray(value)) return value[0]
    if (typeof value === 'string') return value
    return ''
  }

  return (
    <div className="bird-modal" role="presentation">
      <button type="button" className="bird-modal__backdrop" aria-label="Close dialog" onClick={onClose} />

      <div
        className="bird-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
      >
        <header className="bird-modal__header">
          <div>
            <p className="bird-modal__eyebrow">Birds Management</p>
            <h2 id={titleId} className="bird-modal__title">
              {mode === 'edit' ? 'Edit Bird' : 'Add Bird'}
            </h2>
          </div>
          <button type="button" className="bird-modal__close" aria-label="Close" onClick={onClose}>
            ×
          </button>
        </header>

        <form className="bird-modal__form" onSubmit={handleSubmit} noValidate>
          <div className="bird-modal__body">
            {formError ? <p className="bird-modal__banner">{formError}</p> : null}

            <section className="bird-modal__section">
              <h3 className="bird-modal__section-title">Main Bird Information</h3>
              <div className="bird-modal__grid">
                <label className="field">
                  <span className="field__label">
                    Bird ID <span className="search-select__required">*</span>
                  </span>
                  <input
                    className={`field__input${firstError('bird_id') ? ' is-invalid' : ''}`}
                    type="text"
                    value={form.bird_id}
                    onChange={(event) => updateField('bird_id', event.target.value)}
                    placeholder="e.g. LB-2044"
                    autoComplete="off"
                  />
                  {firstError('bird_id') ? <span className="field__error">{firstError('bird_id')}</span> : null}
                </label>

                <label className="field">
                  <span className="field__label">
                    Age (Months) <span className="search-select__required">*</span>
                  </span>
                  <select
                    className={`field__input${firstError('age_months') ? ' is-invalid' : ''}`}
                    value={form.age_months === '' || form.age_months === null ? '' : String(form.age_months)}
                    onChange={(event) => updateField('age_months', event.target.value)}
                  >
                    <option value="">Select age</option>
                    {ageMonthOptions(form.age_months).map((months) => (
                      <option key={months} value={String(months)}>
                        {months} {months === 1 ? 'month' : 'months'}
                      </option>
                    ))}
                  </select>
                  {firstError('age_months') ? (
                    <span className="field__error">{firstError('age_months')}</span>
                  ) : null}
                </label>

                <SearchableSelect
                  readable
                  label="Species"
                  required
                  options={speciesOptions}
                  value={form.species_id}
                  onChange={(next) => {
                    const stillValid = colorsForSpecies(baseColors, next).some(
                      (color) => String(color.id) === String(form.base_color_id),
                    )
                    const mutationIds = keepMutationIdsForSpecies(
                      form.visual_mutation_ids,
                      visualMutations,
                      next,
                    )
                    const geneIds = keepGeneIds(form.split_gene_ids, splitGenes, next, form.sex)
                    setForm((prev) => ({
                      ...prev,
                      species_id: next,
                      base_color_id: stillValid ? prev.base_color_id : null,
                      visual_mutation_ids: mutationIds,
                      visual_mutation_id: mutationIds[0] ?? null,
                      split_gene_ids: geneIds,
                      split_gene_id: geneIds[0] ?? null,
                    }))
                  }}
                  getOptionLabel={speciesLabel}
                  getOptionValue={(option) => option.id}
                  getOptionPreview={(option) => getSpeciesFormPreview(option, form.sex)}
                  allowEmpty={false}
                  placeholder="Select species"
                  emptyLabel="Select species"
                  searchPlaceholder="Search species…"
                  error={firstError('species_id')}
                />

                <label className="field">
                  <span className="field__label">
                    Sex <span className="search-select__required">*</span>
                  </span>
                  <select
                    className={`field__input${firstError('sex') ? ' is-invalid' : ''}`}
                    value={form.sex}
                    onChange={(event) => {
                      const nextSex = event.target.value
                      const geneIds = keepGeneIds(form.split_gene_ids, splitGenes, form.species_id, nextSex)
                      setForm((prev) => ({
                        ...prev,
                        sex: nextSex,
                        split_gene_ids: geneIds,
                        split_gene_id: geneIds[0] ?? null,
                      }))
                    }}
                  >
                    <option value="">Select sex</option>
                    {SEX_OPTIONS.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                  {firstError('sex') ? <span className="field__error">{firstError('sex')}</span> : null}
                </label>

                <SearchableSelect
                  readable
                  label="Base Color"
                  options={colorsForSpecies(baseColors, form.species_id)}
                  value={form.base_color_id}
                  onChange={(next) => updateField('base_color_id', next)}
                  allowEmpty
                  emptyLabel="None"
                  searchPlaceholder="Search base colors…"
                  error={firstError('base_color_id')}
                />

                <SearchableSelect
                  readable
                  multiple
                  label="Visual Mutation"
                  options={mutationsForSpecies(visualMutations, form.species_id)}
                  value={form.visual_mutation_ids}
                  onChange={(next) => {
                    setForm((prev) => ({
                      ...prev,
                      visual_mutation_ids: next,
                      visual_mutation_id: next[0] ?? null,
                    }))
                  }}
                  isOptionDisabled={(option) =>
                    mutationConflicts(
                      option,
                      mutationsForSpecies(visualMutations, form.species_id).filter((item) =>
                        form.visual_mutation_ids.some((id) => String(id) === String(item.id)),
                      ),
                    )
                  }
                  allowEmpty
                  emptyLabel="None"
                  disabled={!form.species_id}
                  searchPlaceholder={form.species_id ? 'Search mutations…' : 'Select a species first'}
                  error={firstError('visual_mutation_ids') || firstError('visual_mutation_id')}
                />

                <div className="bird-modal__span-2">
                  <SearchableSelect
                    readable
                    multiple
                    label="Split Genes / Hidden Genes"
                    options={genesForBird(splitGenes, form.species_id, form.sex)}
                    value={form.split_gene_ids}
                    onChange={(next) =>
                      setForm((prev) => ({
                        ...prev,
                        split_gene_ids: next,
                        split_gene_id: next[0] ?? null,
                      }))
                    }
                    isOptionDisabled={(option) =>
                      geneConflicts(
                        option,
                        genesForBird(splitGenes, form.species_id, form.sex).filter((item) =>
                          form.split_gene_ids.some((id) => String(id) === String(item.id)),
                        ),
                      )
                    }
                    allowEmpty
                    emptyLabel="None"
                    disabled={!form.species_id}
                    searchPlaceholder={
                      form.species_id ? 'Search genes…' : 'Select a species first'
                    }
                    error={firstError('split_gene_ids') || firstError('split_gene_id')}
                  />
                </div>
              </div>
            </section>

            <section className="bird-modal__grandparents">
              <button
                type="button"
                className="bird-modal__gp-toggle"
                aria-expanded={grandparentsOpen}
                onClick={() => setGrandparentsOpen((openState) => !openState)}
              >
                <span>
                  <span className="bird-modal__gp-title">Grandparents Information</span>
                  <span className="bird-modal__gp-hint">Optional lineage fields</span>
                </span>
                <span className={`bird-modal__gp-chevron${grandparentsOpen ? ' is-open' : ''}`} aria-hidden="true" />
              </button>

              {grandparentsOpen ? (
                <div className="bird-modal__gp-body">
                  {GRANDPARENT_DEFS.map((item) => (
                    <GrandparentFields
                      key={item.key}
                      title={item.title}
                      sex={sexForGrandparent(item.key)}
                      value={form.grandparents[item.key]}
                      onChange={(next) => updateGrandparent(item.key, next)}
                      speciesOptions={speciesOptions}
                      baseColors={baseColors}
                      visualMutations={visualMutations}
                      splitGenes={splitGenes}
                      errors={{
                        species_id: firstError(`grandparents.${item.key}.species_id`),
                        base_color_id: firstError(`grandparents.${item.key}.base_color_id`),
                        visual_mutation_id: firstError(`grandparents.${item.key}.visual_mutation_id`),
                        visual_mutation_ids: firstError(`grandparents.${item.key}.visual_mutation_ids`),
                        split_gene_id: firstError(`grandparents.${item.key}.split_gene_id`),
                        split_gene_ids: firstError(`grandparents.${item.key}.split_gene_ids`),
                      }}
                    />
                  ))}
                </div>
              ) : null}
            </section>
          </div>

          <footer className="bird-modal__footer">
            <button type="button" className="birds__btn birds__btn--ghost" onClick={onClose} disabled={submitting}>
              Cancel
            </button>
            <button type="submit" className="birds__btn birds__btn--primary" disabled={submitting}>
              {submitting ? 'Saving…' : mode === 'edit' ? 'Update Bird' : 'Save Bird'}
            </button>
          </footer>
        </form>
      </div>
    </div>
  )
}
