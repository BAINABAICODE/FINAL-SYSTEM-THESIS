import { useState } from 'react'
import OffspringOutcomeCard from './OffspringOutcomeCard'

const PAGE = 6

export default function OutcomeCards({ rows }) {
  const [limit, setLimit] = useState(PAGE)
  const visible = rows.slice(0, limit)
  const remaining = rows.length - visible.length
  if (!rows.length) return <p className="gx-muted">No joint offspring outcomes were returned by the engine.</p>
  return (
    <div className="gx-cards">
      <div className="gx-cards__grid">
        {visible.map((row) => <OffspringOutcomeCard key={row.id} row={row} rank={row.rank} />)}
      </div>
      {remaining > 0 ? (
        <button type="button" className="gx-linkbtn" onClick={() => setLimit((v) => v + PAGE)}>
          Show {Math.min(PAGE, remaining)} more of {remaining} remaining outcome cards
        </button>
      ) : null}
    </div>
  )
}
