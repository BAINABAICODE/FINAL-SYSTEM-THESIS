import { AlleleBadge, GenotypeBadge, StatusPill } from '../shared/primitives'

function ParentEncoding({ label, role, parent, karyotype }) {
  return (
    <div className="gx-encode">
      <p className="gx-encode__label">{label} <span className="gx-muted">({role}{karyotype ? ` · ${karyotype}` : ''})</span></p>
      <dl className="gx-kv gx-kv--tight">
        <div><dt>Input record</dt><dd>{parent.record || <span className="gx-muted">Not provided</span>}{parent.assumedNonCarrier ? <StatusPill tone="warn">documented non-carrier</StatusPill> : null}</dd></div>
        <div><dt>Stored code</dt><dd><code className="gx-code">{parent.code}</code></dd></div>
        <div><dt>Encoded genotype</dt><dd>{parent.valid ? <>{'{'} <GenotypeBadge genotype={parent.alleles.join('/')} /> {'}'}</> : <span className="gx-muted">{parent.reason || 'Not provided'}</span>}</dd></div>
        <div>
          <dt>Interpretation</dt>
          <dd>
            {parent.interpretation.length ? (
              <ul className="gx-plain-list">
                {parent.interpretation.map((item, index) => (
                  <li key={`${item.allele}-${index}`}><AlleleBadge allele={item.allele} /> {item.text}</li>
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
      <ParentEncoding label={cockLabel} role="Cock" parent={locus.parents.cock} karyotype={locus.sexLinked ? 'ZZ' : null} />
      <ParentEncoding label={henLabel} role="Hen" parent={locus.parents.hen} karyotype={locus.sexLinked ? 'ZW' : null} />
      {locus.assumptionNote ? <p className="gx-note gx-encode-grid__note">{locus.assumptionNote}</p> : null}
    </div>
  )
}
