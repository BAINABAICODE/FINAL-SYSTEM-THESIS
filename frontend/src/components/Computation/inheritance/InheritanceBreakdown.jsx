import { GenotypeBadge, ModeBadge, StatusPill } from '../shared/primitives'

export default function InheritanceBreakdown({ rows }) {
  if (!rows.length) return <p className="gx-muted">No loci were processed.</p>
  return (
    <div className="gx-table-wrap">
      <table className="gx-table gx-table--breakdown">
        <caption className="gx-visually-hidden">Per-locus inheritance breakdown</caption>
        <thead>
          <tr>
            <th scope="col">Gene / trait</th>
            <th scope="col">Mode</th>
            <th scope="col">Cock alleles</th>
            <th scope="col">Hen alleles</th>
            <th scope="col">Outcome</th>
            <th scope="col">Formula</th>
            <th scope="col">Check</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.key}>
              <th scope="row">{row.name}<span className="gx-muted gx-stack">{row.category?.replace(/_/g, ' ')}</span></th>
              <td><ModeBadge mode={row.mode} /></td>
              <td><GenotypeBadge genotype={row.cockAlleles} /></td>
              <td><GenotypeBadge genotype={row.henAlleles} /></td>
              <td className="gx-table__outcome">{row.outcomeText}</td>
              <td><code className="gx-code">{row.formula}</code></td>
              <td>
                {row.verified === true ? <StatusPill tone="ok">✓ matches engine</StatusPill>
                  : row.verified === false ? <StatusPill tone="bad">✕ mismatch</StatusPill>
                    : <StatusPill tone="neutral">not compared</StatusPill>}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
