import { AllelePlain, GenotypeBadge, StatusPill } from '../shared/primitives'

function ParentEncoding({ label, role, parent, karyotype, locusName }) {
  return (
    <div className="gx-encode">
      <p className="gx-encode__label">{label} <span className="gx-muted">({role}{karyotype ? ` · ${karyotype}` : ''})</span></p>
      <dl className="gx-kv gx-kv--tight">
        <div><dt>What was stored</dt><dd>{parent.record || <span className="gx-muted">Not provided</span>}{parent.assumedNonCarrier ? <StatusPill tone="warn">documented non-carrier</StatusPill> : null}</dd></div>
        <div><dt>Genetic code</dt><dd><code className="gx-code">{parent.code}</code></dd></div>
        <div><dt>Two alleles</dt><dd>{parent.valid ? <>{'{'} <GenotypeBadge genotype={parent.alleles.join('/')} /> {'}'}</> : <span className="gx-muted">{parent.reason || 'Not provided'}</span>}</dd></div>
        <div>
          <dt>Plain meaning</dt>
          <dd>
            {parent.interpretation.length ? (
              <ul className="gx-plain-list">
                {parent.interpretation.map((item, index) => (
                  <li key={`${item.allele}-${index}`}>
                    <AllelePlain allele={item.allele} locusName={locusName} />
                    <span className="gx-encode__meaning">{item.meaning || item.text}</span>
                  </li>
                ))}
              </ul>
            ) : <span className="gx-muted">No alleles to interpret.</span>}
          </dd>
        </div>
        {parent.confidence ? <div><dt>Record confidence</dt><dd>{parent.confidence}</dd></div> : null}
      </dl>
    </div>
  )
}

export default function AlleleEncoding({ locus }) {
  const cockLabel = `${locus.roles?.cock?.slot || 'Cock'}${locus.roles?.cock?.birdId ? ` · ${locus.roles.cock.birdId}` : ''}`
  const henLabel = `${locus.roles?.hen?.slot || 'Hen'}${locus.roles?.hen?.birdId ? ` · ${locus.roles.hen.birdId}` : ''}`
  return (
    <div className="gx-encode-grid">
      <ParentEncoding label={cockLabel} role="Cock" parent={locus.parents.cock} karyotype={locus.sexLinked ? 'ZZ' : null} locusName={locus.name} />
      <ParentEncoding label={henLabel} role="Hen" parent={locus.parents.hen} karyotype={locus.sexLinked ? 'ZW' : null} locusName={locus.name} />
      {locus.assumptionNote ? <p className="gx-note gx-encode-grid__note">{locus.assumptionNote}</p> : null}
    </div>
  )
}
