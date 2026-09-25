import { useEffect } from 'react'
import './Help.css'

const CONTENTS = [
  { id: 'help-start', n: '01', label: 'First pairing', hint: 'The shortest path to a result' },
  { id: 'help-birds', n: '02', label: 'Birds ledger', hint: 'What to record, and why' },
  { id: 'help-fields', n: '03', label: 'Parent fields', hint: 'Every input on Start Breeding' },
  { id: 'help-report', n: '04', label: 'Reading a report', hint: 'What each result tab means' },
  { id: 'help-stuck', n: '05', label: 'When it blocks', hint: 'Insufficient data and warnings' },
  { id: 'help-terms', n: '06', label: 'Terms', hint: 'RBGIA, GICA, splits, sex' },
]

const START_STEPS = [
  {
    n: '01',
    title: 'Record the parents',
    copy: 'Open Birds and add each lovebird with a unique Bird ID, species, sex, age, base color, visual mutations, and any split genes. Pedigree is optional.',
    href: '#birds',
    action: 'Open Birds',
  },
  {
    n: '02',
    title: 'Compose the pair',
    copy: 'Go to Start Breeding. Load two saved birds, or type the parents in. One cock and one hen. Species and sex must be set before analysis will run.',
    href: '#breeding',
    action: 'Start Breeding',
  },
  {
    n: '03',
    title: 'Run Start Analysis',
    copy: 'AGAPORA checks breeding-safety and species compatibility, then RBGIA traces inheritance and GICA scores the pair. The computation result opens automatically.',
  },
  {
    n: '04',
    title: 'Keep the ledger',
    copy: 'Saved runs appear under Predictions. Open View Result to return to the full report — overview, GICA, Punnett squares, forecast, and complexity.',
    href: '#predictions',
    action: 'Open Predictions',
  },
]

const BIRD_FIELDS = [
  { name: 'Bird ID', copy: 'Required unique label for the record. Used on breeding forms and prediction cards.' },
  { name: 'Species', copy: 'Locks the genetic trait list and the species-compatibility table. Change species and base color / mutations reset.' },
  { name: 'Sex', copy: 'Cock (male, ZZ) or Hen (female, ZW). Required for pairing and for every sex-linked locus.' },
  { name: 'Age', copy: 'Months of age. Used by breeding-safety rules, not by Mendelian math.' },
  { name: 'Base Color', copy: 'Documented ground color. RBGIA will not invent a wild-type filler if this is missing.' },
  { name: 'Visual Mutation', copy: 'Traits the bird actually shows. These enter phenotype mapping and mutation compatibility.' },
  { name: 'Split / Hidden Genes', copy: 'Recessive or sex-linked genes carried without a visual. They still shape offspring odds.' },
  { name: 'Grandparents', copy: 'Optional pedigree. Helps flag documented close relationships. Never required to run a pairing.' },
]

const REPORT_TABS = [
  { name: 'Overview', copy: 'Pair headline, GICA score and label, and the short recommendation.' },
  { name: 'Compatibility', copy: 'Species pairing status — same species, documented hybrid, limited, or unsupported.' },
  { name: 'GICA', copy: 'The 100-point index, six factor scores, positives, warnings, and the weighted formula.' },
  { name: 'Genetic Distribution', copy: 'Theoretical offspring odds for sex, base color, visuals, and split genes from RBGIA.' },
  { name: 'Reproductive Forecast', copy: 'A separate clutch simulation. Living chicks sample the unchanged RBGIA distribution. Not a guaranteed nest.' },
  { name: 'Inheritance Report', copy: 'Allele encoding, Punnett squares, gametes, and the computation trace for this pair.' },
  { name: 'Time / Space Complexity', copy: 'How much work this result actually did — Punnett cells, joint rows, GICA factors, simulated eggs.' },
]

