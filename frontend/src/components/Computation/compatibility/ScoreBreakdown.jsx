import { KeyValue, StatusPill } from '../shared/primitives'
import { percentText } from '../shared/format'

function factorEvidence(factor, model, trace) {
  const { species } = model
  switch (factor.key) {
    case 'species_compatibility':
      return [
        ['Parent 1', `${species.parent1.species || 'Not provided'}${species.parent1.scientific_name ? ` (${species.parent1.scientific_name})` : ''}`],
        ['Parent 2', `${species.parent2.species || 'Not provided'}${species.parent2.scientific_name ? ` (${species.parent2.scientific_name})` : ''}`],
        ['Result', species.label],
      ]
    case 'inheritance_information':
      return [
        ['Loci calculated', `${trace?.loci?.filter((l) => l.status === 'calculated').length ?? '—'} of ${trace?.loci?.length ?? '—'}`],
        ['Data confidence', factor.value || 'Not provided'],
      ]
    case 'genetic_risk':
      return [
        ['Risk level', model.risk?.level || factor.value || 'Not provided'],
        ['Detected recessive / shared loci', (trace?.loci || []).filter((l) => l.mode.recessive).map((l) => l.name).join(', ') || 'None calculated'],
        ['Affected combinations', (model.risk?.affected || []).length ? model.risk.affected.map((a) => `${a.locus || ''} ${a.possible_offspring || a.message || ''}`.trim()).join('; ') : 'None documented'],
      ]
    case 'genetic_diversity':
      return [
        ['Diversity level', model.diversity?.level || factor.value || 'Not provided'],
        ['Differing loci', (model.diversity?.loci || []).filter((l) => l.relationship === 'different').map((l) => l.locus).join(', ') || 'None'],
        ['Identical loci', (model.diversity?.loci || []).filter((l) => l.relationship === 'identical').map((l) => l.locus).join(', ') || 'None'],
      ]
    case 'mutation_compatibility':
      return [
        ['Dataset verdict', model.mutation?.level || factor.value || 'Not provided'],
        ['Sex-linked loci', (trace?.sexLinkedLoci || []).map((l) => `${l.name} (${l.roles?.cock?.slot || 'Cock'} = ZZ, ${l.roles?.hen?.slot || 'Hen'} = ZW)`).join('; ') || 'None detected'],
      ]
    default:
      return [['Result', factor.value || 'Not provided']]
  }
}

export default function ScoreBreakdown({ model, trace }) {
  if (!model.factors.length) {
    return (
      <div className="gx-trace">
        <p className="gx-alert is-warn">No per-factor computation is stored for this result. Stored engine score: <strong>{model.backendScore ?? '—'}</strong> · Classification: <strong>{model.classification.label}</strong>.</p>
        {model.legacyRows.length ? (
          <KeyValue rows={model.legacyRows.map((row, index) => [row.factor || `Row ${index + 1}`, `${row.points ?? row.contribution ?? '—'} · ${row.value ?? ''}`])} />
        ) : null}
      </div>
    )
  }
  return (
    <div className="gx-trace">
      {model.factors.map((factor, index) => (
        <article key={factor.key} className="gx-trace__step">
          <header className="gx-trace__head">
            <span className="gx-trace__index">{index + 1}</span>
            <h4>{factor.name}</h4>
            <StatusPill tone={factor.raw >= 80 ? 'ok' : factor.raw >= 50 ? 'warn' : 'bad'}>{factor.value || '—'}</StatusPill>
          </header>
          <KeyValue rows={factorEvidence(factor, model, trace)} />
          <dl className="gx-calc">
            <div><dt>Raw score</dt><dd>{factor.points} / {factor.max} → <strong>{factor.raw.toFixed(2)} / 100</strong></dd></div>
            <div><dt>Weight</dt><dd>{factor.symbol} = {factor.max} ÷ {model.totalMax} = <strong>{factor.weight.toFixed(2)}</strong> ({percentText(factor.weight, 0)})</dd></div>
            <div><dt>Weighted contribution</dt><dd><code>{factor.formula}</code></dd></div>
          </dl>
          {factor.detail ? <p className="gx-note">{factor.detail}</p> : null}
        </article>
      ))}
      <article className="gx-trace__total">
        <h4>Total compatibility</h4>
        <p className="gx-trace__sum">
          {model.factors.map((f) => f.weighted.toFixed(2)).join(' + ')} = <strong>{model.computedTotal.toFixed(2)}%</strong>
        </p>
        {model.capped ? (
          <p className="gx-note">A blocking rule (validation error, incompatible species, or unsupported pairing) caps the stored score at {model.backendScore}. Raw score before cap: {model.rawBeforeCap ?? model.computedTotal.toFixed(2)}.</p>
        ) : null}
        <p>Stored engine score: <strong>{model.backendScore ?? '—'}</strong> · Classification: <strong>{model.classification.label}</strong> ({model.classification.guidance})</p>
        {!model.consistent ? <p className="gx-alert is-bad">The factor sum does not reproduce the stored score. The stored score remains authoritative; please report this result.</p> : null}
      </article>
    </div>
  )
}
