import { StatusPill } from '../shared/primitives'

function ComplexityTable({ rows, mode }) {
  const showTime = mode === 'time'
  return (
    <div className="gx-table-wrap">
      <table className="gx-table">
        <caption className="gx-visually-hidden">
          {showTime ? 'Time complexity of each stored calculation' : 'Space complexity of each stored calculation'}
        </caption>
        <thead>
          <tr>
            <th scope="col">Calculation</th>
            <th scope="col">What was counted</th>
            <th scope="col">{showTime ? 'Time' : 'Space'}</th>
            <th scope="col">{showTime ? 'Operations' : 'Stored units'}</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.id}>
              <th scope="row">
                {row.name}
                <span className="gx-muted gx-stack">{row.group}</span>
              </th>
              <td>{row.detail}</td>
              <td><code className="gx-code">{showTime ? row.time : row.space}</code></td>
              <td>{showTime ? row.timeOps : row.spaceUnits}</td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr>
            <th scope="row">This pair</th>
            <td>Sum of the rows above — not a generic constant.</td>
            <td />
            <td>
              {showTime
                ? rows.reduce((sum, row) => sum + row.timeOps, 0)
                : rows.reduce((sum, row) => sum + row.spaceUnits, 0)}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  )
}

export default function ComplexitySection({ mode, report, proves }) {
  const isTime = mode === 'time'
  const headline = isTime ? report.time : report.space
  const { L, C, P, R, F, E, S } = report.variables

  return (
    <section className="compute-result__card compute-section-panel">
      <p className="compute-result__eyebrow">Step 08 · {report.method} · counted from this stored run</p>
      <h2>{isTime ? 'Time complexity' : 'Space complexity'}</h2>
      {proves ? (
        <p className="compute-proves">
          <span className="compute-proves__label">What this proves</span>
          {proves}
        </p>
      ) : null}
      <p className="compute-result__empty">
        {isTime
          ? 'This chapter is not genetics. It counts the work this pair actually required: Punnett cells per locus, the joint genotype product, GICA factors, and clutch stages. Offspring odds are not recalculated here.'
          : 'This chapter is not genetics. It counts the data this result kept: Punnett cells, retained offspring rows, GICA factor records, and simulated eggs. Nothing extra is allocated beyond those stored structures.'}
      </p>

      <div className="compute-summary__grid compute-summary__grid--forecast">
        <article className="compute-metric">
          <p className="compute-metric__label">Asymptotic bound</p>
          <p className="compute-metric__value">{headline.bound}</p>
          <p className="compute-metric__hint">{headline.explanation}</p>
        </article>
        <article className="compute-metric">
          <p className="compute-metric__label">This computation</p>
          <p className="compute-metric__value">{headline.substituted}</p>
          <p className="compute-metric__hint">
            {isTime
              ? `${headline.measuredOps} primitive combination steps counted from the payload.`
              : `${headline.measuredUnits} stored records counted from the payload.`}
          </p>
        </article>
        <article className="compute-metric">
          <p className="compute-metric__label">Counts used</p>
          <p className="compute-metric__value">L={L} · C={C} · P={P}</p>
          <p className="compute-metric__hint">R={R} joint rows · F={F} GICA factors · E={E} eggs · S={S} stages</p>
        </article>
      </div>

      <div className="compute-complexity__vars" aria-label="Variable definitions">
        <StatusPill tone="info">L loci calculated</StatusPill>
        <StatusPill tone="info">C Punnett cells</StatusPill>
        <StatusPill tone="info">P = Π kᵢ joint product</StatusPill>
        <StatusPill tone="info">R retained rows</StatusPill>
        <StatusPill tone="info">F GICA factors</StatusPill>
        <StatusPill tone="info">E eggs · S stages</StatusPill>
      </div>

      <h3>{isTime ? 'Time cost of every calculation' : 'Space cost of every calculation'}</h3>
      <ComplexityTable rows={report.calculations} mode={mode} />
    </section>
  )
}
