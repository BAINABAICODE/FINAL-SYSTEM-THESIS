import { useEffect } from 'react'
import agaporaIcon from '../../assets/icons/AGAPORA-icon.png'
import './About.css'

const ENGINES = [
  {
    code: '01',
    name: 'RBGIA',
    full: 'Rule-Based Genetic Inheritance Analysis',
    lead: 'The inheritance engine.',
    copy: 'RBGIA reads stored parental genotypes — never invented wild-type fillers — then walks allele encoding, inheritance-mode detection, gamete formation, Punnett combination, and phenotype mapping. The result is a theoretical offspring distribution: base color, visual mutations, split genes, and sex.',
    points: [
      'Dataset-driven loci from documented genetic codes',
      'Autosomal, incomplete-dominant, and Z-linked modes',
      'Probabilities stay theoretical until a clutch is actually laid',
    ],
  },
  {
    code: '02',
    name: 'GICA',
    full: 'Genetic Inheritance Compatibility Analysis',
    lead: 'The pairing verdict.',
    copy: 'GICA scores a pair on a 100-point index using six documented factors. It uses RBGIA outcomes as evidence; it never rewrites them. Compatibility, risk, diversity, and breeding constraints remain a separate layer from inheritance math.',
    points: [
      'Six weighted factors, classified Excellent through Not Recommended',
      'Species pairing from AGAPORA breeding records, not guesswork',
      'Reproductive forecast and clutch cards sit on top, not inside, the genetics',
    ],
  },
]

const GICA_FACTORS = [
  { weight: '30', name: 'Species Compatibility', detail: 'Same-species, documented hybrid, limited, or unsupported pairings from breeding records.' },
  { weight: '20', name: 'Inheritance Information', detail: 'How complete the stored parental genotypes and calculable RBGIA loci actually are.' },
  { weight: '15', name: 'Mutation Compatibility', detail: 'Whether visual and split mutations resolve with documented inheritance types.' },
  { weight: '15', name: 'Genetic Risk', detail: 'Undesirable combinations, shared recessive pathways, and species-level risk signals.' },
  { weight: '10', name: 'Genetic Diversity', detail: 'Allelic difference at documented loci — not color names treated as diversity.' },
  { weight: '10', name: 'Breeding Constraints', detail: 'Pair validity, age and safety notices, and fertility documentation.' },
]

const STEPS = [
  { n: '01', title: 'Record the flock', copy: 'Catalogue each bird with species, sex, age, base color, visual mutations, splits, and optional pedigree.' },
  { n: '02', title: 'Compose a pair', copy: 'Select a cock and hen. AGAPORA checks species compatibility and breeding-safety rules before prediction runs.' },
  { n: '03', title: 'Trace inheritance', copy: 'RBGIA encodes alleles, builds gametes, and publishes theoretical offspring outcomes with a computation trace.' },
  { n: '04', title: 'Score compatibility', copy: 'GICA returns a 100-point index, a classification, and a why — positives, warnings, and a recommendation.' },
  { n: '05', title: 'Forecast the clutch', copy: 'A separate simulation layer samples living chicks from the unchanged RBGIA distribution. It is illustrative, not a lottery of guaranteed chicks.' },
  { n: '06', title: 'Keep the ledger', copy: 'Save the prediction. Reopen the full computation result anytime — formulas, Punnett squares, and outcome cards included.' },
]

const PRINCIPLES = [
  {
    title: 'Stored codes only',
    copy: 'Missing genotypes are marked insufficient. AGAPORA will not invent a wild-type allele to keep a chart looking complete.',
  },
  {
    title: 'Layers stay separate',
    copy: 'RBGIA calculates inheritance. GICA scores the pair. Clutch simulation illustrates. None of the three is allowed to rewrite the others.',
  },
  {
    title: 'Documented pairings',
    copy: 'Species compatibility comes from lovebird records and breeding-compatibility tables — same species, hybrid, limited, or unsupported.',
  },
  {
    title: 'Theory, not guarantee',
    copy: 'Offspring cards are expected theoretical distributions. Fertility, viability, and the nest itself remain biology, not software.',
  },
]

const SPECIES = [
  'Agapornis roseicollis',
  'Agapornis personatus',
  'Agapornis fischeri',
  'Agapornis lilianae',
  'Agapornis nigrigenis',
  'Agapornis taranta',
  'Agapornis pullarius',
  'Agapornis canus',
  'Agapornis swindernianus',
]

