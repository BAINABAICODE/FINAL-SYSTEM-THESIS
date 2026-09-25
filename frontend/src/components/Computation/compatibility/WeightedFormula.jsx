import { percentText } from '../shared/format'

export default function WeightedFormula({ model }) {
  const { factors, weightsSumPercent, totalMax, methodology } = model
  if (!factors.length) {
    return <p className="gx-alert is-warn">No weighted factor breakdown is stored for this result, so the formula cannot be reconstructed. Re-run the computation with the current engine.</p>
  }
  return (
    <div className="gx-formula">
      <p className="gx-formula__intro">
        The application's GICA analyzer awards points per factor out of a fixed maximum and sums them to {totalMax}.
        Expressed as a weighted model, each factor's weight is its maximum share of the total:
      </p>
      <pre className="gx-formula__block" aria-label="Weighted compatibility formula">
{`Compatibility Score =
${factors.map((f) => `  (${f.symbol} × ${f.name})`).join('\n+\n')}

where  ${f0(factors)}
Raw_i  = points_i ÷ max_i × 100      (0–100 per factor)
W_i    = max_i ÷ ${totalMax}                 (weights sum to 1)
Score  = Σ W_i × Raw_i  ∈  0–100`}
      </pre>
      <table className="gx-table">
        <caption className="gx-visually-hidden">Weights used by the application</caption>
        <thead>
          <tr><th scope="col">Factor</th><th scope="col">Symbol</th><th scope="col">Max points</th><th scope="col">Weight</th></tr>
        </thead>
        <tbody>
          {factors.map((f) => (
            <tr key={f.key}>
              <th scope="row">{f.name}</th>
              <td><code>{f.symbol}</code></td>
              <td>{f.max}</td>
              <td>{percentText(f.weight, 0)}</td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr><th scope="row">Total</th><td /><td>{totalMax}</td><td>{percentText(weightsSumPercent / 100, 0)}</td></tr>
        </tfoot>
      </table>
      {methodology ? <p className="gx-note">Engine methodology: {methodology}</p> : null}
    </div>
  )
}

function f0(factors) {
  return factors.map((f) => `${f.symbol} = ${f.name.toLowerCase()} weight`).join('\n       ')
}
