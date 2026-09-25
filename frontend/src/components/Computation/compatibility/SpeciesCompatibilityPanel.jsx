import { KeyValue, StatusPill } from '../shared/primitives'

function ParentSpecies({ label, parent }) {
  return (
    <div className="gx-species__parent">
      <p className="gx-species__slot">{label}</p>
      <p className="gx-species__common">{parent?.species || 'Not provided'}</p>
      <p className="gx-species__sci">{parent?.scientific_name || 'Scientific name not provided'}</p>
      <p className="gx-species__meta">{parent?.bird_id || '—'} · {parent?.sex || 'Sex not provided'}{parent?.species_group ? ` · ${parent.species_group}` : ''}</p>
    </div>
  )
}

export default function SpeciesCompatibilityPanel({ species }) {
  const tone = species.sameSpecies ? 'ok' : species.interspecific ? 'warn' : 'neutral'
  return (
    <div className="gx-species">
      <div className="gx-species__grid">
        <ParentSpecies label="Parent 1" parent={species.parent1} />
        <div className="gx-species__verdict">
          <StatusPill tone={tone}>
            {species.sameSpecies ? '✓ Same species' : species.interspecific ? '⚠ Interspecific pairing' : 'Not documented'}
          </StatusPill>
        </div>
        <ParentSpecies label="Parent 2" parent={species.parent2} />
      </div>
      {species.interspecific ? (
        <p className="gx-alert is-warn">
          The selected parents belong to different species records. Compatibility classification: <strong>{species.label}</strong>.
          Genetic prediction confidence: <strong>Reduced</strong>. The pairing is not labelled impossible unless the stored species-compatibility rules say so.
        </p>
      ) : null}
      <KeyValue rows={[
        ['Breeding type', species.breedingType ? species.breedingType.replace(/_/g, ' ') : null],
        ['Species compatibility', species.scorePercent != null ? `${species.scorePercent}%` : null],
        ['Prediction allowed', species.predictionAllowed ? 'Yes' : 'No'],
        ['Scientific basis', species.basis],
        ['Rule applied', species.methodology],
      ]} />
      {species.warning ? <p className="gx-alert is-warn">{species.warning}</p> : null}
    </div>
  )
}
