import { useId, useState } from 'react'

export default function Disclosure({ title, eyebrow, icon, defaultOpen = false, badge, children, id }) {
  const [open, setOpen] = useState(defaultOpen)
  const generatedId = useId()
  const panelId = id || `disclosure-${generatedId}`

  return (
    <section className={`gx-disclosure${open ? ' is-open' : ''}`}>
      <h3 className="gx-disclosure__heading">
        <button
          type="button"
          className="gx-disclosure__toggle"
          aria-expanded={open}
          aria-controls={panelId}
          onClick={() => setOpen((value) => !value)}
        >
          <span className="gx-disclosure__chevron" aria-hidden="true">▸</span>
          {icon ? <span className="gx-disclosure__icon" aria-hidden="true">{icon}</span> : null}
          <span className="gx-disclosure__text">
            {eyebrow ? <span className="gx-disclosure__eyebrow">{eyebrow}</span> : null}
            <span className="gx-disclosure__title">{title}</span>
          </span>
          {badge ? <span className="gx-disclosure__badge">{badge}</span> : null}
        </button>
      </h3>
      <div id={panelId} className="gx-disclosure__panel" hidden={!open}>
        {open ? children : null}
      </div>
    </section>
  )
}
