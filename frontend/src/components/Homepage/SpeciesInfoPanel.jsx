import { EYE_RING_GROUP } from './speciesSlides'

export default function SpeciesInfoPanel({ bird, position, total }) {
  if (!bird) return null

  const hasEyeRing = bird.group === EYE_RING_GROUP

  return (
    <section className="bird-panel" aria-live="polite" aria-label="Selected species information">
      <article key={bird.id} className="bird-panel__card">
        <header className="bird-panel__header">
          <span className="bird-panel__eyebrow">Selected species</span>
          <span className="bird-panel__counter">
            {String(position).padStart(2, '0')}
            <span aria-hidden="true"> / </span>
            {String(total).padStart(2, '0')}
          </span>
        </header>

        <h2 className="bird-panel__name">{bird.commonName}</h2>
        <p className="bird-panel__latin">
          <em>{bird.scientificName}</em>
          {bird.alternateName ? <span className="bird-panel__alt">also {bird.alternateName}</span> : null}
        </p>

        <div className="bird-panel__tags">
          <span className={`bird-panel__chip${hasEyeRing ? ' is-eye-ring' : ''}`}>
            <span className="bird-panel__chip-dot" aria-hidden="true" />
            {bird.group}
          </span>
          <span className="bird-panel__chip is-genus">Genus Agapornis</span>
        </div>

        <p className="bird-panel__description">{bird.description}</p>

        <a className="bird-panel__link" href="#breeding">
          Compute a pairing with this species
          <span aria-hidden="true"> →</span>
        </a>
      </article>
    </section>
  )
}
