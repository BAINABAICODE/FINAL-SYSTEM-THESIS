import { collectAlleleGlossary } from '../../../services/genetics'
import { AlleleBadge } from '../shared/primitives'

export default function AlleleTranslation({ loci = [], title = 'Allele translation' }) {
  const rows = collectAlleleGlossary(loci)
  if (!rows.length) return null

  return (
    <section className="allele-lexicon" aria-label={title}>
      <header className="allele-lexicon__head">
        <p className="allele-lexicon__kicker">How to read the codes</p>
        <h3>{title}</h3>
        <p>
          Each parent stores two letters per gene.
          {' '}
          <strong>+</strong>
          {' '}
          means normal (not the mutation). A letter without + is the mutation.
          {' '}
          <strong>W</strong>
          {' '}
          is the hen’s W chromosome — it carries no color gene.
        </p>
      </header>
      <ul className="allele-lexicon__list">
        {rows.map((row) => (
          <li key={`${row.allele}-${row.text}`} className={`allele-lexicon__item is-${row.kind}`}>
            <AlleleBadge allele={row.allele} title={row.meaning} />
            <div>
              <p className="allele-lexicon__kind">{row.kindLabel}</p>
              <p className="allele-lexicon__plain">{row.plain}</p>
              <p className="allele-lexicon__meaning">{row.meaning}</p>
            </div>
          </li>
        ))}
      </ul>
    </section>
  )
}
