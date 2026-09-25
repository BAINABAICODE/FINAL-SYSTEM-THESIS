import { StatusPill } from '../shared/primitives'
import { percentText } from '../shared/format'

function ScoreRing({ score, tone, label }) {
  const value = Math.max(0, Math.min(100, Number(score) || 0))
  const radius = 54
  const circumference = 2 * Math.PI * radius
  const offset = circumference * (1 - value / 100)
  return (
    <div className={`gx-ring is-${tone}`} role="img" aria-label={`Compatibility score ${value} out of 100, ${label}`}>
      <svg viewBox="0 0 128 128" aria-hidden="true">
        <circle className="gx-ring__track" cx="64" cy="64" r={radius} />
        <circle className="gx-ring__fill" cx="64" cy="64" r={radius} strokeDasharray={circumference} strokeDashoffset={offset} />
      </svg>
      <div className="gx-ring__center">
        <span className="gx-ring__value">{Number.isFinite(Number(score)) ? `${Math.round(value)}%` : '—'}</span>
        <span className="gx-ring__label">{label}</span>
      </div>
    </div>
  )
}

export default function CompatibilitySummary({ model, rbgiaSummary, confidence }) {
  const { score, classification, factors, species, summary, recommendation, consistent, capped } = model
  return (
    <div className="gx-summary">
      <div className="gx-summary__score">
        <ScoreRing score={score} tone={classification.tone} label={classification.label} />
        <p className="gx-summary__guidance">{classification.guidance}</p>
        <p className="gx-summary__scale">
          Scale: {classification.bands.map((b) => `${b.label} ${b.min}–${b.max}`).join(' · ')}
        </p>
      </div>
      <div className="gx-summary__body">
        <div className="gx-summary__pills">
          <StatusPill tone={species.sameSpecies ? 'ok' : species.interspecific ? 'warn' : 'neutral'}>
            {species.sameSpecies ? '✓ Same species' : species.interspecific ? '⚠ Interspecific pairing' : 'Species not documented'}
          </StatusPill>
          <StatusPill tone="neutral">{species.label}</StatusPill>
          {confidence ? <StatusPill tone={confidence.overall === 'High' ? 'ok' : confidence.overall === 'Moderate' ? 'warn' : 'bad'}>Prediction confidence: {confidence.overall}</StatusPill> : null}
          {factors.length ? (
            <StatusPill tone={consistent ? 'ok' : 'bad'}>{consistent ? (capped ? 'Score capped by blocking rule' : 'Formula verified against stored score') : 'Score mismatch — see trace'}</StatusPill>
          ) : <StatusPill tone="warn">Legacy result — no factor trace stored</StatusPill>}
        </div>
        {summary ? <p className="gx-summary__text">{summary}</p> : null}
        <h4 className="gx-summary__subtitle">Genetic compatibility score — factor results</h4>
        {!factors.length ? (
          <p className="gx-alert is-warn">
            This result was stored by an earlier engine version without a weighted factor breakdown. The stored score is shown as-is;
            re-run the computation to obtain the full GICA factor trace.
          </p>
        ) : null}
        <ul className="gx-factor-lines">
          {factors.map((factor) => (
            <li key={factor.key}>
              <span className="gx-factor-lines__name">{factor.name}</span>
              <span className="gx-factor-lines__track" aria-hidden="true"><span style={{ width: `${Math.max(0, Math.min(100, factor.raw))}%` }} /></span>
              <span className="gx-factor-lines__value">{percentText(factor.raw / 100, 0)}</span>
              <span className="gx-factor-lines__points">{factor.points} / {factor.max} pts</span>
            </li>
          ))}
        </ul>
        {rbgiaSummary ? (
          <p className="gx-summary__rbgia">
            RBGIA processed <strong>{rbgiaSummary.lociTotal ?? rbgiaSummary.loci?.length ?? '—'}</strong> loci into <strong>{rbgiaSummary.totalOutcomes}</strong> deterministic offspring genotypes
            {rbgiaSummary.sexLinkedTraits?.length ? <> including sex-linked inheritance for <strong>{rbgiaSummary.sexLinkedTraits.join(', ')}</strong></> : null}.
          </p>
        ) : null}
        {recommendation ? <p className="gx-summary__recommendation"><strong>Recommendation.</strong> {recommendation}</p> : null}
      </div>
    </div>
  )
}
