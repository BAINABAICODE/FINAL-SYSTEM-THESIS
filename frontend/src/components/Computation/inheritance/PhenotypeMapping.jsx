import { ExpressionBadge, GenotypeBadge, SexBadge } from '../shared/primitives'

export default function PhenotypeMapping({ locus }) {
  if (!locus.outcomes.length) return <p className="gx-muted">No genotypes to map.</p>
  return (
    <ol className="gx-mapping">
      {locus.outcomes.map((outcome) => {
        const p = outcome.phenotype
        return (
          <li key={`${outcome.sex}-${outcome.genotype}`} className="gx-mapping__row">
            <div className="gx-mapping__geno">
              <GenotypeBadge genotype={outcome.genotype} />
              {outcome.sex !== 'both' ? <SexBadge sex={outcome.sex} label={outcome.sex === 'cock' ? '♂ son' : '♀ daughter'} /> : null}
            </div>
            <span className="gx-mapping__arrow" aria-hidden="true">↓</span>
            <div className="gx-mapping__rule">
              <ExpressionBadge expression={p.expression} label={p.expressionLabel} />
              <span className="gx-muted"> under {locus.mode.label}</span>
            </div>
            <span className="gx-mapping__arrow" aria-hidden="true">↓</span>
            <div className="gx-mapping__result">
              <p><strong>Visual:</strong> {p.visualMutations.length ? p.visualMutations.join(', ') : p.baseColor || (p.visual ? 'Expressed' : 'No visual change at this locus')}</p>
              {p.splitHidden.length ? <p><strong>Carrier / split:</strong> {p.splitHidden.join(', ')}</p> : null}
              <p className="gx-mapping__text">{p.phenotype || <span className="gx-muted">{p.phenotypeNote || 'Not specified in stored phenotype record.'}</span>}</p>
              {!p.storedMatch ? <p className="gx-note">Expression class derived from the inheritance mode; no stored phenotype row matched this genotype.</p> : null}
            </div>
          </li>
        )
      })}
    </ol>
  )
}
