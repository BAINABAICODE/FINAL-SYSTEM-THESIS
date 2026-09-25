import { GenotypeBadge, SexBadge } from '../shared/primitives'

function names(list, key = 'name') {
  const values = (list || []).map((item) => (typeof item === 'string' ? item : item?.[key])).filter(Boolean)
  return values.length ? values.join(', ') : null
}

function codes(list) {
  const values = (list || []).map((item) => item?.genetic_code).filter(Boolean)
  return values
}

function Cell({ value, fallback = 'Not provided' }) {
  return value ? <>{value}</> : <span className="gx-muted">{fallback}</span>
}

export default function ParentGeneticProfile({ snapshot, species }) {
  const p1 = snapshot?.parent_1 || {}
  const p2 = snapshot?.parent_2 || {}
  const rows = [
    ['Bird ID', (p) => <Cell value={p.bird_id} />],
    ['Species', (p, s) => <Cell value={p.species?.common_name || s?.species} />],
    ['Scientific name', (p, s) => <Cell value={p.species?.scientific_name || s?.scientific_name} />],
    ['Sex', (p, s) => <SexBadge sex={p.sex} label={p.sex_label || s?.sex} />],
    ['Chromosomes', (p) => (String(p.sex).toLowerCase() === 'cock' ? 'ZZ' : String(p.sex).toLowerCase() === 'hen' ? 'ZW' : <span className="gx-muted">Not provided</span>)],
    ['Age', (p) => <Cell value={p.age_months != null ? `${p.age_months} months` : null} />],
    ['Base colour', (p) => <Cell value={p.base_color?.name} />],
    ['Base-colour genotype', (p) => (p.base_color?.genetic_code ? <GenotypeBadge genotype={p.base_color.genetic_code.split('|')[0]} /> : <span className="gx-muted">Not provided</span>)],
    ['Dark factor', (p) => (p.base_color?.genetic_code?.includes('|') ? <GenotypeBadge genotype={p.base_color.genetic_code.split('|')[1]} /> : <span className="gx-muted">Not encoded</span>)],
    ['Visual mutations', (p) => <Cell value={names(p.visual_mutations)} fallback="None recorded" />],
    ['Visual mutation codes', (p) => (codes(p.visual_mutations).length ? codes(p.visual_mutations).map((c) => <code key={c} className="gx-code">{c}</code>) : <span className="gx-muted">—</span>)],
    ['Split / hidden genes', (p) => <Cell value={names(p.split_genes)} fallback="None recorded" />],
    ['Split gene codes', (p) => (codes(p.split_genes).length ? codes(p.split_genes).map((c) => <code key={c} className="gx-code">{c}</code>) : <span className="gx-muted">—</span>)],
    ['Grandparent records', (p) => (p.grandparents_recorded ? `${p.grandparents_recorded} recorded` : <span className="gx-muted">Not provided</span>)],
  ]

  return (
    <div className="gx-table-wrap">
      <table className="gx-table gx-table--profile">
        <caption className="gx-visually-hidden">Parent genetic profile comparison</caption>
        <thead>
          <tr>
            <th scope="col">Attribute</th>
            <th scope="col">Parent 1</th>
            <th scope="col">Parent 2</th>
          </tr>
        </thead>
        <tbody>
          {rows.map(([label, render]) => (
            <tr key={label}>
              <th scope="row">{label}</th>
              <td>{render(p1, species?.parent1)}</td>
              <td>{render(p2, species?.parent2)}</td>
            </tr>
          ))}
        </tbody>
      </table>
      <p className="gx-note">Values are the stored AGAPORA records captured at computation time. Missing information is shown as “Not provided” and is never assumed.</p>
    </div>
  )
}
