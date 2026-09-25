import { GenotypeBadge, ModeBadge, ProbabilityBar, SexBadge } from '../shared/primitives'
import { percentText } from '../shared/format'
import { resolveInheritanceMode } from '../../../services/genetics'

function LociGenotype({ genotype }) {
  const parts = String(genotype || '').split('|').map((s) => s.trim()).filter(Boolean)
  if (!parts.length) return <span className="gx-muted">Not provided</span>
  return (
    <ul className="gx-card__loci">
      {parts.map((part) => {
        const [name, code] = part.split(':').map((s) => s.trim())
        return <li key={part}><span className="gx-card__locus-name">{name}</span> <GenotypeBadge genotype={code || name} /></li>
      })}
    </ul>
  )
}

function inheritanceSummary(row) {
  const modes = new Map()
  ;(row.loci || []).forEach((locus) => {
    if (!locus.probability || locus.probability >= 1) return
    const mode = resolveInheritanceMode(locus.inheritance_type, locus.category)
    modes.set(mode.key, mode)
  })
  if (!modes.size && row.inheritanceClasses) {
    return Object.entries(row.inheritanceClasses).filter(([, v]) => v).map(([k]) => k.replace(/_/g, ' ')).join(', ') || 'Fixed loci only'
  }
  return [...modes.values()]
}

function geneticExplanation(row) {
  const visual = row.visualMutations.length ? `${row.visualMutations.join(' and ')} expressed visually` : 'no visual mutation expressed'
  const splits = row.splitHiddenGenes.length ? `; carries ${row.splitHiddenGenes.join(', ')} as hidden split allele${row.splitHiddenGenes.length > 1 ? 's' : ''}` : ''
  const dark = row.darkFactor && row.darkFactor !== 'none' ? ` with ${row.darkFactor} dark factor` : ''
  return `${row.sexLabel || 'Offspring'} with ${row.baseColor || 'stored base colour'}${dark}; ${visual}${splits}. Each locus was combined independently, so the joint probability is the product of the per-locus Punnett probabilities.`
}

export default function OffspringOutcomeCard({ row, rank }) {
  const modes = inheritanceSummary(row)
  return (
    <article className="gx-card" aria-labelledby={`outcome-${row.id}-title`}>
      <header className="gx-card__head">
        <p className="gx-card__eyebrow" id={`outcome-${row.id}-title`}>Outcome #{rank}</p>
        <SexBadge sex={row.sex} label={row.sexLabel} />
      </header>
      {row.imageUrl ? (
        <figure className="gx-card__media">
          <img src={row.imageUrl} alt={`Illustrative visualisation of ${row.baseColor || 'offspring'}${row.visualMutations.length ? ` ${row.visualMutations.join(' ')}` : ''}, ${row.sexLabel || ''}`} loading="lazy" />
          <figcaption>Visualisation of the already-determined genotype{row.eggNumber ? ` (egg card ${row.eggNumber})` : ''}. The image does not influence the genetics.</figcaption>
        </figure>
      ) : null}
      <dl className="gx-card__facts">
        <div>
          <dt>Visual result</dt>
          <dd><strong>{row.baseColor || 'Not stored'}</strong>{row.darkFactor && row.darkFactor !== 'none' ? ` · ${row.darkFactor}` : ''}{row.visualMutations.length ? ` · ${row.visualMutations.join(', ')}` : ''}</dd>
        </div>
        <div>
          <dt>Split / hidden</dt>
          <dd>{row.splitHiddenGenes.length ? row.splitHiddenGenes.join(', ') : <span className="gx-muted">None</span>}</dd>
        </div>
        <div>
          <dt>Genotype</dt>
          <dd><LociGenotype genotype={row.genotype} /></dd>
        </div>
        <div>
          <dt>Probability</dt>
          <dd><ProbabilityBar probability={row.probability} label={`Outcome ${rank}`} compact />{row.fraction ? <span className="gx-muted"> {row.fraction}</span> : null}</dd>
        </div>
        <div>
          <dt>Sex</dt>
          <dd>{row.sexLabel || 'Not sex-linked / depends on inheritance rule'} <span className="gx-muted">(ZZ / ZW cross, 1/2 each)</span></dd>
        </div>
        <div>
          <dt>Inheritance</dt>
          <dd>{Array.isArray(modes) ? (modes.length ? modes.map((m) => <ModeBadge key={m.key} mode={m} />) : <span className="gx-muted">All loci fixed in both parents</span>) : modes}</dd>
        </div>
        <div>
          <dt>Formula trace</dt>
          <dd>{row.formula ? <code className="gx-code">F5: {row.formula}</code> : <code className="gx-code">{row.fraction || percentText(row.probability)}</code>}{row.productMatches === false ? <span className="gx-alert is-bad"> product ≠ stored</span> : null}</dd>
        </div>
        <div>
          <dt>Genetic explanation</dt>
          <dd>{geneticExplanation(row)}</dd>
        </div>
      </dl>
      {row.phenotype ? <p className="gx-card__phenotype">{row.phenotype}</p> : null}
    </article>
  )
}
