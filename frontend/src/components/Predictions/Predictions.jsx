import { useCallback, useEffect, useMemo, useState } from 'react'
import { createPortal } from 'react-dom'
import api from '../../api/client'
import { getSpeciesFormPreview } from '../../assets/species-form/index.js'
import './Predictions.css'

const PAGE_SIZE = 9

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString()
}

function statusClass(status) {
  const key = String(status || '').toLowerCase()
  if (key.includes('provisional')) return 'is-provisional'
  if (key.includes('complete') || key.includes('completed')) return 'is-complete'
  if (key.includes('fail') || key.includes('block')) return 'is-failed'
  return 'is-neutral'
}

function hasParentProfile(parent) {
  if (!parent || typeof parent !== 'object' || Array.isArray(parent)) return false
  return Boolean(parent.bird_id || parent.species || parent.sex || parent.base_color)
}

function listNames(items) {
  if (!Array.isArray(items) || !items.length) return '—'
  const names = items.map((item) => item?.name).filter(Boolean)
  return names.length ? names.join(', ') : '—'
}

function parentPreview(parent) {
  if (!parent) return null
  return getSpeciesFormPreview(
    {
      id: parent.species?.id || parent.species_id,
      common_name: parent.species?.common_name,
    },
    parent.sex,
  )
}

function sexLabel(parent) {
  if (parent?.sex_label) return parent.sex_label
  if (parent?.sex === 'hen') return 'Hen — Female'
  if (parent?.sex === 'cock') return 'Cock — Male'
  return parent?.sex || '—'
}

function placePairHover(rect) {
  const width = Math.min(680, window.innerWidth - 16)
  const height = 280
  const gap = 10
  const pad = 8
  let left = rect.right + gap
  let top = rect.top

  if (left + width > window.innerWidth - pad) {
    left = rect.left - gap - width
  }
  if (left < pad) {
    left = Math.max(pad, (window.innerWidth - width) / 2)
    top = rect.bottom + gap
  }
  if (top + height > window.innerHeight - pad) {
    top = Math.max(pad, window.innerHeight - height - pad)
  }
  if (top < pad) top = pad

  return {
    top: `${Math.round(top)}px`,
    left: `${Math.round(left)}px`,
    width: `${Math.round(width)}px`,
  }
}

function PairBirdColumn({ parent, fallbackId, role }) {
  const preview = parentPreview(parent)
  const birdId = parent?.bird_id || fallbackId || '—'

  return (
    <div className="pair-hover__bird">
      <p className="pair-hover__role">{role}</p>
      <div className="pair-hover__row">
        {preview?.src ? (
          <div className="pair-hover__figure">
            <img src={preview.src} alt="" />
          </div>
        ) : null}
        <div className="pair-hover__body">
          <p className="pair-hover__id">{birdId}</p>
          {hasParentProfile(parent) ? (
            <dl>
              <div>
                <dt>Species</dt>
                <dd>{parent.species?.label || parent.species?.common_name || '—'}</dd>
              </div>
              <div>
                <dt>Sex</dt>
                <dd>{sexLabel(parent)}</dd>
              </div>
              <div>
                <dt>Age</dt>
                <dd>{parent.age_months != null ? `${parent.age_months} mo` : '—'}</dd>
              </div>
              <div>
                <dt>Base Color</dt>
                <dd>{parent.base_color?.name || '—'}</dd>
              </div>
              <div>
                <dt>Visual Mutation</dt>
                <dd>{listNames(parent.visual_mutations)}</dd>
              </div>
              <div>
                <dt>Split / Hidden Genes</dt>
                <dd>{listNames(parent.split_genes)}</dd>
              </div>
            </dl>
          ) : (
            <p className="pair-hover__missing">No saved profile for this bird.</p>
          )}
        </div>
      </div>
    </div>
  )
}

function PairHoverCard({ item, anchor }) {
  return createPortal(
    <div className="pair-hover" style={placePairHover(anchor)} role="tooltip">
      <p className="pair-hover__title">
        {item.parent_1_bird_id} × {item.parent_2_bird_id}
      </p>
      <div className="pair-hover__grid">
        <PairBirdColumn parent={item.parent_1} fallbackId={item.parent_1_bird_id} role="Parent 1" />
        <PairBirdColumn parent={item.parent_2} fallbackId={item.parent_2_bird_id} role="Parent 2" />
      </div>
    </div>,
    document.body,
  )
}

function pairBadge(label, status) {
  const text = label || status || 'Pairing'
  if (String(status || '').includes('SAME')) return { text, tone: 'same' }
  if (String(status || '').includes('HYBRID')) return { text, tone: 'hybrid' }
  if (String(status || '').includes('UNSUPPORTED')) return { text, tone: 'unsupported' }
  return { text, tone: 'neutral' }
}

