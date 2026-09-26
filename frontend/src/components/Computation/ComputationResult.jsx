import { forwardRef, useCallback, useEffect, useMemo, useRef, useState } from 'react'
import api from '../../api/client'
import { generateAllChickImages, generateChickImage } from '../../services/huggingFaceService'
import { speciesImages } from '../../assets/birds'
import PanelistProcessBrief from './PanelistProcessBrief'
import ComplexitySection from './complexity/ComplexitySection'
import CompatibilitySummary from './compatibility/CompatibilitySummary'
import WeightedFormula from './compatibility/WeightedFormula'
import ScoreBreakdown from './compatibility/ScoreBreakdown'
import SpeciesCompatibilityPanel from './compatibility/SpeciesCompatibilityPanel'
import ParentGeneticProfile from './compatibility/ParentGeneticProfile'
import RbgiaPipeline from './inheritance/RbgiaPipeline'
import RbgiaSummary from './inheritance/RbgiaSummary'
import AlleleTranslation from './inheritance/AlleleTranslation'
import PunnettSquare from './inheritance/PunnettSquare'
import PhenotypeMap from './inheritance/PhenotypeMap'
import PairInheritanceBoard from './inheritance/PairInheritanceBoard'
import ClutchSimulationPanel from './outcomes/ClutchSimulationPanel'
import { eggStatusShort, eggStatusTone, isLivingEgg } from './outcomes/eggStatus'
import { StatusPill } from './shared/primitives'
import { attachHeadToTail, buildCompatibilityModel, buildComplexityReport, buildRbgiaTrace, translateGenotype } from '../../services/genetics'
import '../Breeding/BreedingWorkflow.css'
import './CompatibilityModule.css'
import './ComputationResult.css'

const RESULT_SECTIONS = [
  {
    id: 'compatibility',
    n: '01',
    group: 'input',
    label: 'Encoded pair',
    hint: 'What the engines read',
    proves: 'Why this is first: RBGIA and GICA only read stored phenotype, genotype, visual mutations, and split genes. Missing genotypes stay missing. Looks are not scored.',
  },
  {
    id: 'flow',
    n: '02',
    group: 'rbgia',
    label: 'Algorithm',
    hint: 'Why, then how',
    proves: 'Why the engines are separate: RBGIA computes inheritance. GICA scores that evidence. The clutch forecast uses the score and never rewrites the Punnett squares.',
  },
  {
    id: 'inheritance',
    n: '03',
    group: 'rbgia',
    label: 'RBGIA computation',
    hint: 'Alleles → phenotype',
    proves: 'How inheritance is computed: translate alleles, form gametes, fill every Punnett box, then map genotype to phenotype. Same parents always give the same boxes.',
  },
  {
    id: 'distribution',
    n: '04',
    group: 'rbgia',
    label: 'Offspring odds',
    hint: 'Genotype and phenotype',
    proves: 'RBGIA output for this pair: sex, base color, visual mutations, split genes, and the joint genotype and phenotype distribution.',
  },
  {
    id: 'gica',
    n: '05',
    group: 'gica',
    label: 'Weight scoring',
    hint: 'GICA 0–100',
    proves: 'How the score is weighted: desirable traits, recessive risk, genetic diversity, and mutation load, plus species fit and breeding constraints. Each weight is that factor’s share of 100. GICA does not edit RBGIA fractions.',
  },
  {
    id: 'final-output',
    n: '06',
    group: 'gica',
    label: 'Score and why',
    hint: 'The decision',
    proves: 'The numerical GICA score, the points behind it, and the breeding recommendation those weights produced.',
  },
  {
    id: 'forecast',
    n: '07',
    group: 'output',
    label: 'Clutch and hatch',
    hint: 'Eggs, then chicks',
    proves: 'Forecasted clutch stays inside the species range and is adjusted by the compatibility score, diversity, and genetic load. Expected hatchlings apply the hatch rate. These are estimates.',
  },
  {
    id: 'complexity',
    n: '08',
    group: 'output',
    label: 'Time and space',
    hint: 'Work this run did',
    proves: 'Time grows with offspring outcomes times traits. Space grows with the trait map. The counts are from this stored run.',
  },
]

const RESULT_GROUPS = [
  { id: 'input', chapter: 'I', label: 'Input', purpose: 'Encode the stored pair' },
  { id: 'rbgia', chapter: 'II', label: 'RBGIA', purpose: 'Compute inheritance' },
  { id: 'gica', chapter: 'III', label: 'GICA', purpose: 'Weight the pair 0–100' },
  { id: 'output', chapter: 'IV', label: 'Output', purpose: 'Score, clutch, and work' },
]

function getSection(id) {
  return RESULT_SECTIONS.find((item) => item.id === id)
}

function resolveSection(id) {
  if (!id) return null
  if (id === 'time-complexity' || id === 'space-complexity') return 'complexity'
  return getSection(id) ? id : null
}

function sectionHash(resultId, sectionId) {
  if (!resultId) return '#predictions'
  const valid = resolveSection(sectionId) || RESULT_SECTIONS[0].id
  return `#computation/${resultId}/${valid}`
}

function displayText(value) {
  if (value == null || value === '') return '—'
  if (typeof value === 'string' || typeof value === 'number') return String(value)
  if (typeof value === 'object') return value.display || value.description || value.genotype || '—'
  return '—'
}

function listOrDash(items) {
  if (!items?.length) return 'None calculated'
  const names = items.map((item) => (typeof item === 'string' ? item : item?.name)).filter(Boolean)
  return names.length ? names.join(', ') : 'None calculated'
}

function speciesLabel(species) {
  if (!species) return '—'
  if (typeof species === 'string') return species
  return species.common_name || species.scientific_name || species.name || '—'
}

/** Visualize engine probability/fraction as a display percent. Does not recalculate genetics. */
function toPercent(value) {
  if (typeof value === 'number' && Number.isFinite(value)) {
    if (value >= 0 && value <= 1) return Math.round(value * 1000) / 10
    return Math.round(value * 10) / 10
  }
  if (typeof value === 'string') {
    const fraction = value.match(/^(\d+)\s*\/\s*(\d+)$/)
    if (fraction) {
      const num = Number(fraction[1])
      const den = Number(fraction[2])
      if (den > 0) return Math.round((num / den) * 1000) / 10
    }
    const pct = value.match(/([\d.]+)\s*%/)
    if (pct) return Number(pct[1])
  }
  if (value && typeof value === 'object') {
    if (typeof value.probability === 'number') return toPercent(value.probability)
    if (value.fraction) return toPercent(value.fraction)
  }
  return null
}

function formatPercent(value) {
  const pct = toPercent(value)
  if (pct == null) return null
  return `${pct}%`
}

function gicaStatusLabel(label, score) {
  if (label) return String(label).trim()
  if (score == null) return 'Not documented'
  if (score >= 90) return 'Excellent'
  if (score >= 75) return 'Good'
  if (score >= 60) return 'Fair'
  if (score >= 40) return 'Poor'
  return 'Not Recommended'
}

function sexLabel(sex, sexLabelText) {
  if (sexLabelText) return sexLabelText
  const value = String(sex || '').toLowerCase()
  if (value === 'cock' || value === 'male') return 'Male / Cock'
  if (value === 'hen' || value === 'female') return 'Female / Hen'
  if (value === 'both') return 'Both / Unspecified'
  return sex || '—'
}

function classifyInheritance(type) {
  const value = String(type || '').toLowerCase()
  if (!value) return null
  if (value.includes('sex-linked')) return 'sex-linked'
  if (value.includes('dominant') && !value.includes('recessive')) return 'dominant'
  if (value.includes('incomplete') || value.includes('intermediate') || value.includes('partial')) return 'dominant'
  if (value.includes('recessive')) return 'recessive'
  return null
}

function chickTraitLabel(row) {
  const visuals = (row.visual_mutations || []).filter(Boolean)
  const splits = (row.split_hidden_genes || row.split_genes || []).filter(Boolean)
  const parts = [
    row.base_color,
    visuals.length ? visuals.join(' + ') : null,
    splits.length ? `Split ${splits.join(' + ')}` : null,
  ].filter(Boolean)
  if (parts.length) return parts.join(' · ')
  return row.phenotype || row.trait_name || row.genotype || 'Predicted outcome'
}

function outcomeFlags(row) {
  const flags = { dominant: false, 'sex-linked': false, recessive: false }
  const classes = row.inheritance_classes || null
  if (classes) {
    if (classes.dominant) flags.dominant = true
    if (classes.sex_linked || classes['sex-linked']) flags['sex-linked'] = true
    if (classes.recessive) flags.recessive = true
    return flags
  }
  const loci = row.loci || []
  if (loci.length) {
    loci.forEach((locus) => {
      const kind = classifyInheritance(locus.inheritance_type)
      if (kind) flags[kind] = true
    })
  }
  const direct = classifyInheritance(row.inheritance_type)
  if (direct) flags[direct] = true
  if ((row.split_hidden_genes || []).length) flags.recessive = true
  return flags
}

function buildTraitSummary(rows) {
  return rows
    .slice(0, 8)
    .map((row) => {
      const pct = formatPercent(row)
      return `${chickTraitLabel(row)}${pct ? ` (${pct})` : ''}`
    })
    .join(' · ')
}

