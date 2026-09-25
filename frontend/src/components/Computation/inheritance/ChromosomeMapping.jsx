import { AlleleBadge } from '../shared/primitives'

function Karyotype({ label, role, data }) {
  return (
    <div className="gx-karyo">
      <p className="gx-karyo__label">{label} <span className="gx-muted">— {role}</span></p>
      <p className="gx-karyo__type">{data.karyotype}</p>
      <div className="gx-karyo__chromosomes">
        {data.chromosomes.map((chromosome, index) => (
          <div key={`${chromosome.chromosome}-${index}`} className={`gx-chromosome is-${chromosome.chromosome.toLowerCase()}${chromosome.wild === false ? ' is-mutant' : ''}`}>
            <span className="gx-chromosome__name">{chromosome.chromosome}</span>
            {chromosome.chromosome === 'W'
              ? <span className="gx-chromosome__allele gx-muted">no allele</span>
              : <span className="gx-chromosome__allele"><AlleleBadge allele={chromosome.allele} /></span>}
          </div>
        ))}
      </div>
      {!data.valid ? <p className="gx-alert is-warn">Stored code does not match the expected {data.karyotype} configuration.</p> : null}
    </div>
  )
}

export default function ChromosomeMapping({ locus }) {
  if (!locus.chromosomes) return null
  return (
    <div className="gx-karyo-grid">
      <Karyotype label={locus.roles?.cock?.slot || 'Cock'} role="Cock" data={locus.chromosomes.cock} />
      <Karyotype label={locus.roles?.hen?.slot || 'Hen'} role="Hen" data={locus.chromosomes.hen} />
    </div>
  )
}
