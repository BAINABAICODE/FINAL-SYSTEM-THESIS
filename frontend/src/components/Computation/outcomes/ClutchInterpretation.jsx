import { useMemo, useState } from 'react'
import { buildClutchInterpretation, CLUTCH_DISCLAIMER, DEFAULT_CLUTCH_SIZES } from '../../../services/genetics'
import { percentText } from '../shared/format'

export default function ClutchInterpretation({ appearanceOutcomes }) {
  const [min, setMin] = useState(DEFAULT_CLUTCH_SIZES[0])
  const [max, setMax] = useState(DEFAULT_CLUTCH_SIZES[DEFAULT_CLUTCH_SIZES.length - 1])
  const sizes = useMemo(() => {
    const lo = Math.max(1, Math.min(min, max))
    const hi = Math.min(12, Math.max(min, max))
    return Array.from({ length: hi - lo + 1 }, (_, i) => lo + i)
  }, [min, max])
  const clutches = useMemo(() => buildClutchInterpretation(appearanceOutcomes, sizes), [appearanceOutcomes, sizes])

  return (
    <div className="gx-clutch">
      <div className="gx-clutch__controls">
        <label>Clutch range from
          <input type="number" min="1" max="12" value={min} onChange={(e) => setMin(Number(e.target.value) || 1)} />
        </label>
        <label>to
          <input type="number" min="1" max="12" value={max} onChange={(e) => setMax(Number(e.target.value) || 1)} />
        </label>
        <span className="gx-muted">eggs</span>
      </div>
      <p className="gx-note">Expected theoretical distribution for the top {Math.min(8, appearanceOutcomes.length)} visual appearance classes (mean ± one binomial standard deviation, rounded to whole eggs).</p>
      <div className="gx-table-wrap">
        <table className="gx-table gx-table--clutch">
          <caption className="gx-visually-hidden">Expected theoretical distribution per clutch size</caption>
          <thead>
            <tr>
              <th scope="col">Visual result</th>
              <th scope="col">Probability</th>
              {clutches.map((c) => <th key={c.size} scope="col">{c.size} eggs</th>)}
            </tr>
          </thead>
          <tbody>
            {(clutches[0]?.rows || []).map((row, index) => (
              <tr key={row.label}>
                <th scope="row">{row.label}</th>
                <td>{percentText(row.probability)}</td>
                {clutches.map((c) => {
                  const cell = c.rows[index]
                  return <td key={c.size}>≈ {cell.low === cell.high ? cell.low : `${cell.low}–${cell.high}`}</td>
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="gx-alert is-warn">{CLUTCH_DISCLAIMER}</p>
    </div>
  )
}