export default function About() {
  useEffect(() => {
    const previous = document.title
    document.title = 'About — AGAPORA'
    return () => {
      document.title = previous
    }
  }, [])

  return (
    <main id="about" className="about">
      <section className="about-hero" aria-labelledby="about-heading">
        <div className="about-hero__media" aria-hidden="true" />
        <div className="about-hero__inner">
          <p className="about-kicker">The aviary ledger</p>
          <h1 id="about-heading" className="about-hero__title">
            Science for the pair,
            <em> not a guess for the nest.</em>
          </h1>
          <p className="about-hero__lede">
            AGAPORA is a rule-based genetic platform for lovebird breeders. Named for{' '}
            <i>Agapornis</i>, it replaces folklore pairing with two engines that stay honest
            about what the records actually contain: RBGIA for inheritance, GICA for compatibility.
          </p>
          <div className="about-hero__meta" aria-label="System method">
            <img className="about-hero__mark" src={agaporaIcon} alt="" width={72} height={72} />
            <div>
              <p className="about-hero__method">AGAPORA-RBGIA-GICA-v3</p>
              <p className="about-hero__method-note">Thesis method · dataset-driven · never invents alleles</p>
            </div>
          </div>
        </div>
      </section>

      <section className="about-mission" aria-labelledby="about-mission-heading">
        <div className="about-mission__inner">
          <p className="about-kicker">Why it exists</p>
          <h2 id="about-mission-heading" className="about-section-title">
            Pairing should be a calculation you can inspect.
          </h2>
          <div className="about-mission__grid">
            <blockquote className="about-quote">
              <p>
                “By replacing guesswork with science, AGAPORA keeps the breeding process
                reliable, consistent, and accountable to the genotypes you actually recorded.”
              </p>
            </blockquote>
            <div className="about-mission__copy">
              <p>
                Lovebird colour genetics are unforgiving: sex-linked (ZZ / ZW) loci, incomplete
                dominants, hidden splits, and species that should never be mixed. A beautiful
                pair on the perch is not the same as a compatible pair on paper.
              </p>
              <p>
                AGAPORA takes the flock you already keep — species, sex, base color, visuals,
                splits, pedigree — and runs a documented pipeline. Every score, Punnett cell,
                and outcome card can be opened, traced, and saved.
              </p>
            </div>
          </div>
        </div>
      </section>

      <section className="about-engines" aria-labelledby="about-engines-heading">
        <div className="about-engines__inner">
          <div className="about-engines__intro">
            <p className="about-kicker about-kicker--on-dark">Twin engines</p>
            <h2 id="about-engines-heading" className="about-section-title about-section-title--on-dark">
              Inheritance calculates.
              <span> Compatibility decides.</span>
            </h2>
            <p className="about-engines__lede">
              Two systems, one report. RBGIA never scores the pair. GICA never edits the
              Punnett math. That separation is the whole point of the method.
            </p>
          </div>

          <div className="about-engines__cards">
            {ENGINES.map((engine) => (
              <article key={engine.name} className={`about-engine about-engine--${engine.name.toLowerCase()}`}>
                <header className="about-engine__head">
                  <span className="about-engine__code">{engine.code}</span>
                  <h3 className="about-engine__name">{engine.name}</h3>
                </header>
                <p className="about-engine__full">{engine.full}</p>
                <p className="about-engine__lead">{engine.lead}</p>
                <p className="about-engine__copy">{engine.copy}</p>
                <ul className="about-engine__points">
                  {engine.points.map((point) => (
                    <li key={point}>{point}</li>
                  ))}
                </ul>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="about-factors" aria-labelledby="about-factors-heading">
        <div className="about-factors__inner">
          <div className="about-factors__intro">
            <p className="about-kicker">GICA · 100 points</p>
            <h2 id="about-factors-heading" className="about-section-title">
              Six factors. One index.
            </h2>
            <p>
              Classification runs Excellent (90–100), Good (75–89), Fair (60–74),
              Poor (40–59), and Not Recommended (0–39). Weights are published so the
              verdict can be audited, not just accepted.
            </p>
          </div>
          <ol className="about-factors__list">
            {GICA_FACTORS.map((factor) => (
              <li key={factor.name}>
                <span className="about-factors__weight">{factor.weight}</span>
                <div>
                  <h3>{factor.name}</h3>
                  <p>{factor.detail}</p>
                </div>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <section className="about-path" aria-labelledby="about-path-heading">
        <div className="about-path__inner">
          <p className="about-kicker">From perch to report</p>
          <h2 id="about-path-heading" className="about-section-title">
            How a pairing moves through AGAPORA
          </h2>
          <ol className="about-path__list">
            {STEPS.map((step) => (
              <li key={step.n} className="about-path__step">
                <span className="about-path__n">{step.n}</span>
                <h3>{step.title}</h3>
                <p>{step.copy}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <section className="about-principles" aria-labelledby="about-principles-heading">
        <div className="about-principles__inner">
          <p className="about-kicker">Working rules</p>
          <h2 id="about-principles-heading" className="about-section-title">
            What the engine refuses to pretend.
          </h2>
          <div className="about-principles__grid">
            {PRINCIPLES.map((item) => (
              <article key={item.title} className="about-principle">
                <h3>{item.title}</h3>
                <p>{item.copy}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="about-species" aria-labelledby="about-species-heading">
        <div className="about-species__inner">
          <p className="about-kicker">The genus</p>
          <h2 id="about-species-heading" className="about-section-title">
            Built around <i>Agapornis</i>
          </h2>
          <p className="about-species__lede">
            Nine lovebird species sit in the catalogue — each with documented forms,
            genetic traits, and breeding-compatibility records. Pairings are judged
            against those records, not against a generic “parrot” model.
          </p>
          <ul className="about-species__list">
            {SPECIES.map((name) => (
              <li key={name}>
                <i>{name}</i>
              </li>
            ))}
          </ul>
        </div>
      </section>

      <section className="about-close" aria-labelledby="about-close-heading">
        <div className="about-close__inner">
          <p className="about-kicker about-kicker--on-dark">Begin with the flock</p>
          <h2 id="about-close-heading" className="about-section-title about-section-title--on-dark">
            Record two parents.
            <span> Read the inheritance.</span>
          </h2>
          <p className="about-close__copy">
            Start in Birds to keep the ledger, or go straight to Start Breeding when
            the pair is already known. Predictions keep every run you save.
          </p>
          <div className="about-close__actions">
            <a className="about-btn about-btn--primary" href="#breeding">
              Start Breeding
            </a>
            <a className="about-btn about-btn--ghost" href="#birds">
              View birds
            </a>
          </div>
        </div>
      </section>
    </main>
  )
}