const BLOCKS = [
  {
    q: 'Start Analysis stays blocked',
    a: 'Both parents need species and sex. A cock and a hen are required. Unsupported species pairings, or validation errors such as an invalid mutation combination, will stop the run until the records are corrected.',
  },
  {
    q: 'The result says insufficient data',
    a: 'RBGIA only uses stored genetic codes. If base color or loci are missing, confidence drops to insufficient or partially determined. Fill the documented genotypes — AGAPORA will not invent wild-type alleles to complete the chart.',
  },
  {
    q: 'GICA is low even though the pair looks beautiful',
    a: 'GICA is not a beauty score. Species mismatch, shared recessives, thin inheritance data, or breeding-safety flags all pull the index down. Read the six factors and the why — positives and warnings — instead of the plumage.',
  },
  {
    q: 'Clutch cards do not match the percentages',
    a: 'Egg and chick cards are an illustrative simulation layer. They sample living chicks from the RBGIA distribution; they are not a promised clutch sequence. The Genetic Distribution tab is the theoretical source of truth.',
  },
  {
    q: 'Where did my last run go?',
    a: 'Completed analyses are stored under Predictions. Open View Result on a card to return to the full computation. If nothing is listed, the last Start Analysis did not finish or was not saved.',
  },
]

const TERMS = [
  { term: 'RBGIA', def: 'Rule-Based Genetic Inheritance Analysis. Calculates theoretical offspring from stored parental genotypes.' },
  { term: 'GICA', def: 'Genetic Inheritance Compatibility Analysis. Scores the pair on 100 points without rewriting RBGIA math.' },
  { term: 'Cock / Hen', def: 'Male (ZZ) and female (ZW). Sex-linked genes are read on the Z chromosome.' },
  { term: 'Visual', def: 'A mutation expressed in the bird you can see.' },
  { term: 'Split', def: 'A hidden gene the bird carries without showing the trait.' },
  { term: 'Same-species', def: 'Both parents share a documented species ID. Highest species-compatibility score.' },
]

