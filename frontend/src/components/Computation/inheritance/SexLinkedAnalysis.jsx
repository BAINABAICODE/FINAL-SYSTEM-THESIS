import { SEX_LINKED_RULES } from '../../../services/genetics'
import { GenotypeBadge, SexBadge } from '../shared/primitives'
import { percentText } from '../shared/format'
import ChromosomeMapping from './ChromosomeMapping'
import GameteFormation from './GameteFormation'
import PunnettSquare from './PunnettSquare'

export default function SexLinkedAnalysis({ loci }) {
  if (!loci.length) {
    return (
      <p className="gx-alert is-ok">
        No sex-linked (Z-linked) mutation was present in the stored parental records, so all calculated loci follow autosomal inheritance.
        Sex is still determined by the ZZ / ZW chromosomal cross (50% ♂ / 50% ♀).
      </p>
    )
  }
  return (
    <div className="gx-sexlinked">
      <ul className="gx-rules">
        {SEX_LINKED_RULES.map((rule) => <li key={rule}>{rule}</li>)}
      </ul>
      {loci.map((locus) => (
        <article key={locus.key} className="gx-sexlinked__locus">
          <h4 className="gx-sexlinked__title">{locus.name} <span className="gx-muted">· {locus.mode.label}</span></h4>
          <h5 className="gx-subhead">Chromosome mapping</h5>
          <ChromosomeMapping locus={locus} />
          <h5 className="gx-subhead">Gamete formation</h5>
          <GameteFormation locus={locus} />
          <h5 className="gx-subhead">Z-linked Punnett square</h5>
          <PunnettSquare locus={locus} />
          <h5 className="gx-subhead">Sex-specific result</h5>
          <ul className="gx-sexlinked__results">
            {locus.outcomes.map((outcome) => (
              <li key={`${outcome.sex}-${outcome.genotype}`}>
                <strong>{percentText(outcome.probability)}</strong>
                <SexBadge sex={outcome.sex} label={outcome.sex === 'cock' ? '♂' : '♀'} />
                <GenotypeBadge genotype={outcome.genotype} />
                <span className={`gx-sl-class is-${String(outcome.sexLinkedClass || '').replace(/\s+/g, '-')}`}>{outcome.sexLinkedClass}</span>
                <span className="gx-muted">{outcome.phenotype.expressionLabel}</span>
              </li>
            ))}
          </ul>
        </article>
      ))}
    </div>
  )
}