export default function Predictions() {
  const [items, setItems] = useState([])
  const [selected, setSelected] = useState(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [page, setPage] = useState(1)
  const [hoveredId, setHoveredId] = useState(null)
  const [hoverAnchor, setHoverAnchor] = useState(null)

  const loadHistory = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const response = await api.get('/predictions')
      setItems(response.data?.data || [])
    } catch {
      setError('Saved computations could not be loaded. Make sure the server is running, then retry.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadHistory()
  }, [loadHistory])

  const totalPages = Math.max(1, Math.ceil(items.length / PAGE_SIZE))

  useEffect(() => {
    if (page > totalPages) setPage(totalPages)
  }, [page, totalPages])

  const pageItems = useMemo(() => {
    const start = (page - 1) * PAGE_SIZE
    return items.slice(start, start + PAGE_SIZE)
  }, [items, page])

  const openComputation = async (id) => {
    setError('')
    try {
      const response = await api.get(`/predictions/${id}`)
      setSelected(response.data?.data || null)
    } catch {
      setError('This computation could not be opened.')
    }
  }

  const handleViewResult = (item) => {
    if (item.computation_result_id) {
      window.location.hash = `computation/${item.computation_result_id}`
      return
    }
    openComputation(item.id)
  }

  return (
    <main id="computation-history" className="history">
      <div className="history__shell">
        <header className="history__header">
          <div className="history__header-copy">
            <p className="history__eyebrow">Records</p>
            <h1 className="history__title">Computation History</h1>
            <p className="history__lede">
              Review saved breeding computations and open a result when you need the full outcome.
            </p>
          </div>
          <p className="history__count">
            <span>{items.length}</span>
            saved
          </p>
        </header>

        {error ? (
          <div className="history__alert" role="alert">
            <p>{error}</p>
            <button type="button" className="history__btn history__btn--ghost" onClick={loadHistory}>
              Retry
            </button>
          </div>
        ) : null}

        <section className="history-panel" aria-label="Saved computation history">
          <div className="history-panel__head">
            <div>
              <h2 className="history-panel__title">Saved Computations</h2>
              <p className="history-panel__subtitle">
                {loading ? 'Loading records…' : `${items.length} record${items.length === 1 ? '' : 's'} shown`}
              </p>
            </div>
          </div>

          {loading ? (
            <div className="history-empty">
              <p className="history-empty__title">Loading computations</p>
              <p className="history-empty__copy">Fetching saved records from the server.</p>
            </div>
          ) : items.length ? (
            <>
              <div className="history__grid">
                {pageItems.map((item) => {
                  const badge = pairBadge(item.compatibility_label, item.compatibility_status)
                  const showHover = hoveredId === item.id && hoverAnchor
                  return (
                    <article
                      key={item.id}
                      className="history-card"
                      onMouseEnter={(event) => {
                        setHoveredId(item.id)
                        setHoverAnchor(event.currentTarget.getBoundingClientRect())
                      }}
                      onMouseLeave={() => {
                        setHoveredId((current) => (current === item.id ? null : current))
                        setHoverAnchor(null)
                      }}
                    >
                      {showHover ? <PairHoverCard item={item} anchor={hoverAnchor} /> : null}
                      <div className="history-card__top">
                        <span className={`history-card__badge is-${badge.tone}`}>{badge.text}</span>
                        <span className={`history-card__status ${statusClass(item.status)}`}>
                          {item.status || 'saved'}
                        </span>
                      </div>

                      <h3 className="history-card__pair">
                        <span>{item.parent_1_bird_id}</span>
                        <span className="history-card__times" aria-hidden="true">
                          ×
                        </span>
                        <span>{item.parent_2_bird_id}</span>
                      </h3>

                      <p className="history-card__meta">{formatDate(item.created_at)}</p>

                      <button
                        type="button"
                        className="history__btn history__btn--primary"
                        onClick={() => handleViewResult(item)}
                      >
                        View Result
                      </button>
                    </article>
                  )
                })}
              </div>

              {totalPages > 1 ? (
                <div className="history__pager">
                  <button
                    type="button"
                    className="history__btn history__btn--ghost"
                    disabled={page <= 1}
                    onClick={() => setPage((current) => current - 1)}
                  >
                    Previous
                  </button>
                  <p>
                    Page {page} of {totalPages}
                  </p>
                  <button
                    type="button"
                    className="history__btn history__btn--ghost"
                    disabled={page >= totalPages}
                    onClick={() => setPage((current) => current + 1)}
                  >
                    Next
                  </button>
                </div>
              ) : null}
            </>
          ) : (
            <div className="history-empty">
              <p className="history-empty__title">No computations yet</p>
              <p className="history-empty__copy">
                Run Start Breeding to create your first computation record.
              </p>
              <a className="history__btn history__btn--primary" href="#breeding">
                Start Breeding
              </a>
            </div>
          )}
        </section>

        {selected ? (
          <section className="history-detail" aria-label="Computation detail">
            <div className="history-detail__head">
              <h2>
                {selected.parent_1_bird_id} × {selected.parent_2_bird_id}
              </h2>
              <button type="button" className="history__btn history__btn--ghost" onClick={() => setSelected(null)}>
                Close
              </button>
            </div>
            <p>{selected.prediction?.message}</p>
            {(selected.prediction?.outcomes || []).map((outcome) => (
              <article key={`${outcome.category}-${outcome.name}`} className="history-outcome">
                <h3>{outcome.name}</h3>
                <p>{outcome.reason || outcome.status}</p>
                {outcome.results?.length ? (
                  <ul>
                    {outcome.results.map((row) => (
                      <li key={`${row.sex}-${row.genotype}`}>
                        {row.sex}: {row.genotype} — {row.fraction}
                      </li>
                    ))}
                  </ul>
                ) : null}
              </article>
            ))}
          </section>
        ) : null}
      </div>
    </main>
  )
}
