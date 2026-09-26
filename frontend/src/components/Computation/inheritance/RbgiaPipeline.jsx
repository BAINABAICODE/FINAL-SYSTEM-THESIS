import { useState } from 'react'
import { PIPELINE_STEPS } from '../../../services/genetics'
import { ModeBadge, StatusPill } from '../shared/primitives'
import AlleleEncoding from './AlleleEncoding'
import GameteFormation from './GameteFormation'
import PunnettSquare from './PunnettSquare'
import PhenotypeMapping from './PhenotypeMapping'
import FormulaTrace from './FormulaTrace'

const STAGES = [
  { id: 'F1', title: 'Translate parent alleles', hint: 'What each stored code means', Component: AlleleEncoding },
  { id: 'F2', title: 'What each parent can pass on', hint: 'Gametes — one allele each', Component: GameteFormation },
  { id: 'F3', title: 'Punnett square', hint: 'Every possible chick for this gene', Component: PunnettSquare },
  { id: 'F4', title: 'What the chick will look like', hint: 'Genotype → visible result', Component: PhenotypeMapping },
  { id: 'F5', title: 'Add up the odds', hint: 'Same boxes are grouped and counted', Component: FormulaTrace },
]

export default function RbgiaPipeline({ loci }) {
  const [activeKey, setActiveKey] = useState(loci[0]?.key || null)
  const active = loci.find((l) => l.key === activeKey) || loci[0]
  if (!active) return <p className="gx-muted">The engine did not return any per-locus computation.</p>

  return (
    <div className="gx-pipeline">
      <ol className="gx-pipeline__flow" aria-label="RBGIA data flow">
        {STAGES.map((step) => <li key={step.id}>{step.id} {step.title}</li>)}
      </ol>
      <p className="gx-pipeline__read">
        Pick a gene below. The same five steps run for every stored locus: encode → pass on → Punnett → looks → odds.
        Full engine path ({PIPELINE_STEPS.length} steps) stays available in the trace.
      </p>

      <div className="gx-pipeline__loci" role="tablist" aria-label="Loci processed by RBGIA">
        {loci.map((locus) => (
          <button
            key={locus.key}
            type="button"
            role="tab"
            id={`locus-tab-${slug(locus.key)}`}
            aria-selected={locus.key === active.key}
            aria-controls={`locus-panel-${slug(active.key)}`}
            className={`gx-pipeline__locus${locus.key === active.key ? ' is-active' : ''}`}
            onClick={() => setActiveKey(locus.key)}
          >
            <span className="gx-pipeline__locus-name">{locus.name}</span>
            <span className="gx-pipeline__locus-meta">{locus.sexLinked ? 'Z-linked' : locus.mode.label.split(' ')[0]} · {locus.outcomes.length || '—'} genotype{locus.outcomes.length === 1 ? '' : 's'}</span>
          </button>
        ))}
      </div>

      <div id={`locus-panel-${slug(active.key)}`} role="tabpanel" aria-labelledby={`locus-tab-${slug(active.key)}`} className="gx-pipeline__panel">
        <header className="gx-pipeline__head">
          <div>
            <h4>{active.name}</h4>
            <p className="gx-muted">Stored inheritance type: {active.inheritanceType || 'not provided'}</p>
          </div>
          <div className="gx-pipeline__badges">
            <ModeBadge mode={active.mode} />
            <StatusPill tone={active.status === 'calculated' ? 'ok' : 'warn'}>{active.status}</StatusPill>
            {active.verified === true ? <StatusPill tone="ok">✓ trace = engine</StatusPill> : active.verified === false ? <StatusPill tone="bad">✕ trace mismatch</StatusPill> : null}
            {active.provisional ? <StatusPill tone="warn">provisional</StatusPill> : null}
          </div>
        </header>
        {STAGES.map(({ id, title, hint, Component }) => (
          <section key={id} className="gx-stage">
            <h5 className="gx-stage__title">
              <span className="gx-stage__id">{id}</span>
              <span>
                {title}
                {hint ? <small className="gx-stage__hint">{hint}</small> : null}
              </span>
            </h5>
            <Component locus={active} />
          </section>
        ))}
        {active.verificationDetail ? <p className="gx-note">Verification: {active.verificationDetail}</p> : null}
      </div>
    </div>
  )
}

function slug(value) {
  return String(value).replace(/[^a-z0-9]+/gi, '-').toLowerCase()
}
