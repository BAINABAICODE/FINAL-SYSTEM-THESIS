import { AllelePlain } from '../shared/primitives'
import { percentText } from '../shared/format'

function GameteList({ label, gametes, sexLinked, role, locusName }) {
  return (
    <div className="gx-gametes__parent">
      <p className="gx-gametes__label">{label}</p>
      {gametes.gametes.length ? (
        <ul className="gx-gametes__list">
          {gametes.gametes.map((g) => (
            <li key={g.allele}>
              <AllelePlain allele={g.allele} locusName={locusName} />
              <span className="gx-gametes__arrow" aria-hidden="true">→</span>
              <strong>{percentText(g.probability, 0)}</strong>
              <span className="gx-muted"> ({g.count} of {g.total} gametes{sexLinked ? (g.isW ? ', W egg' : role === 'hen' ? ', Z egg' : ', Z sperm') : ''})</span>
            </li>
          ))}
        </ul>
      ) : <p className="gx-muted">No gametes — parental genotype not provided.</p>}
    </div>
  )
}

export default function GameteFormation({ locus }) {
  const cock = locus.gametes.cock
  const hen = locus.gametes.hen
  const heteroCock = cock.gametes.length > 1
  const heteroHen = hen.gametes.filter((g) => !g.isW).length > 1
  return (
    <div className="gx-gametes">
      <GameteList label={`${locus.roles?.cock?.slot || 'Cock'} (cock${locus.sexLinked ? ', ZZ' : ''})`} gametes={cock} sexLinked={locus.sexLinked} role="cock" locusName={locus.name} />
      <GameteList label={`${locus.roles?.hen?.slot || 'Hen'} (hen${locus.sexLinked ? ', ZW' : ''})`} gametes={hen} sexLinked={locus.sexLinked} role="hen" locusName={locus.name} />
      <p className="gx-note gx-gametes__note">
        Law of segregation: each allele copy enters a gamete with equal probability.
        {heteroCock || heteroHen ? ' Heterozygous parents therefore produce two gamete types at 50% each;' : ' Homozygous parents produce a single gamete type at 100%;'}
        {locus.sexLinked ? ' the hen passes either her Z allele (to sons) or W (to daughters).' : ' both parents contribute one allele to every offspring.'}
      </p>
    </div>
  )
}