function downloadBreedingReport({ pair, species, gica, gicaScore, eggs, probabilities, recommendation }) {
  const score = gicaScore ?? pair?.score ?? null
  const label = pair?.label || gicaStatusLabel(gica.label, score)
  const lines = [
    'AGAPORA — Genetic Pair Compatibility Report',
    'Thesis: An Algorithmic Analysis of Lovebird Pair Compatibility Using Rule-Based Genetic Inheritance.',
    '',
    `GENETIC PAIR COMPATIBILITY: ${score == null ? '—' : `${score} / 100`} · ${label}`,
    `Recommendation: ${recommendation || gica.recommendation || pair?.recommendation || '—'}`,
    '',
    'Parent 1:',
    `  ${(pair?.parent_1 || species.parent_1)?.bird_id || '—'} · ${(pair?.parent_1 || species.parent_1)?.species || '—'} · ${(pair?.parent_1 || species.parent_1)?.sex || '—'}`,
    'Parent 2:',
    `  ${(pair?.parent_2 || species.parent_2)?.bird_id || '—'} · ${(pair?.parent_2 || species.parent_2)?.species || '—'} · ${(pair?.parent_2 || species.parent_2)?.sex || '—'}`,
    '',
    'WHY THIS PAIR RECEIVED THIS SCORE',
    'Positives:',
    ...((pair?.why?.positives || gica.why?.positives || ['—']).map((item) => `  - ${item}`)),
    'Warnings:',
    ...((pair?.why?.warnings || gica.why?.warnings || ['—']).map((item) => `  - ${item}`)),
    '',
    'COMPATIBILITY BREAKDOWN',
    ...((pair?.breakdown || gica.breakdown || []).map((row) => `  ${row.factor}: ${row.points ?? row.contribution ?? '—'} / ${row.max_points ?? '—'} (${row.value ?? '—'})`)),
    '',
    `Genetic Risk: ${pair?.risk?.level || gica.risk?.level || '—'}`,
    `Genetic Diversity: ${pair?.diversity?.level || gica.diversity?.level || '—'}`,
    '',
    'RBGIA supporting inheritance outcomes (theoretical):',
  ]

  const rows = (probabilities.complete_offspring || []).length
    ? probabilities.complete_offspring
    : eggs

  rows.forEach((row, index) => {
    lines.push(
      `${index + 1}. ${chickTraitLabel(row)} | Sex: ${sexLabel(row.sex, row.sex_label)} | Probability: ${formatPercent(row) || '—'}`,
    )
  })

  lines.push('')
  lines.push('GICA evaluates pair compatibility. RBGIA calculates rule-based inheritance. Offspring distributions support the compatibility analysis.')
  lines.push('This report is theoretical and deterministic — not a guaranteed biological clutch sequence.')

  const blob = new Blob([lines.join('\n')], { type: 'text/plain;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `agapora-pair-compatibility-${Date.now()}.txt`
  link.click()
  URL.revokeObjectURL(url)
}

function rowName(row, fallback = 'Outcome') {
  if (row.sex && !row.genotype && !row.trait && !row.phenotype) return sexLabel(row.sex, row.sex_label || row.trait)
  if (row.trait) return row.trait
  return [row.genotype, row.phenotype, row.sex ? sexLabel(row.sex, row.sex_label) : null]
    .filter(Boolean)
    .join(' · ') || fallback
}

function sortByProbability(rows) {
  return [...(rows || [])]
    .filter((row) => toPercent(row) != null || row.fraction || typeof row.probability === 'number')
    .sort((a, b) => (toPercent(b) ?? -1) - (toPercent(a) ?? -1))
}

function parseTraitBadges(items = []) {
  return items.map((item) => {
    const text = String(item)
    const match = text.match(/^(Base color|Visual mutation|Split\/hidden gene):\s*(.+)$/i)
    if (match) return { kind: match[1], value: match[2] }
    return { kind: 'Trait', value: text }
  })
}

function forecastIsDocumented(value) {
  if (value == null || value === '') return false
  return !String(value).toLowerCase().includes('not documented')
}

function ProgressBar({ percent, tone = 'info', label }) {
  const width = Math.max(0, Math.min(100, percent ?? 0))
  return (
    <div className={`compute-bar is-${tone}`} title={label || undefined}>
      <div className="compute-bar__track" aria-hidden="true">
        <div className="compute-bar__fill" style={{ width: `${width}%` }} />
      </div>
      {label ? <span className="compute-bar__label">{label}</span> : null}
    </div>
  )
}

function MetricCard({ label, value, hint, children }) {
  return (
    <article className="compute-metric">
      <p className="compute-metric__label">{label}</p>
      <p className="compute-metric__value">{value}</p>
      {hint ? <p className="compute-metric__hint">{hint}</p> : null}
      {children}
    </article>
  )
}

function ProvesNote({ sectionId }) {
  const item = getSection(sectionId)
  if (!item) return null
  return (
    <p className="compute-proves">
      <span className="compute-proves__label">What this proves</span>
      {item.proves}
    </p>
  )
}

function ResultBlock({ title, children, eyebrow, sectionId }) {
  return (
    <section id={sectionId ? `process-${sectionId}` : undefined} className="compute-result__card compute-section-panel">
      {eyebrow ? <p className="compute-result__eyebrow">{eyebrow}</p> : null}
      <h2>{title}</h2>
      <ProvesNote sectionId={sectionId} />
      {children}
    </section>
  )
}

function unavailableReasonFor(probabilities, category) {
  const hit = (probabilities?.unavailable || []).find((item) => item.category === category)
  return hit?.reason || null
}

function DistributionPanel({ title, rows, nameResolver, unavailableReason }) {
  const sorted = useMemo(() => sortByProbability(rows), [rows])

  if (!sorted.length) {
    return (
      <article className="compute-dist-panel">
        <header className="compute-dist-panel__head">
          <h3>{title}</h3>
        </header>
        <p className="compute-result__empty">
          {unavailableReason
            ? `Calculation unavailable. Reason: ${unavailableReason}`
            : 'Calculation unavailable. Reason: Missing parental genotype or undocumented locus for this category.'}
        </p>
      </article>
    )
  }

  const topPct = toPercent(sorted[0])

  return (
    <article className="compute-dist-panel">
      <header className="compute-dist-panel__head">
        <div>
          <p className="compute-dist-panel__kicker">Highest probability</p>
          <h3>{title}</h3>
        </div>
        <span className="compute-dist-panel__highest">{formatPercent(topPct) || '—'}</span>
      </header>
      <ul className="compute-prob-list">
        {sorted.map((row, index) => {
          const pct = toPercent(row)
          const name = nameResolver ? nameResolver(row) : rowName(row)
          const genotype = row.genotype || row.base_color_genotype || null
          return (
            <li key={`${title}-${name}-${row.fraction || pct}-${index}`} className={`compute-prob-row${index === 0 ? ' is-top' : ''}`}>
              <div className="compute-prob-row__head">
                <span className="compute-prob-row__name">{name}</span>
                <span className="compute-prob-row__pct">{formatPercent(pct)}</span>
              </div>
              {genotype ? <p className="compute-prob-row__genotype">Code: {genotype} · Means: {translateGenotype(genotype, row.locus || row.trait)}</p> : null}
              <ProgressBar percent={pct} tone={index === 0 ? 'high' : 'info'} />
              {row.fraction ? <p className="compute-prob-row__fraction">{row.fraction} of all calculated chicks</p> : null}
            </li>
          )
        })}
      </ul>
    </article>
  )
}

const SECTION_PANEL_ID = 'compute-section-panel'

const SectionNav = forwardRef(function SectionNav({ section, resultId, onChange, summary }, ref) {
  const active = getSection(section)

  return (
    <nav ref={ref} className="compute-outline" aria-label="Algorithm steps">
      <div className="compute-outline__head">
        <p className="compute-outline__kicker">Breeding complete · full process</p>
        <p className="compute-outline__title">Why this result, then how it was computed</p>
        <p className="compute-outline__lede">
          The board above is the full process. These links open the proof for each step: encoded pair, RBGIA computation, offspring odds, GICA weights, the score, the clutch, and the work this run did.
        </p>
      </div>

      {summary ? (
        <dl className="sop-metrics" aria-label="This run">
          <div>
            <dt>Pair</dt>
            <dd>{summary.parents}</dd>
          </div>
          <div>
            <dt>RBGIA</dt>
            <dd>{summary.loci} genes · {summary.outcomes} genotypes</dd>
          </div>
          <div>
            <dt>GICA</dt>
            <dd>{summary.score} · {summary.status}</dd>
          </div>
          <div>
            <dt>Forecast</dt>
            <dd>{summary.eggs} eggs · {summary.hatchlings} hatchlings</dd>
          </div>
        </dl>
      ) : null}

      <ol className="compute-outline__legend">
        <li>
          <strong>I · Input</strong>
          Encode phenotype, genotype, visual mutations, and known splits. Missing genes stay missing.
        </li>
        <li>
          <strong>II · RBGIA</strong>
          Mendelian and sex-linked rules. Alleles, gametes, Punnett squares, then genotype and phenotype odds.
        </li>
        <li>
          <strong>III · GICA</strong>
          Weight desirable traits, recessive risk, diversity, and mutation load into one score from 0 to 100.
        </li>
        <li>
          <strong>IV · Output</strong>
          The score and why, the clutch inside the species range, expected hatchlings, and the work this run did.
        </li>
      </ol>

      <div className="compute-outline__groups">
        {RESULT_GROUPS.map((group) => (
          <div key={group.id} className="compute-outline__group">
            <p className="compute-outline__group-index">Chapter {group.chapter}</p>
            <p className="compute-outline__group-label">{group.label}</p>
            <p className="compute-outline__group-purpose">{group.purpose}</p>
            <div className="compute-outline__tabs">
              {RESULT_SECTIONS.filter((item) => item.group === group.id).map((item) => {
                const selected = section === item.id
                return (
                  <a
                    key={item.id}
                    id={`compute-tab-${item.id}`}
                    href={sectionHash(resultId, item.id)}
                    aria-current={selected ? 'page' : undefined}
                    aria-controls={SECTION_PANEL_ID}
                    className={`compute-outline__btn${selected ? ' is-active' : ''}`}
                    onClick={(event) => onChange(item.id, event)}
                  >
                    <span className="compute-outline__n">{item.n}</span>
                    <span className="compute-outline__copy">
                      <span className="compute-outline__label">{item.label}</span>
                      <span className="compute-outline__hint">{item.hint}</span>
                    </span>
                  </a>
                )
              })}
            </div>
          </div>
        ))}
      </div>

      {active ? (
        <p className="compute-outline__proves">
          <span>Now proving · {active.n} {active.label}</span>
          {active.proves}
        </p>
      ) : null}
    </nav>
  )
})

/** Tracks the fixed site header height so section anchors sit below it. */
function useSiteHeaderHeight() {
  const [height, setHeight] = useState(0)
  useEffect(() => {
    const header = document.querySelector('.navbar')
    if (!header) return undefined
    const update = () => setHeight(Math.round(header.getBoundingClientRect().height))
    update()
    if (typeof ResizeObserver === 'undefined') return undefined
    const observer = new ResizeObserver(update)
    observer.observe(header)
    return () => observer.disconnect()
  }, [])
  return height
}

/** Presentation-only split of a GICA factor string into title + detail. Text is unchanged. */
function splitFactor(text) {
  const raw = String(text || '').trim()
  const colon = raw.match(/^([^:]{3,60}):\s+(.+)$/)
  if (colon) return { title: colon[1].trim(), detail: colon[2].trim() }
  const paren = raw.match(/^(.+?)\s\(([^()]+)\)$/)
  if (paren) return { title: paren[1].trim(), detail: paren[2].trim() }
  return { title: raw, detail: '' }
}

function evidenceValue(value) {
  if (value == null || value === '') return '—'
  const text = String(value)
  if (/^[A-Z_]+$/.test(text)) {
    return text.toLowerCase().replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
  }
  return text
}

function locusLabel(value) {
  if (value == null || value === '') return '—'
  const text = String(value)
  if (/^[a-z_]+$/.test(text)) {
    return text.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
  }
  return evidenceValue(text)
}

function speciesBreakdownValue(breakdown) {
  const row = (breakdown || []).find((item) => item.key === 'species_compatibility')
  return row?.value || null
}

function gicaTone(score) {
  if (score == null) return 'muted'
  if (score >= 90) return 'full'
  if (score >= 75) return 'high'
  if (score >= 60) return 'moderate'
  if (score >= 40) return 'low'
  return 'poor'
}

function CompatibilityScoreCard({ score, status, summary }) {
  const pct = score == null ? null : Math.max(0, Math.min(100, Math.round(score)))
  const tone = gicaTone(score)
  return (
    <article className={`thesis-score-card is-${tone}`} aria-labelledby="thesis-score-title">
      <p className="compute-result__eyebrow" id="thesis-score-title">Compatibility score</p>
      <div className="thesis-score-card__figure">
        <p className="thesis-score-card__score">
          <strong>{pct == null ? '—' : pct}</strong>
          <span>/ 100</span>
        </p>
        <p className="thesis-score-card__percent">{pct == null ? 'Not documented' : `${pct}%`}</p>
      </div>
      <div
        className={`thesis-score-bar is-${tone}`}
        role="progressbar"
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={pct ?? 0}
        aria-valuetext={pct == null ? 'Score unavailable' : `${pct} percent, ${status}`}
        aria-label="Genetic pair compatibility score"
      >
        <div className="thesis-score-bar__fill" style={{ width: `${pct ?? 0}%` }} />
      </div>
      <p className="thesis-score-card__rating">{status}</p>
      {summary ? <p className="thesis-score-card__summary">{summary}</p> : null}
    </article>
  )
}

function resultSpeciesId(result, presentation = {}) {
  const parents = [
    result?.parent_snapshot?.parent_1,
    result?.parent_snapshot?.parent_2,
    presentation?.pair_compatibility?.parent_1,
    presentation?.pair_compatibility?.parent_2,
  ]
  for (const parent of parents) {
    const id = parent?.species?.id ?? parent?.species_id
    if (id != null) return id
  }
  return null
}

function parentSpeciesImage(parent) {
  const id = parent?.species?.id ?? parent?.species_id
  return id != null ? speciesImages[id] || null : null
}

function ParentBirdColumn({ label, parent, fallback }) {
  const species = parent?.species?.common_name || parent?.species || fallback?.species || '—'
  const scientific = parent?.species?.scientific_name || fallback?.scientific_name || ''
  const sex = parent?.sex_label || sexLabel(parent?.sex, fallback?.sex)
  const birdId = parent?.bird_id || fallback?.bird_id || '—'
  const image = parentSpeciesImage(parent)
  const alt = `${species}${scientific ? ` (${scientific})` : ''} — ${sex}, ${label}`

  return (
    <div className="thesis-parent">
      <p className="thesis-parent__label">{label}</p>
      <div className="thesis-parent__media">
        {image ? (
          <img src={image} alt={alt} loading="lazy" />
        ) : (
          <div className="thesis-parent__placeholder" role="img" aria-label={`${species} image unavailable`}>
            <span>{species !== '—' ? species.charAt(0) : '?'}</span>
          </div>
        )}
      </div>
      <p className="thesis-parent__id">{birdId}</p>
      <dl className="thesis-parent__facts">
        <div>
          <dt>Species</dt>
          <dd>{species}{scientific ? <em>{scientific}</em> : null}</dd>
        </div>
        <div><dt>Sex</dt><dd>{sex}</dd></div>
        <div><dt>Base Color</dt><dd>{parent?.base_color?.name || displayText(parent?.base_color)}</dd></div>
        <div><dt>Visual Mutation</dt><dd>{listOrDash(parent?.visual_mutations)}</dd></div>
        <div><dt>Split / Hidden Genes</dt><dd>{listOrDash(parent?.split_genes)}</dd></div>
      </dl>
    </div>
  )
}

function ParentBirdsCard({ parentSnapshot, parent1Fallback, parent2Fallback }) {
  return (
    <article className="thesis-parents-card" aria-labelledby="thesis-parents-title">
      <p className="compute-result__eyebrow" id="thesis-parents-title">Parent birds</p>
      <div className="thesis-parents-grid">
        <ParentBirdColumn label="Parent 1" parent={parentSnapshot?.parent_1} fallback={parent1Fallback} />
        <div className="thesis-parents-x" aria-hidden="true">×</div>
        <ParentBirdColumn label="Parent 2" parent={parentSnapshot?.parent_2} fallback={parent2Fallback} />
      </div>
    </article>
  )
}

function InheritanceTag({ kind }) {
  const labels = {
    dominant: 'Dominant',
    'sex-linked': 'Sex-Linked',
    recessive: 'Recessive',
  }
  return <span className={`thesis-tag is-${kind}`}>{labels[kind] || kind}</span>
}

function FinalSystemOutputSection({
  pair,
  species,
  gica,
  gicaScore,
  probabilities,
  eggs,
  confidence,
  parentSnapshot,
}) {
  const score = pair?.score ?? gicaScore ?? null
  const status = gicaStatusLabel(pair?.label || gica.label, score)
  const recommendation = pair?.recommendation || gica.recommendation || '—'
  const why = pair?.why || gica.why || { positives: [], warnings: [] }
  const breakdown = pair?.breakdown || gica.breakdown || []
  const risk = pair?.risk || gica.risk || null
  const diversity = pair?.diversity || gica.diversity || null
  const mutationCompat = pair?.mutation_compatibility || gica.mutation_compatibility || null
  const finalAnalysis = pair?.final_analysis || {}
  const parent1 = pair?.parent_1 || species.parent_1 || {}
  const parent2 = pair?.parent_2 || species.parent_2 || {}

  const predictedRows = useMemo(() => {
    const source = (probabilities.complete_offspring || []).length
      ? probabilities.complete_offspring
      : eggs
    return sortByProbability(source).slice(0, 12)
  }, [probabilities.complete_offspring, eggs])

  const support = pair?.rbgia_support || {}

  return (
    <ResultBlock
      title="Score and why"
      eyebrow="Step 06 · GICA decision from the weights above"
      sectionId="final-output"
    >
      <p className="compute-read">
        This is the decision. The sections above are the computation: encoded parents, the RBGIA walk, the offspring odds, and the GICA weights. This block shows the score those weights produced and why the points landed where they did.
      </p>
      <p className="thesis-thesis-line">
        {pair?.thesis_statement || 'Genetic Pair Compatibility Analysis of lovebird pair compatibility using rule-based genetic inheritance (GICA + RBGIA).'}
      </p>

      <div className="thesis-final">
        <div className="thesis-primary">
          <CompatibilityScoreCard
            score={score}
            status={status}
            summary={pair?.summary || gica.summary || null}
          />
          <ParentBirdsCard
            parentSnapshot={parentSnapshot}
            parent1Fallback={parent1}
            parent2Fallback={parent2}
          />
        </div>

        <div className="thesis-report">
          <article className="thesis-report__card" aria-labelledby="thesis-why-title">
            <header className="thesis-report__head">
              <h3 className="thesis-report__title" id="thesis-why-title">Why this pair received this score</h3>
              <p className="thesis-report__subtitle">Compatibility Explanation</p>
            </header>

            <section className="thesis-report__section" aria-labelledby="thesis-positive-title">
              <h4 className="thesis-report__section-title" id="thesis-positive-title">Positive Factors</h4>
              {(why.positives || []).length ? (
                <ul className="thesis-factors">
                  {why.positives.map((item) => {
                    const factor = splitFactor(item)
                    return (
                      <li key={`pos-${item}`} className="thesis-factor is-positive">
                        <span className="thesis-factor__icon" aria-hidden="true">✓</span>
                        <div>
                          <p className="thesis-factor__title">{factor.title}</p>
                          {factor.detail ? <p className="thesis-factor__detail">{factor.detail}</p> : null}
                        </div>
                      </li>
                    )
                  })}
                </ul>
              ) : (
                <p className="thesis-report__empty">No positive factors were returned by GICA.</p>
              )}
            </section>

            <section className="thesis-report__section" aria-labelledby="thesis-risk-title">
              <h4 className="thesis-report__section-title" id="thesis-risk-title">Risk / Consideration Factors</h4>
              {(why.warnings || []).length ? (
                <ul className="thesis-factors">
                  {why.warnings.map((item) => {
                    const factor = splitFactor(item)
                    return (
                      <li key={`warn-${item}`} className="thesis-factor is-warning">
                        <span className="thesis-factor__icon" aria-hidden="true">•</span>
                        <div>
                          <p className="thesis-factor__title">{factor.title}</p>
                          {factor.detail ? <p className="thesis-factor__detail">{factor.detail}</p> : null}
                        </div>
                      </li>
                    )
                  })}
                </ul>
              ) : (
                <p className="thesis-report__empty">No significant risk factors identified from the available records.</p>
              )}
            </section>

            {breakdown.length ? (
              <section className="thesis-report__section" aria-labelledby="thesis-evidence-title">
                <h4 className="thesis-report__section-title" id="thesis-evidence-title">Calculation Evidence</h4>
                <dl className="thesis-evidence">
                  {breakdown.map((row) => {
                    const points = Number(row.points ?? row.contribution)
                    const max = Number(row.max_points)
                    return (
                      <div key={row.key || row.factor} className="thesis-evidence__row">
                        <dt>{row.factor}</dt>
                        <dd>
                          <span className="thesis-evidence__value">{evidenceValue(row.value)}</span>
                          {Number.isFinite(points) && Number.isFinite(max) ? (
                            <span className="thesis-evidence__points">{points} / {max} pts</span>
                          ) : null}
                        </dd>
                      </div>
                    )
                  })}
                </dl>
              </section>
            ) : null}
          </article>

          <article className="thesis-report__card thesis-report__card--recommend" aria-labelledby="thesis-recommend-title">
            <header className="thesis-report__head">
              <h3 className="thesis-report__title" id="thesis-recommend-title">Breeding recommendation</h3>
            </header>

            <section className="thesis-report__section" aria-labelledby="thesis-final-title">
              <h4 className="thesis-report__section-title" id="thesis-final-title">Final Pair Analysis</h4>
              <p className="thesis-report__label">Compatibility</p>
              <p className="thesis-report__score">
                <strong>{score == null ? '—' : `${Math.round(score)} / 100`}</strong>
                <span className={`thesis-report__rating is-${gicaTone(score)}`}>{status}</span>
              </p>
            </section>

            <section className="thesis-report__section" aria-label="Key genetic indicators">
              <dl className="thesis-indicators">
                <div className="thesis-indicator">
                  <dt>Genetic Risk</dt>
                  <dd>{finalAnalysis.genetic_risk || risk?.level || '—'}</dd>
                  {risk?.summary ? <p>{risk.summary}</p> : null}
                </div>
                <div className="thesis-indicator">
                  <dt>Genetic Diversity</dt>
                  <dd>{finalAnalysis.genetic_diversity || diversity?.level || '—'}</dd>
                  {diversity?.summary ? <p>{diversity.summary}</p> : null}
                </div>
                <div className="thesis-indicator">
                  <dt>Mutation Compatibility</dt>
                  <dd>{finalAnalysis.mutation_compatibility || mutationCompat?.level || '—'}</dd>
                  {mutationCompat?.detail ? <p>{mutationCompat.detail}</p> : null}
                </div>
                <div className="thesis-indicator">
                  <dt>Species Compatibility</dt>
                  <dd>{species.label || species.status || speciesBreakdownValue(breakdown) || '—'}</dd>
                  {species.breeding_type ? <p>{evidenceValue(String(species.breeding_type).toUpperCase())}</p> : null}
                </div>
              </dl>
            </section>

            <section className="thesis-report__section" aria-labelledby="thesis-reco-title">
              <h4 className="thesis-report__section-title" id="thesis-reco-title">Recommendation</h4>
              <blockquote className="thesis-report__conclusion">{recommendation}</blockquote>
              {finalAnalysis.inheritance_summary ? (
                <p className="thesis-report__note">{finalAnalysis.inheritance_summary}</p>
              ) : null}
              {confidence?.level ? <p className="thesis-report__note">RBGIA data confidence: {confidence.level}</p> : null}
            </section>

            <section className="thesis-report__section thesis-report__basis" aria-labelledby="thesis-basis-title">
              <h4 className="thesis-report__section-title" id="thesis-basis-title">Basis of Analysis</h4>
              <dl className="thesis-basis">
                <div><dt>RBGIA</dt><dd>Rule-Based Genetic Inheritance Analysis</dd></div>
                <div><dt>GICA</dt><dd>Genetic Inheritance Compatibility Analysis</dd></div>
                <div>
                  <dt>Input Records</dt>
                  <dd>{[parent1.bird_id, parent2.bird_id].filter(Boolean).join(' + ') || 'Parent 1 + Parent 2'}</dd>
                </div>
              </dl>
            </section>

            <button
              type="button"
              className="thesis-report-btn"
              onClick={() => downloadBreedingReport({
                pair,
                species,
                gica,
                gicaScore: score,
                eggs,
                probabilities,
                recommendation,
              })}
            >
              Generate Report
            </button>
          </article>
        </div>

        <article className="thesis-card thesis-gica-report">
          <div className="thesis-gica-report__main">
            <header className="gica-breakdown__head">
              <h3 className="gica-breakdown__title">GICA Breakdown</h3>
              <p className="gica-breakdown__subtitle">Compatibility Breakdown</p>
            </header>
            {breakdown.length ? (
              <ol className="gica-breakdown">
                {breakdown.map((row) => {
                  const points = Number(row.points ?? row.contribution)
                  const max = Number(row.max_points)
                  const scoreText = Number.isFinite(points)
                    ? `${points}${Number.isFinite(max) ? ` / ${max}` : ''}`
                    : null
                  const valueText = row.value != null && row.value !== '' ? evidenceValue(row.value) : null
                  return (
                    <li key={row.key || row.factor} className="gica-factor">
                      <p className="gica-factor__name">{row.factor}</p>
                      <p className="gica-factor__score">
                        {scoreText ? <strong>{scoreText}</strong> : null}
                        {scoreText && valueText ? <span aria-hidden="true"> · </span> : null}
                        {valueText ? <span>{valueText}</span> : null}
                        {!scoreText && !valueText ? '—' : null}
                      </p>
                      {row.detail ? <p className="gica-factor__description">{row.detail}</p> : null}
                    </li>
                  )
                })}
              </ol>
            ) : (
              <p className="compute-result__empty">No GICA breakdown was returned.</p>
            )}
            <p className="gica-breakdown__footnote">
              Final GICA score: <strong>{score == null ? '—' : `${Math.round(score)} / 100`}</strong> · {status}
            </p>
          </div>

          <aside className="thesis-gica-report__side" aria-label="Genetic risk and diversity">
            <section className="genetic-card">
              <h3 className="genetic-card__title">Genetic Risk</h3>
              <p className={`genetic-card__value is-${String(risk?.level || 'none').toLowerCase()}`}>{risk?.level || '—'}</p>
              <p className="genetic-card__summary">{risk?.summary || 'No genetic-risk payload returned.'}</p>
              {(risk?.affected || []).length ? (
                <section className="genetic-evidence">
                  <h4 className="genetic-evidence__title">Inheritance Evidence</h4>
                  <ul className="genetic-evidence__list">
                    {risk.affected.slice(0, 6).map((item, index) => {
                      const share = item.fraction || (item.probability != null ? formatPercent(item.probability) : null)
                      const outcome = item.message || item.possible_offspring || item.type
                      return (
                        <li key={`risk-${index}`} className="genetic-evidence__item">
                          {item.locus ? <p className="genetic-evidence__locus">{locusLabel(item.locus)}</p> : null}
                          {outcome || share ? (
                            <p className="genetic-evidence__outcome">
                              {outcome ? <strong>{outcome}</strong> : null}
                              {outcome && share ? ' · ' : ''}
                              {share || ''}
                            </p>
                          ) : null}
                          {item.parent_1 || item.parent_2 ? (
                            <p className="genetic-evidence__parents">
                              P1 {item.parent_1 || '—'} × P2 {item.parent_2 || '—'}
                            </p>
                          ) : null}
                        </li>
                      )
                    })}
                  </ul>
                </section>
              ) : null}
            </section>
            <section className="genetic-card">
              <h3 className="genetic-card__title">Genetic Diversity</h3>
              <p className={`genetic-card__value is-${String(diversity?.level || 'none').toLowerCase()}`}>{diversity?.level || '—'}</p>
              <p className="genetic-card__summary">{diversity?.summary || 'No diversity payload returned.'}</p>
              {(diversity?.loci || []).length ? (
                <section className="genetic-evidence">
                  <h4 className="genetic-evidence__title">Evidence</h4>
                  <ul className="genetic-evidence__list genetic-evidence__list--compact">
                    {diversity.loci.slice(0, 6).map((item, index) => (
                      <li key={`div-${index}`} className="genetic-evidence__item">
                        <p className="genetic-evidence__locus">
                          {locusLabel(item.locus)}
                          {item.relationship ? <span className="genetic-evidence__tag">{item.relationship}</span> : null}
                        </p>
                        <p className="genetic-evidence__parents">
                          P1 {item.parent_1 || '—'} × P2 {item.parent_2 || '—'}
                        </p>
                      </li>
                    ))}
                  </ul>
                </section>
              ) : null}
            </section>
          </aside>
        </article>

        <article className="thesis-card">
          <p className="compute-result__eyebrow">RBGIA · supporting inheritance analysis</p>
          <h3>Rule-Based Inheritance Analysis</h3>
          <p className="thesis-card__summary">
            {support.message || 'Parent 1 + Parent 2 genetic contributions were crossed under documented inheritance rules to produce theoretical offspring outcomes that support the compatibility analysis.'}
          </p>
          <div className="thesis-flow">
            <div className="thesis-flow__node">Parent 1 genetic contribution</div>
            <div className="thesis-flow__arrow">+</div>
            <div className="thesis-flow__node">Parent 2 genetic contribution</div>
            <div className="thesis-flow__arrow">↓</div>
            <div className="thesis-flow__node">RBGIA genetic cross → possible offspring outcomes</div>
          </div>
          <div className="compute-dist-grid" style={{ marginTop: '0.85rem' }}>
            <DistributionPanel
              title="Sex Distribution"
              rows={support.sex || probabilities.sex}
              nameResolver={(row) => sexLabel(row.sex, row.trait || row.sex_label)}
              unavailableReason={unavailableReasonFor(probabilities, 'chromosomal_sex')}
            />
            <DistributionPanel
              title="Base Color Distribution"
              rows={support.base_color || probabilities.base_color}
              unavailableReason={unavailableReasonFor(probabilities, 'base_color')}
            />
            <DistributionPanel
              title="Visual Mutation Distribution"
              rows={support.visual_mutations || probabilities.visual_mutations}
              unavailableReason={unavailableReasonFor(probabilities, 'visual_mutation')}
            />
            <DistributionPanel
              title="Split / Hidden Gene Distribution"
              rows={support.split_hidden_genes || probabilities.split_hidden_genes}
              unavailableReason={unavailableReasonFor(probabilities, 'split_gene')}
            />
          </div>
        </article>

        <article className="thesis-card thesis-card--offspring">
          <header className="thesis-card__head">
            <div>
              <p className="compute-result__eyebrow">Complete offspring outcomes · evidence for compatibility</p>
              <h3>
                {predictedRows.length
                  ? `${predictedRows.length} Theoretical Genetic Outcome${predictedRows.length === 1 ? '' : 's'}`
                  : 'No theoretical offspring outcomes calculated'}
              </h3>
            </div>
          </header>
          {predictedRows.length ? (
            <div className="thesis-table-wrap">
              <table className="thesis-table">
                <thead>
                  <tr>
                    <th>Sex</th>
                    <th>Base Color</th>
                    <th>Visual Mutations</th>
                    <th>Split / Hidden</th>
                    <th>Genotype</th>
                    <th>Probability</th>
                  </tr>
                </thead>
                <tbody>
                  {predictedRows.map((row, index) => (
                    <tr key={`out-${index}-${row.genotype || chickTraitLabel(row)}`}>
                      <td>{sexLabel(row.sex, row.sex_label)}</td>
                      <td>{displayText(row.base_color)}</td>
                      <td>{listOrDash(row.visual_mutations)}</td>
                      <td>{listOrDash(row.split_hidden_genes)}</td>
                      <td><span className="thesis-table__geno">{displayText(row.genotype)}</span></td>
                      <td>{formatPercent(row) || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="compute-result__empty">
              Calculation unavailable. Reason: Joint offspring outcomes could not be assembled from the parental genotypes.
            </p>
          )}
          <p className="thesis-card__footnote">
            These are theoretical RBGIA genotype/phenotype combinations used as supporting evidence for GICA. They are not a guaranteed clutch sequence.
          </p>
        </article>
      </div>
    </ResultBlock>
  )
}

function ComputationalFlowSection({
  pair,
  gica,
  gicaScore,
  rbgiaTrace,
  algorithm,
  parentSnapshot,
}) {
  const method = algorithm?.method || 'AGAPORA-RBGIA-GICA-v3'
  const loci = rbgiaTrace?.loci?.length ?? algorithm?.loci_processed ?? '—'
  const outcomes = rbgiaTrace?.summary?.totalOutcomes ?? '—'
  const status = gicaStatusLabel(pair?.label || gica?.label, gicaScore)
  const parent1 = parentSnapshot?.parent_1?.bird_id || pair?.parent_1?.bird_id || 'Parent 1'
  const parent2 = parentSnapshot?.parent_2?.bird_id || pair?.parent_2?.bird_id || 'Parent 2'

  const featuredLocus = rbgiaTrace?.loci?.find((locus) => locus.square?.valid) || rbgiaTrace?.loci?.[0] || null
  const appearanceRows = (rbgiaTrace?.appearanceOutcomes || []).map((row) => ({
    ...row,
    trait: row.label,
    probability: row.probability,
    fraction: row.fraction,
  }))

  return (
    <ResultBlock
      title="How the algorithm ran"
      eyebrow="Step 02 · why the engines are separate, then the order they ran"
      sectionId="flow"
    >
      <p className="compute-read">
        RBGIA and GICA stay separate so the inheritance math can be checked on its own.
        RBGIA applies Mendelian segregation, independent assortment, and sex-linked chromosomal rules.
        GICA then weights those outcomes. Both parents are already chosen, so this run does not search a population.
        The full squares, odds, weights, score, clutch, and complexity follow on this page.
      </p>

      <ol className="algo-process" aria-label="Algorithm process for this pair">
        <li>
          <span className="algo-process__n">1</span>
          <div>
            <p className="algo-process__label">Encode the pair</p>
            <p><strong>{parent1}</strong> × <strong>{parent2}</strong>. Phenotype, genotype, visual mutations, and known splits are read from stored records.</p>
            <p className="algo-process__why">Why: later steps cannot invent a genotype that was never stored.</p>
          </div>
        </li>
        <li>
          <span className="algo-process__n">2</span>
          <div>
            <p className="algo-process__label">Translate alleles</p>
            <p>Each stored code is read as normal (+) or mutation. W means no gene on that side.</p>
            <p className="algo-process__why">Why: the Punnett square needs symbols, not only the color name a breeder sees.</p>
          </div>
        </li>
        <li>
          <span className="algo-process__n">3</span>
          <div>
            <p className="algo-process__label">Build gametes</p>
            <p>Each parent passes one allele per gene. Heterozygotes split 50 / 50. Sex-linked genes follow the Z chromosome.</p>
            <p className="algo-process__why">Why: this is the Law of Segregation, including chromosomal sex-linkage.</p>
          </div>
        </li>
        <li>
          <span className="algo-process__n">4</span>
          <div>
            <p className="algo-process__label">Punnett square</p>
            <p>Every cock allele is crossed with every hen allele. Method {method}.</p>
            <p className="algo-process__why">Why: the same parents must always fill the same boxes. That is the determinism check.</p>
          </div>
        </li>
        <li>
          <span className="algo-process__n">5</span>
          <div>
            <p className="algo-process__label">Count gene odds</p>
            <p>{loci} genes · {outcomes} chick genotypes. Visible looks and hidden splits are totaled from those boxes.</p>
            <p className="algo-process__why">Why: GICA needs probabilities. It does not roll a random clutch and call that the inheritance.</p>
          </div>
        </li>
        <li>
          <span className="algo-process__n">6</span>
          <div>
            <p className="algo-process__label">Weight the pair</p>
            <p>{gicaScore == null ? '—' : `${Math.round(gicaScore)} / 100`} · {status}. Desirable traits, recessive risk, diversity, and mutation load, plus species fit and breeding constraints.</p>
            <p className="algo-process__why">Why: one score has to show which factor gave or withheld points. RBGIA fractions stay unchanged.</p>
          </div>
        </li>
        <li>
          <span className="algo-process__n">7</span>
          <div>
            <p className="algo-process__label">Forecast eggs and hatchlings</p>
            <p>The species clutch range is adjusted by the score, diversity, and genetic load. Hatchlings apply the stored hatch rate.</p>
            <p className="algo-process__why">Why: clutch size is not a fixed number of chicks. The forecast is an estimate, not a promised nest.</p>
          </div>
        </li>
      </ol>

      <div className="gx-table-wrap">
        <table className="gx-table compute-flow-table">
          <caption className="gx-visually-hidden">Computational components used on this result</caption>
          <thead>
            <tr>
              <th scope="col">Engine</th>
              <th scope="col">What it does</th>
              <th scope="col">Kind</th>
              <th scope="col">This pair</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <th scope="row">Framework</th>
              <td>Evaluates the selected pairing</td>
              <td>Single-pair run · {method}</td>
              <td>{parent1} × {parent2}</td>
            </tr>
            <tr>
              <th scope="row">RBGIA</th>
              <td>Walks inheritance for every stored gene</td>
              <td>Rule-based, same input → same odds</td>
              <td>{loci} genes · {outcomes} chick genotypes</td>
            </tr>
            <tr>
              <th scope="row">GICA</th>
              <td>Scores whether the pair should be bred</td>
              <td>Weighted 0–100 index</td>
              <td>{gicaScore == null ? '—' : `${Math.round(gicaScore)} / 100`} · {status}</td>
            </tr>
          </tbody>
        </table>
      </div>

      {rbgiaTrace?.loci?.length ? <AlleleTranslation loci={rbgiaTrace.loci} title="Allele translation for this pair" /> : null}

      {featuredLocus?.square?.valid ? (
        <div className="algo-punnett">
          <header>
            <p className="compute-result__eyebrow">Featured Punnett square</p>
            <h3>{featuredLocus.name}</h3>
            <p>Cock alleles down the side. Hen alleles across the top. Each box is one possible chick at this gene.</p>
          </header>
          <PunnettSquare locus={featuredLocus} />
        </div>
      ) : null}

      {appearanceRows.length ? (
        <DistributionPanel
          title="Visible gene distribution"
          rows={appearanceRows}
          nameResolver={(row) => row.label || row.trait}
        />
      ) : null}

      <p className="compute-read">
        {pair?.recommendation || gica?.recommendation || 'Open Pair result for the full GICA recommendation, or Punnett walk for every gene.'}
      </p>
    </ResultBlock>
  )
}

function CombinedComplexitySection({ report }) {
  const [mode, setMode] = useState('time')
  return (
    <div className="compute-complexity-wrap">
      <div className="compute-result__switch compute-complexity-switch" role="tablist" aria-label="Complexity measure">
        <button
          type="button"
          role="tab"
          aria-selected={mode === 'time'}
          className={`compute-result__tab${mode === 'time' ? ' is-active' : ''}`}
          onClick={() => setMode('time')}
        >
          Time
        </button>
        <button
          type="button"
          role="tab"
          aria-selected={mode === 'space'}
          className={`compute-result__tab${mode === 'space' ? ' is-active' : ''}`}
          onClick={() => setMode('space')}
        >
          Space
        </button>
      </div>
      <ComplexitySection mode={mode} report={report} proves={getSection('complexity')?.proves} />
    </div>
  )
}

function CompatibilitySection({ result }) {
  const presentation = result?.result_presentation || {}
  const model = useMemo(
    () => buildCompatibilityModel(presentation.gica || {}, presentation.species_compatibility || {}),
    [presentation.gica, presentation.species_compatibility],
  )

  return (
    <ResultBlock
      title="Encoded pair"
      eyebrow="Step 01 · Input the engines are allowed to read"
      sectionId="compatibility"
    >
      <p className="compute-read">
        This is the input phase. Phenotype, genotype, visual mutations, and known split or carrier genes are taken from the stored parent records.
        Appearance is not scored. A missing genotype stays missing. The species factor in the GICA weight table is taken from this record.
      </p>
      <div className="gx-module compute-chapter">
        <SpeciesCompatibilityPanel species={model.species} />
        <h3>Stored parent records</h3>
        <p className="compute-read">
          These are the captured parental fields. Missing values stay missing — genotypes are not invented to fill gaps.
        </p>
        <ParentGeneticProfile snapshot={result?.parent_snapshot} species={model.species} />
      </div>
    </ResultBlock>
  )
}

function GicaSection({ result, gica, gicaScore, rbgiaTrace }) {
  const presentation = result?.result_presentation || {}
  const model = useMemo(
    () => buildCompatibilityModel(gica || {}, presentation.species_compatibility || {}),
    [gica, presentation.species_compatibility],
  )
  const trace = rbgiaTrace

  return (
    <ResultBlock
      title="GICA weight scoring"
      eyebrow="Step 05 · how the 0–100 score is weighted"
      sectionId="gica"
    >
      <p className="compute-read">
        GICA turns the RBGIA odds into one score. Each factor has a fixed maximum. That maximum is its weight: points earned divided by the maximum, then summed to a score from 0 to 100.
        The factors cover desirable trait inheritance, recessive risk, genetic diversity, mutation or genetic load, species fit, and breeding constraints.
        GICA may read the RBGIA gene odds as evidence. It never changes those odds.
      </p>
      <dl className="compute-glossary">
        <div>
          <dt>Weighted combination</dt>
          <dd>
            Species, inheritance information, mutation compatibility, genetic risk, genetic diversity, and breeding constraints.
            Stored engine score: {gicaScore == null ? '—' : `${gicaScore} / 100`}.
          </dd>
        </div>
        <div>
          <dt>Relationship to RBGIA</dt>
          <dd>RBGIA supplies offspring odds as supporting evidence. GICA does not change those fractions, invent genotypes, or replace the inheritance engine.</dd>
        </div>
      </dl>
      <div className="gx-module compute-chapter">
        <CompatibilitySummary
          model={model}
          rbgiaSummary={trace ? { ...trace.summary, lociTotal: trace.loci.length } : null}
        />
        <h3>How the 100 points are awarded</h3>
        <WeightedFormula model={model} />
        <h3>Factor-by-factor proof</h3>
        <p className="compute-read">
          Each factor below is taken from the stored breakdown. The client only presents the formula; it does not re-score the pair.
        </p>
        <ScoreBreakdown model={model} trace={trace} />
      </div>
    </ResultBlock>
  )
}

function GeneticDistributionSection({ probabilities, eggs, exampleNote, rbgiaTrace }) {
  const phenotypeRows = (probabilities.phenotype || []).length
    ? probabilities.phenotype
    : eggs.map((egg) => ({
      phenotype: egg.phenotype,
      genotype: egg.genotype,
      sex: egg.sex,
      probability: egg.probability,
      fraction: egg.probability?.fraction,
      count: egg.probability?.count,
      trait: egg.trait_name || egg.base_color,
    }))

  const complete = (probabilities.complete_offspring || []).length
    ? probabilities.complete_offspring
    : eggs.map((egg) => ({
      sex: egg.sex,
      sex_label: egg.sex_label,
      base_color: egg.base_color,
      base_color_genotype: egg.base_color_genotype,
      visual_mutations: egg.visual_mutations,
      split_hidden_genes: egg.split_hidden_genes || egg.split_genes,
      genotype: egg.genotype_display || egg.genotype,
      phenotype: egg.phenotype,
      probability: egg.probability?.probability ?? egg.probability,
      fraction: egg.probability?.fraction,
    }))

  const unavailableFor = (category) => {
    const hit = (probabilities.unavailable || []).find((item) => item.category === category)
    return hit?.reason || null
  }

  return (
    <ResultBlock title="Offspring odds" eyebrow="Step 04 · RBGIA genotype and phenotype output" sectionId="distribution">
      <p className="compute-read">
        These are the inheritance results GICA scores against. The table is gene by gene: cock allele, hen allele, and the chick odds.
        The panels group the same joint outcomes by sex, base color, visual mutations, and hidden splits.
      </p>
      <PairInheritanceBoard trace={rbgiaTrace} probabilities={probabilities} />
      {probabilities.message ? <p className="compute-result__empty">{probabilities.message}</p> : null}
      <p className="compute-dist-legend">
        All panels are aggregated from the same joint offspring genotype set.
        {exampleNote ? ` ${exampleNote}` : ''}
      </p>
      <div className="compute-dist-grid">
        <DistributionPanel
          title="Sex Distribution"
          rows={probabilities.sex}
          nameResolver={(row) => sexLabel(row.sex, row.trait || row.sex_label)}
          unavailableReason={unavailableFor('chromosomal_sex')}
        />
        <DistributionPanel
          title="Base Color Distribution"
          rows={probabilities.base_color}
          unavailableReason={unavailableFor('base_color')}
        />
        <DistributionPanel
          title="Visual Mutation Distribution"
          rows={probabilities.visual_mutations}
          unavailableReason={unavailableFor('visual_mutation')}
        />
        <DistributionPanel
          title="Split / Hidden Gene Distribution"
          rows={probabilities.split_hidden_genes}
          unavailableReason={unavailableFor('split_gene')}
        />
      </div>

      <h3 className="compute-dist-complete-title">Every calculated chick type</h3>
      <p className="compute-dist-legend">
        Each card is one unique sex + look + hidden-split combination from the Punnett walk.
      </p>
      {(complete || []).length ? (
        <div className="compute-dist-complete-grid">
          {complete.map((row, index) => {
            const pct = toPercent(row)
            return (
              <article key={`complete-${row.genotype || index}-${pct}`} className="compute-dist-complete-card">
                <header>
                  <h4>Offspring Outcome {index + 1}</h4>
                  <span>{formatPercent(pct) || '—'}</span>
                </header>
                <dl className="compute-result__facts">
                  <div><dt>Sex</dt><dd>{sexLabel(row.sex, row.sex_label)}</dd></div>
                  <div><dt>Base Color</dt><dd>{displayText(row.base_color)}</dd></div>
                  <div><dt>Visual Mutations</dt><dd>{listOrDash(row.visual_mutations)}</dd></div>
                  <div><dt>Split / Hidden Genes</dt><dd>{listOrDash(row.split_hidden_genes)}</dd></div>
                  <div><dt>Genotype</dt><dd>{displayText(row.genotype)}</dd></div>
                  <div><dt>Means</dt><dd>{translateGenotype(row.genotype, row.base_color)}</dd></div>
                  <div><dt>Phenotype</dt><dd>{displayText(row.phenotype)}</dd></div>
                </dl>
                <PhenotypeMap source={row} />
                <ProgressBar percent={pct} tone={index === 0 ? 'high' : 'info'} />
                {row.fraction ? <p className="compute-prob-row__fraction">{row.fraction}</p> : null}
              </article>
            )
          })}
        </div>
      ) : (
        <p className="compute-result__empty">
          Calculation unavailable. Reason: Joint offspring outcomes could not be assembled from the parental genotypes.
        </p>
      )}

      <div className="compute-dist-grid" style={{ marginTop: '1rem' }}>
        <DistributionPanel
          title="Genotype Distribution"
          rows={probabilities.genotype}
          nameResolver={(row) => row.genotype || rowName(row)}
        />
        <DistributionPanel
          title="Phenotype Distribution"
          rows={phenotypeRows}
          nameResolver={(row) => row.phenotype || row.trait || row.genotype || rowName(row)}
        />
      </div>
    </ResultBlock>
  )
}

function ForecastSection({ forecast, species, eggs, clutchSimulation }) {
  const hatchPct = toPercent(forecast.hatch_rate ?? forecast.hatch_rate_percent)

  return (
    <ResultBlock
      title="Clutch and hatchlings"
      eyebrow="Step 07 · species range, adjusted by the GICA score"
      sectionId="forecast"
    >
      <p className="compute-read">
        {clutchSimulation
          ? 'The forecast starts from the species clutch range, then moves inside that range using the compatibility score, genetic diversity, and mutation or genetic load. Expected hatchlings apply the hatch rate. Living chick cards sample the unchanged RBGIA odds. Eggs that fail have no genotype.'
          : 'These forecast figures come from the species reproductive range and the compatibility assessment. Missing biological values are not invented. Expected hatchlings apply the stored hatch rate to the forecasted eggs.'}
      </p>
      {clutchSimulation ? <ClutchSimulationPanel simulation={clutchSimulation} eggs={eggs} /> : null}
      <div className="compute-summary__grid compute-summary__grid--forecast">
        <MetricCard label="Estimated Eggs" value={forecast.estimated_eggs || 'Not documented in AGAPORA breeding-safety records. Biological clutch size is not invented.'} />
        <MetricCard label="Estimated Hatchlings" value={forecast.estimated_hatchlings || 'Not documented in AGAPORA breeding-safety records. Hatchling count is not invented.'} />
        <MetricCard
          label="Hatch Rate"
          value={hatchPct == null
            ? (forecast.hatch_rate || forecast.hatch_rate_percent || 'Not documented in AGAPORA breeding-safety records.')
            : formatPercent(hatchPct)}
        >
          {hatchPct != null ? <ProgressBar percent={hatchPct} tone="moderate" /> : null}
        </MetricCard>
        <MetricCard
          label="Forecast Status"
          value={[
            forecast.estimated_eggs,
            forecast.estimated_hatchlings,
            forecast.hatch_rate,
          ].some(forecastIsDocumented) ? 'Partial / documented values present' : 'Not documented'}
        />
        <MetricCard
          label={clutchSimulation ? 'Simulated Clutch Cards' : 'Genetic Outcome Examples'}
          value={forecast.eggs_forecast ?? forecast.genetic_outcome_groups ?? eggs.length ?? '—'}
          hint={clutchSimulation
            ? 'Simulated eggs (3–7) sampled from the compatibility-level clutch distribution — not a promised nest size.'
            : 'Count of illustrative RBGIA outcome cards — not a biological clutch size.'}
        />
        <MetricCard
          label="Biological / Record Basis"
          value={species.fertility_status || 'Not documented'}
          hint={species.risk_level ? `Risk: ${species.risk_level}` : (forecast.expected_hatch_range || undefined)}
        />
      </div>
      {(forecast.forecast_factors || []).length ? (
        <>
          <h3>Forecast factors</h3>
          <ul className="compute-result__list">
            {(forecast.forecast_factors || []).map((factor) => <li key={factor}>{factor}</li>)}
          </ul>
        </>
      ) : null}
      {forecast.note ? <p className="compute-result__empty">{forecast.note}</p> : null}
    </ResultBlock>
  )
}

function InheritanceSection({ inherited, report, eggs, rbgiaTrace, probabilities = {} }) {
  const parent1Badges = parseTraitBadges(inherited.traits_from_parent_1)
  const parent2Badges = parseTraitBadges(inherited.traits_from_parent_2)
  const topEgg = eggs[0]
  const trace = rbgiaTrace

  return (
    <ResultBlock
      title="RBGIA computation"
      eyebrow="Step 03 · alleles, gametes, Punnett squares, phenotype"
      sectionId="inheritance"
    >
      <p className="compute-read">
        This is the inheritance computation. Every stored gene is walked in the same order: translate the alleles, form gametes, fill the Punnett square, map the genotype to a phenotype, then total the odds.
        Dominant, recessive, and sex-linked genes each follow their own rule. Same parents always give the same boxes. GICA does not edit this walk.
      </p>
      <PairInheritanceBoard trace={trace} probabilities={probabilities} title="Parent alleles → chick odds" />
      {trace?.loci?.length ? <AlleleTranslation loci={trace.loci} title="Allele translation" /> : null}
      <div className="compute-inherit-flow">
        <article className="compute-inherit-card">
          <h3>Parent 1 Contribution</h3>
          <div className="compute-badge-wrap">
            {parent1Badges.length
              ? parent1Badges.map((badge) => (
                <span key={`p1-${badge.kind}-${badge.value}`} className="compute-result__badge is-info">
                  {badge.kind}: {badge.value}
                </span>
              ))
              : <p className="compute-result__empty">None recorded</p>}
          </div>
        </article>

        <div className="compute-inherit-arrow" aria-hidden="true">↓</div>

        <article className="compute-inherit-card">
          <h3>Parent 2 Contribution</h3>
          <div className="compute-badge-wrap">
            {parent2Badges.length
              ? parent2Badges.map((badge) => (
                <span key={`p2-${badge.kind}-${badge.value}`} className="compute-result__badge is-info">
                  {badge.kind}: {badge.value}
                </span>
              ))
              : <p className="compute-result__empty">None recorded</p>}
          </div>
        </article>

        <div className="compute-inherit-arrow" aria-hidden="true">↓</div>

        <article className="compute-inherit-card compute-inherit-card--offspring">
          <h3>Offspring Inheritance</h3>
          <div className="compute-summary__grid compute-summary__grid--forecast">
            <MetricCard label="Base Color" value={inherited.base_color_inheritance?.name || report.most_likely_base_color || 'Not calculated'} />
            <MetricCard
              label="Visual Mutations"
              value={listOrDash((inherited.mutation_inheritance || []).map((item) => item.name).filter(Boolean))}
            />
            <MetricCard label="Split / Hidden Genes" value={listOrDash(inherited.recessive_traits_carried)} />
            <MetricCard label="Visible Traits / Phenotype" value={listOrDash(inherited.visible_traits)} />
            <MetricCard
              label="Genotype"
              value={topEgg ? displayText(topEgg.genotype_display || topEgg.genotype) : 'See Genetic Distribution'}
            />
            <MetricCard
              label="Parent contribution codes"
              value={[
                inherited.base_color_inheritance?.parent_1_code,
                inherited.base_color_inheritance?.parent_2_code,
              ].filter(Boolean).join(' × ') || '—'}
            />
          </div>

          {inherited.base_color_inheritance?.results?.length ? (
            <DistributionPanel
              title="Base-color outcome probabilities"
              rows={inherited.base_color_inheritance.results.map((row) => ({
                ...row,
                trait: inherited.base_color_inheritance.name,
              }))}
            />
          ) : null}
        </article>
      </div>

      {(inherited.sex_linked_inheritance || []).length ? (
        <>
          <h3>Sex-linked inheritance</h3>
          <div className="compute-badge-wrap">
            {inherited.sex_linked_inheritance.map((item) => (
              <span key={item.name} className="compute-result__badge is-warning">
                {item.name} · {item.inheritance_type}
              </span>
            ))}
          </div>
        </>
      ) : null}

      <p className="compute-result__empty" style={{ marginTop: '0.75rem' }}>
        {inherited.overall_genetic_interpretation || '—'}
      </p>

      {trace?.loci?.length ? (
        <div className="gx-module compute-chapter">
          <h3>Every gene · Punnett square and odds</h3>
          <RbgiaPipeline loci={trace.loci} />
          <h3>RBGIA summary for this pair</h3>
          <RbgiaSummary trace={trace} />
        </div>
      ) : (
        <p className="compute-result__empty">
          No per-locus RBGIA trace was stored for this result, so Punnett squares cannot be shown without inventing genotypes.
        </p>
      )}
    </ResultBlock>
  )
}

function imageStatusLabel(status) {
  switch (status) {
    case 'idle':
    case 'pending':
    case 'pending_configuration':
      return 'Not Generated'
    case 'generating':
      return 'Generating'
    case 'generated':
    case 'success':
      return 'Generated'
    case 'failed':
    case 'error':
      return 'Failed'
    default:
      return status || 'Not Generated'
  }
}

function mergeEggImage(egg, apiResult) {
  if (!apiResult) return egg
  return {
    ...egg,
    composed_image_prompt: apiResult.prompt || egg.composed_image_prompt,
    image: {
      ...(egg.image || {}),
      status: apiResult.success ? 'generated' : 'failed',
      image_url: apiResult.image_url || null,
      image_path: apiResult.image_path || null,
      provider: apiResult.provider || 'openrouter',
      model: apiResult.model || null,
      exact_prompt_sent: apiResult.prompt || null,
      generation_error: apiResult.success ? null : (apiResult.message || 'Image generation failed'),
      cached: Boolean(apiResult.cached),
      message: apiResult.message,
      updated_at: new Date().toISOString(),
    },
  }
}

function NonLivingEggCard({ egg }) {
  const label = egg.egg_status_label || eggStatusShort(egg.egg_status)
  const stages = egg.clutch_simulation?.stages || []
  const failedStage = stages.find((stage) => stage.passed === false)
  return (
    <article className={`compute-result__egg-card is-nonliving is-${egg.egg_status}`}>
      <header className="compute-result__egg-head">
        <div>
          <p className="compute-result__eyebrow">Simulated egg outcome</p>
          <h3>{`Egg #${egg.egg_number}`}</h3>
        </div>
        <StatusPill tone={eggStatusTone(egg.egg_status)}>{eggStatusShort(egg.egg_status, label)}</StatusPill>
      </header>
      <div className="compute-result__egg-body">
        <div className="compute-result__egg-nonliving">
          <strong>{label}</strong>
          <p>{egg.genetic_explanation}</p>
          {failedStage ? (
            <p className="compute-prob-row__fraction">
              Simulation trace: {failedStage.stage} roll {Number(failedStage.roll).toFixed(4)} exceeded the {Math.round(failedStage.success_probability * 100)}% success tendency
              {egg.clutch_simulation?.compatibility_level ? ` for ${egg.clutch_simulation.compatibility_level}` : ''}.
            </p>
          ) : null}
          <p className="compute-prob-row__fraction">No genotype, phenotype, or image applies — nothing was invented for this egg.</p>
        </div>
      </div>
    </article>
  )
}

function EggChickCard({ egg, computationResultId, onEggUpdated }) {
  if (!isLivingEgg(egg)) return <NonLivingEggCard egg={egg} />
  return <LivingChickCard egg={egg} computationResultId={computationResultId} onEggUpdated={onEggUpdated} />
}

function LivingChickCard({ egg, computationResultId, onEggUpdated }) {
  const image = egg.image || {}
  const [uiStatus, setUiStatus] = useState(() => {
    if (image.image_url && image.status === 'generated') return 'success'
    if (image.status === 'failed') return 'error'
    return 'idle'
  })
  const [errorMessage, setErrorMessage] = useState(image.generation_error || '')
  const [busy, setBusy] = useState(false)
  const pct = toPercent(egg.probability)
  const hasImage = Boolean(image.image_url)
  const displayStatus = busy ? 'generating' : (image.status || uiStatus)

  async function handleGenerate({ force = false } = {}) {
    if (busy || !computationResultId) return
    setBusy(true)
    setUiStatus('generating')
    setErrorMessage('')
    try {
      const data = await generateChickImage(egg, computationResultId, { force })
      const nextEgg = mergeEggImage(egg, data)
      onEggUpdated?.(nextEgg)
      if (data?.success) {
        setUiStatus('success')
      } else {
        setUiStatus('error')
        setErrorMessage(data?.message || 'Image generation failed')
      }
    } catch (err) {
      const message =
        err?.response?.data?.message
        || err?.response?.data?.error
        || 'Image generation failed. Genetic results were not changed.'
      setUiStatus('error')
      setErrorMessage(message)
      onEggUpdated?.(mergeEggImage(egg, {
        success: false,
        message,
        image_url: null,
      }))
    } finally {
      setBusy(false)
    }
  }

  return (
    <article className="compute-result__egg-card">
      <header className="compute-result__egg-head">
        <div>
          <p className="compute-result__eyebrow">{egg.egg_status ? 'Simulated egg → living chick · RBGIA genetics' : 'Egg / Chick Outcome'}</p>
          <h3>{egg.egg_chick_outcome || `Egg/Chick #${egg.egg_number}`}</h3>
        </div>
        <div className="compute-result__egg-status">
          {egg.egg_status ? <StatusPill tone={eggStatusTone(egg.egg_status)}>{eggStatusShort(egg.egg_status, egg.egg_status_label)}</StatusPill> : null}
          <span className={`compute-result__badge is-${displayStatus === 'generated' || displayStatus === 'success' ? 'success' : displayStatus === 'failed' || displayStatus === 'error' ? 'error' : 'warning'}`}>
            {imageStatusLabel(displayStatus)}
          </span>
        </div>
      </header>
      <div className="compute-result__egg-body">
        <div className="compute-result__media">
          {hasImage && !busy ? (
            <img src={image.image_url} alt={`Phenotype visualization for ${egg.egg_chick_outcome || egg.egg_number}`} />
          ) : (
            <div className={`compute-result__placeholder is-${busy ? 'generating' : displayStatus}`}>
              {busy ? (
                <>
                  <p>Generating visual outcome…</p>
                  <p>Please wait while AGAPORA creates a visual representation of this predicted chick.</p>
                </>
              ) : (
                <>
                  <p>Phenotype visualization</p>
                  <p>{imageStatusLabel(displayStatus)}</p>
                </>
              )}
            </div>
          )}
          <div className="compute-result__image-actions">
            <button
              type="button"
              className="compute-result__image-btn"
              disabled={busy || !computationResultId}
              onClick={() => handleGenerate({ force: hasImage })}
            >
              {busy
                ? 'Generating…'
                : hasImage
                  ? 'Regenerate Image'
                  : uiStatus === 'error'
                    ? 'Try Again'
                    : 'Generate Chick Image'}
            </button>
            {errorMessage ? <p className="compute-result__image-error">{errorMessage}</p> : null}
          </div>
        </div>
        <div>
          <dl className="compute-result__facts">
            <div><dt>Species</dt><dd>{speciesLabel(egg.collected_traits?.species || egg.species)}</dd></div>
            <div><dt>Sex</dt><dd>{sexLabel(egg.sex, egg.sex_label || egg.collected_traits?.sex)}</dd></div>
            <div><dt>Base Color</dt><dd>{displayText(egg.collected_traits?.base_color || egg.base_color)}</dd></div>
            <div><dt>Visual Mutations</dt><dd>{listOrDash(egg.collected_traits?.visual_mutations || egg.visual_mutations)}</dd></div>
            <div><dt>Split/Hidden Genes</dt><dd>{listOrDash(egg.collected_traits?.split_hidden_genes || egg.split_hidden_genes || egg.split_genes)}</dd></div>
            <div><dt>Genotype</dt><dd>{displayText(egg.genotype_display || egg.genotype)}</dd></div>
          </dl>
          <PhenotypeMap source={egg} />
          {pct != null ? (
            <div className="compute-prob-row" style={{ marginTop: '0.75rem' }}>
              <div className="compute-prob-row__head">
                <span className="compute-prob-row__name">Probability</span>
                <span className="compute-prob-row__pct">{formatPercent(pct)}{egg.probability?.fraction ? ` · ${egg.probability.fraction}` : ''}</span>
              </div>
              <ProgressBar percent={pct} tone="info" />
            </div>
          ) : null}
        </div>
      </div>
      {egg.genetic_explanation ? (
        <div className="compute-result__note">
          <h4>Genetic explanation</h4>
          <p>{egg.genetic_explanation}</p>
        </div>
      ) : null}
    </article>
  )
}

export default function ComputationResult({ resultId, sectionId }) {
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')
  const [area, setArea] = useState('dashboard')
  const [section, setSection] = useState(() => resolveSection(sectionId))
  const [eggsOverride, setEggsOverride] = useState(null)
  const [headToTailCatalog, setHeadToTailCatalog] = useState(null)
  const [generatingAll, setGeneratingAll] = useState(false)
  const [generateAllMessage, setGenerateAllMessage] = useState('')
  const sectionNavRef = useRef(null)
  const siteHeaderHeight = useSiteHeaderHeight()

  const handleSectionChange = useCallback((nextSection, event) => {
    event?.preventDefault()
    const resolved = resolveSection(nextSection)
    if (!resolved) return
    setSection(resolved)
    const nextHash = sectionHash(resultId, resolved)
    const currentHash = `#${window.location.hash.replace(/^#/, '')}`
    if (currentHash === nextHash) {
      document.getElementById(`process-${resolved}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
      return
    }
    window.location.hash = nextHash
  }, [resultId])

  useEffect(() => {
    const resolved = resolveSection(sectionId)
    if (!resolved || !result) return undefined
    setSection(resolved)
    const node = document.getElementById(`process-${resolved}`)
    if (!node) return undefined
    const frame = window.requestAnimationFrame(() => {
      node.scrollIntoView({ behavior: 'smooth', block: 'start' })
    })
    return () => window.cancelAnimationFrame(frame)
  }, [result, sectionId])

  useEffect(() => {
    if (!result || area !== 'dashboard') return undefined
    const nodes = RESULT_SECTIONS
      .map((item) => document.getElementById(`process-${item.id}`))
      .filter(Boolean)
    if (!nodes.length || typeof IntersectionObserver === 'undefined') return undefined
    const observer = new IntersectionObserver((entries) => {
      const hit = entries
        .filter((entry) => entry.isIntersecting)
        .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0]
      if (!hit) return
      const id = hit.target.id.replace(/^process-/, '')
      if (!getSection(id)) return
      setSection((current) => (current === id ? current : id))
    }, { rootMargin: '-12% 0px -68% 0px', threshold: 0.01 })
    nodes.forEach((node) => observer.observe(node))
    return () => observer.disconnect()
  }, [result, area])

  useEffect(() => {
    let active = true
    setResult(null)
    setError('')
    setArea('dashboard')
    setEggsOverride(null)
    setGenerateAllMessage('')
    api
      .get(`/computation-results/${resultId}`)
      .then((response) => {
        if (active) setResult(response.data?.data || null)
      })
      .catch(() => {
        if (active) setError('This computation result could not be loaded.')
      })
    api
      .get('/head-to-tail-phenotypes')
      .then((response) => {
        if (active) setHeadToTailCatalog(response.data?.data || null)
      })
      .catch(() => {
        if (active) setHeadToTailCatalog(null)
      })
    return () => {
      active = false
    }
  }, [resultId])

  const presentation = result?.result_presentation || {}
  const species = presentation.species_compatibility || {}
  const gica = presentation.gica || {}
  const pair = presentation.pair_compatibility || {}
  const probabilities = presentation.probabilities || {}
  const forecast = presentation.reproductive_forecast || {}
  const speciesId = useMemo(() => resultSpeciesId(result, presentation), [result, presentation])
  const eggs = useMemo(() => {
    const raw = eggsOverride || presentation.egg_chick_examples || result?.egg_outcomes || []
    if (!headToTailCatalog) return raw
    return raw.map((egg) => attachHeadToTail(egg, headToTailCatalog, speciesId))
  }, [eggsOverride, presentation.egg_chick_examples, result?.egg_outcomes, headToTailCatalog, speciesId])
  const enrichedProbabilities = useMemo(() => {
    if (!headToTailCatalog || !probabilities.complete_offspring) return probabilities
    return {
      ...probabilities,
      complete_offspring: probabilities.complete_offspring.map((row) => attachHeadToTail(row, headToTailCatalog, speciesId)),
    }
  }, [probabilities, headToTailCatalog, speciesId])
  const clutchSimulation = presentation.clutch_simulation || null
  const livingEggs = useMemo(() => eggs.filter(isLivingEgg), [eggs])
  const inherited = presentation.inherited_traits || {}
  const report = presentation.report || {}
  const confidence = presentation.data_confidence || {}
  const algorithm = presentation.algorithm || {}

  const gicaScore = typeof gica.score === 'number' ? gica.score : (typeof pair.score === 'number' ? pair.score : null)
  const rbgiaTrace = useMemo(() => (result ? buildRbgiaTrace(result) : null), [result])
  const runSummary = useMemo(() => {
    const parent1 = result?.parent_snapshot?.parent_1?.bird_id || pair?.parent_1?.bird_id || 'Parent 1'
    const parent2 = result?.parent_snapshot?.parent_2?.bird_id || pair?.parent_2?.bird_id || 'Parent 2'
    return {
      parents: `${parent1} × ${parent2}`,
      loci: rbgiaTrace?.loci?.length ?? algorithm?.loci_processed ?? '—',
      outcomes: rbgiaTrace?.summary?.totalOutcomes ?? '—',
      score: gicaScore == null ? '—' : `${Math.round(gicaScore)} / 100`,
      status: gicaStatusLabel(pair?.label || gica.label, gicaScore),
      eggs: forecastIsDocumented(forecast.estimated_eggs) ? String(forecast.estimated_eggs) : 'Not documented',
      hatchlings: forecastIsDocumented(forecast.estimated_hatchlings) ? String(forecast.estimated_hatchlings) : 'Not documented',
    }
  }, [result, pair, gica, gicaScore, rbgiaTrace, algorithm, forecast])
  const complexityReport = useMemo(
    () => (result ? buildComplexityReport(result, rbgiaTrace) : null),
    [result, rbgiaTrace],
  )

  function handleEggUpdated(nextEgg) {
    setEggsOverride((prev) => {
      const current = prev || presentation.egg_chick_examples || result?.egg_outcomes || []
      return current.map((egg) => {
        const sameKey = nextEgg.outcome_key && egg.outcome_key === nextEgg.outcome_key
        const sameNumber = Number(egg.egg_number) === Number(nextEgg.egg_number)
        return sameKey || sameNumber ? nextEgg : egg
      })
    })
  }

  async function handleGenerateAll() {
    if (!result?.id || generatingAll) return
    setGeneratingAll(true)
    setGenerateAllMessage('')
    try {
      const data = await generateAllChickImages(result.id, { force: false })
      if (Array.isArray(data?.egg_outcomes)) {
        setEggsOverride(data.egg_outcomes)
      }
      setGenerateAllMessage(
        data?.success
          ? `Generated ${data.generated_count || 0} chick image(s).`
          : `Completed with ${data.failed_count || 0} failure(s). Genetic results were not changed.`,
      )
    } catch (err) {
      setGenerateAllMessage(
        err?.response?.data?.message || 'Generate-all failed. Genetic results were not changed.',
      )
    } finally {
      setGeneratingAll(false)
    }
  }

  return (
    <main className="breed compute-result" style={{ '--compute-site-header': `${siteHeaderHeight}px` }}>
      <div className="breed__shell compute-result__shell">
        <header className="compute-result__header">
          <div>
            <p className="compute-result__eyebrow">Breeding complete</p>
            <h1>Lovebird pair compatibility</h1>
            <p className="compute-result__lede">
              The opening board is what panelists review first: why the result exists, how RBGIA computed it, how GICA weighted it, and every required output.
              The chapters under the board are the proof. Chick pictures stay on the second tab.
            </p>
          </div>
          {result ? (
            <div className="compute-result__switch" role="tablist" aria-label="Result areas">
              <button
                type="button"
                role="tab"
                aria-selected={area === 'dashboard'}
                className={`compute-result__tab${area === 'dashboard' ? ' is-active' : ''}`}
                onClick={() => setArea('dashboard')}
              >
                Full process
              </button>
              <button
                type="button"
                role="tab"
                aria-selected={area === 'eggs'}
                className={`compute-result__tab${area === 'eggs' ? ' is-active' : ''}`}
                onClick={() => setArea('eggs')}
              >
                Eggs and chicks{eggs.length ? ` (${eggs.length}${clutchSimulation ? ` eggs · ${livingEggs.length} chicks` : ''})` : ''}
              </button>
            </div>
          ) : null}
        </header>

        {error ? <p className="breed__alert">{error}</p> : null}
        {!result && !error ? <p className="compute-result__empty">Loading computation result…</p> : null}

        {result && area === 'dashboard' ? (
          <>
            <PanelistProcessBrief
              pair={pair}
              gica={gica}
              gicaScore={gicaScore}
              rbgiaTrace={rbgiaTrace}
              algorithm={algorithm}
              forecast={forecast}
              probabilities={enrichedProbabilities}
              complexityReport={complexityReport}
              species={species}
              parentSnapshot={result?.parent_snapshot}
              onOpen={handleSectionChange}
            />
            <SectionNav
              ref={sectionNavRef}
              section={section}
              resultId={resultId}
              onChange={handleSectionChange}
              summary={runSummary}
            />
            <div id={SECTION_PANEL_ID} className="compute-process" aria-label="Full breeding process">
              <CompatibilitySection result={result} />
              <ComputationalFlowSection
                pair={pair}
                gica={gica}
                gicaScore={gicaScore}
                rbgiaTrace={rbgiaTrace}
                algorithm={algorithm}
                parentSnapshot={result?.parent_snapshot}
              />
              <InheritanceSection
                inherited={inherited}
                report={report}
                eggs={livingEggs}
                rbgiaTrace={rbgiaTrace}
                probabilities={enrichedProbabilities}
              />
              <GeneticDistributionSection
                probabilities={enrichedProbabilities}
                eggs={livingEggs}
                exampleNote={presentation.example_outcomes_note}
                rbgiaTrace={rbgiaTrace}
              />
              <GicaSection result={result} gica={gica} gicaScore={gicaScore} rbgiaTrace={rbgiaTrace} />
              <FinalSystemOutputSection
                pair={pair}
                species={species}
                gica={gica}
                gicaScore={gicaScore}
                probabilities={enrichedProbabilities}
                eggs={livingEggs}
                confidence={confidence}
                parentSnapshot={result?.parent_snapshot}
              />
              <ForecastSection forecast={forecast} species={species} eggs={eggs} clutchSimulation={clutchSimulation} />
              {complexityReport ? (
                <div id="process-complexity" className="compute-process__anchor">
                  <CombinedComplexitySection report={complexityReport} />
                </div>
              ) : null}
            </div>
          </>
        ) : null}

        {result && area === 'eggs' ? (
          <div className="compute-result__stack" role="tabpanel">
            <ResultBlock title="Egg / Chick Computation Results" eyebrow="Two probability layers: RBGIA genetics + GICA clutch simulation">
              <ClutchSimulationPanel simulation={clutchSimulation} eggs={eggs} />
              <p className="compute-result__empty">
                {clutchSimulation
                  ? 'Egg status comes from the compatibility-based clutch simulation (Layer 2). Each living chick card shows the RBGIA genetic outcome it sampled (Layer 1). OpenRouter only draws a visual from those fields — it never recalculates genetics.'
                  : 'Each card shows the RBGIA genetic outcome. OpenRouter only draws a visual from those fields — it never recalculates genetics.'}
              </p>
              <div className="compute-result__egg-toolbar">
                {result?.id ? (
                  <a href={`/api/computation-results/${result.id}/image-prompts.pdf`} download>
                    Download prompt PDF
                  </a>
                ) : null}
                <button
                  type="button"
                  className="compute-result__image-btn"
                  disabled={generatingAll || !livingEggs.length}
                  onClick={handleGenerateAll}
                >
                  {generatingAll ? 'Generating all chick images…' : `Generate All Chick Images${clutchSimulation ? ` (${livingEggs.length})` : ''}`}
                </button>
              </div>
              {generateAllMessage ? <p className="compute-result__empty">{generateAllMessage}</p> : null}
              {eggs.length ? (
                <div className="compute-result__egg-grid">
                  {eggs.map((egg) => (
                    <EggChickCard
                      key={egg.egg_outcome_id || egg.outcome_key || egg.egg_chick_outcome}
                      egg={egg}
                      computationResultId={result.id}
                      onEggUpdated={handleEggUpdated}
                    />
                  ))}
                </div>
              ) : (
                <p className="compute-result__empty">No egg/chick examples were calculated. Missing genotypes were not invented.</p>
              )}
            </ResultBlock>
          </div>
        ) : null}
      </div>
    </main>
  )
}