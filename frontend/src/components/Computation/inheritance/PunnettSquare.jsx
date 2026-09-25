import { GenotypeBadge } from '../shared/primitives'
import { percentText } from '../shared/format'

/**
 * Renders a Punnett grid from the engine's `square` (rows = cock gametes, cols = hen gametes).
 * For Z-linked loci the W column produces daughters and the Z column produces sons.
 */
export default function PunnettSquare({ locus }) {
  const { square, outcomes, roles } = locus
  if (!square.valid) {
    return <p className="gx-alert is-warn">Punnett square unavailable: {square.reason}</p>
  }
  const rowP = 1 / square.rows.length
  const colP = 1 / square.cols.length
  const findOutcome = (cell) => outcomes.find((o) => o.sex === cell.sex && (o.paths || []).some((p) => p.fromCock === cell.fromCock && p.fromHen === cell.fromHen))

  return (
    <div className="gx-punnett-wrap">
      <table className="gx-punnett" aria-label={`Punnett square for ${locus.name}`}>
        <caption>
          Rows: {roles?.cock?.slot || 'Cock'} gametes (ZZ) · Columns: {roles?.hen?.slot || 'Hen'} gametes ({square.sexLinked ? 'ZW' : 'diploid'}) · {square.total} cells, each {percentText(rowP * colP)}
        </caption>
        <thead>
          <tr>
            <th scope="col" className="gx-punnett__corner"><span aria-hidden="true">♂ ↓ / ♀ →</span><span className="gx-visually-hidden">cock gametes by hen gametes</span></th>
            {square.cols.map((allele, index) => (
              <th key={`col-${index}`} scope="col" className="gx-punnett__axis">
                <GenotypeBadge genotype={allele} />
                <span className="gx-punnett__axis-p">{percentText(colP)}{square.sexLinked ? (allele === 'W' ? ' · W egg → daughter' : ' · Z egg → son') : ''}</span>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {square.rows.map((rowAllele, rowIndex) => (
            <tr key={`row-${rowIndex}`}>
              <th scope="row" className="gx-punnett__axis">
                <GenotypeBadge genotype={rowAllele} />
                <span className="gx-punnett__axis-p">{percentText(rowP)}{square.sexLinked ? ' · Z sperm' : ''}</span>
              </th>
              {square.cols.map((_, colIndex) => {
                const cell = square.cells.find((c) => c.rowIndex === rowIndex && c.colIndex === colIndex)
                const outcome = cell ? findOutcome(cell) : null
                const phenotype = outcome?.phenotype
                return (
                  <td key={`cell-${rowIndex}-${colIndex}`} className={`gx-punnett__cell is-${cell?.sex || 'both'}${phenotype?.visual ? ' is-visual' : phenotype?.carrier ? ' is-carrier' : ''}`}>
                    <GenotypeBadge genotype={cell?.genotype} />
                    <span className="gx-punnett__sex">{cell?.sex === 'cock' ? '♂ son' : cell?.sex === 'hen' ? '♀ daughter' : 'either sex'}</span>
                    <span className="gx-punnett__expr">{phenotype?.expressionLabel || '—'}</span>
                    <span className="gx-punnett__p">{cell?.path?.expression}</span>
                  </td>
                )
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