export default function Help() {
  useEffect(() => {
    const previous = document.title
    document.title = 'Help — AGAPORA'
    return () => {
      document.title = previous
    }
  }, [])

  useEffect(() => {
    const id = window.location.hash.replace(/^#/, '')
    if (!id || id === 'help') return undefined
    const frame = window.requestAnimationFrame(() => {
      document.getElementById(id)?.scrollIntoView({ block: 'start' })
    })
    return () => window.cancelAnimationFrame(frame)
  }, [])

  return (
    <main id="help" className="help">
      <section className="help-hero" aria-labelledby="help-heading">
        <div className="help-hero__media" aria-hidden="true" />
        <div className="help-hero__inner">
          <p className="help-kicker">Field manual</p>
          <h1 id="help-heading" className="help-hero__title">
            How to run AGAPORA
            <em> without losing the plot.</em>
          </h1>
          <p className="help-hero__lede">
            This is the working handbook: record the flock, compose a pair, read the
            report, and know what the engine will refuse to invent. For the method
            itself, see <a href="#about">About</a>.
          </p>
        </div>
      </section>

      <nav className="help-toc" aria-label="Help contents">
        <div className="help-toc__inner">
          <p className="help-kicker">Contents</p>
          <ol className="help-toc__list">
            {CONTENTS.map((item) => (
              <li key={item.id}>
                <a href={`#${item.id}`}>
                  <span className="help-toc__n">{item.n}</span>
                  <span className="help-toc__label">{item.label}</span>
                  <span className="help-toc__hint">{item.hint}</span>
                </a>
              </li>
            ))}
          </ol>
        </div>
      </nav>

      <section id="help-start" className="help-block" aria-labelledby="help-start-heading">
        <div className="help-block__inner">
          <p className="help-kicker">01 · First pairing</p>
          <h2 id="help-start-heading" className="help-title">
            Four moves from empty ledger to a saved result.
          </h2>
          <ol className="help-steps">
            {START_STEPS.map((step) => (
              <li key={step.n} className="help-step">
                <span className="help-step__n">{step.n}</span>
                <div>
                  <h3>{step.title}</h3>
                  <p>{step.copy}</p>
                  {step.href ? (
                    <a className="help-inline" href={step.href}>
                      {step.action} →
                    </a>
                  ) : null}
                </div>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <section id="help-birds" className="help-block help-block--tint" aria-labelledby="help-birds-heading">
        <div className="help-block__inner">
          <p className="help-kicker">02 · Birds</p>
          <h2 id="help-birds-heading" className="help-title">
            The ledger is the source of truth.
          </h2>
          <p className="help-lede">
            Every later calculation reads stored records. Add Bird opens the form.
            Search and filters find a bird by ID, species, or color. Select Bird from
            My Birds on the breeding screen copies the saved profile into a parent slot.
          </p>
          <ul className="help-points">
            <li>Use a Bird ID you will recognise on prediction cards later.</li>
            <li>Pick species first — trait lists depend on it.</li>
            <li>Leave a field empty rather than guessing a genotype. Empty is honest; invented wild-type is not.</li>
          </ul>
          <a className="help-inline" href="#birds">
            Go to Birds →
          </a>
        </div>
      </section>

      <section id="help-fields" className="help-block" aria-labelledby="help-fields-heading">
        <div className="help-block__inner">
          <p className="help-kicker">03 · Parent fields</p>
          <h2 id="help-fields-heading" className="help-title">
            What each breeding input is for.
          </h2>
          <p className="help-lede">
            The ⓘ marks on Start Breeding open the same notes. Parent 1 and Parent 2
            use the identical field set.
          </p>
          <dl className="help-defs">
            {BIRD_FIELDS.map((field) => (
              <div key={field.name} className="help-def">
                <dt>{field.name}</dt>
                <dd>{field.copy}</dd>
              </div>
            ))}
          </dl>
        </div>
      </section>

      <section id="help-report" className="help-block help-block--dark" aria-labelledby="help-report-heading">
        <div className="help-block__inner">
          <p className="help-kicker help-kicker--on-dark">04 · The report</p>
          <h2 id="help-report-heading" className="help-title help-title--on-dark">
            How to read a computation.
          </h2>
          <p className="help-lede help-lede--on-dark">
            After Start Analysis, the result is a tabbed ledger. GICA decides the
            pair. RBGIA explains the offspring. Forecast never edits either.
          </p>
          <ol className="help-tabs">
            {REPORT_TABS.map((tab, index) => (
              <li key={tab.name}>
                <span>{String(index + 1).padStart(2, '0')}</span>
                <div>
                  <h3>{tab.name}</h3>
                  <p>{tab.copy}</p>
                </div>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <section id="help-stuck" className="help-block" aria-labelledby="help-stuck-heading">
        <div className="help-block__inner">
          <p className="help-kicker">05 · When it blocks</p>
          <h2 id="help-stuck-heading" className="help-title">
            The engine would rather stop than guess.
          </h2>
          <div className="help-faq">
            {BLOCKS.map((item) => (
              <details key={item.q} className="help-faq__item">
                <summary>{item.q}</summary>
                <p>{item.a}</p>
              </details>
            ))}
          </div>
        </div>
      </section>

      <section id="help-terms" className="help-block help-block--tint" aria-labelledby="help-terms-heading">
        <div className="help-block__inner">
          <p className="help-kicker">06 · Terms</p>
          <h2 id="help-terms-heading" className="help-title">
            A short glossary for the aviary.
          </h2>
          <dl className="help-glossary">
            {TERMS.map((item) => (
              <div key={item.term}>
                <dt>{item.term}</dt>
                <dd>{item.def}</dd>
              </div>
            ))}
          </dl>
        </div>
      </section>

      <section className="help-close" aria-labelledby="help-close-heading">
        <div className="help-close__inner">
          <p className="help-kicker help-kicker--on-dark">Ready</p>
          <h2 id="help-close-heading" className="help-title help-title--on-dark">
            Open the ledger,
            <span> then run the pair.</span>
          </h2>
          <p className="help-close__copy">
            Keep records in Birds, or go straight to Start Breeding if both parents
            are already known. About explains why the two engines stay separate.
          </p>
          <div className="help-close__actions">
            <a className="help-btn help-btn--primary" href="#breeding">
              Start Breeding
            </a>
            <a className="help-btn help-btn--ghost" href="#about">
              Read About
            </a>
          </div>
        </div>
      </section>
    </main>
  )
}
