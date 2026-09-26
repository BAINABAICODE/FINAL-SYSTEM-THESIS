import { useMemo } from 'react'
import { buildCompatibilityModel } from '../../services/genetics'

function toPercent(value) {
  if (typeof value === 'number' && Number.isFinite(value)) {
    if (value >= 0 && value <= 1) return Math.round(value * 1000) / 10
    return Math.round(value * 10) / 10
  }
  if (typeof value === 'string') {
    const fraction = value.match(/^(\d+)\s*\/\s*(\d+)$/)
    if (fraction) {
      const den = Number(fraction[2])
      if (den > 0) return Math.round((Number(fraction[1]) / den) * 1000) / 10
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

function formatPct(value) {
  const pct = toPercent(value)
  if (pct == null) return null
  return Number.isInteger(pct) ? `${pct}%` : `${pct}%`
}

function documented(value) {
  if (value == null || value === '') return null
  const text = String(value).trim()
  if (!text || /not documented/i.test(text)) return null
  return text
}

function outcomeName(row) {
  if (!row || typeof row !== 'object') return null
  const named = row.phenotype || row.label || row.trait || row.name || row.mutation || row.base_color
  if (named && typeof named === 'object') return named.display || named.name || named.description || null
  if (named) return String(named)
  if (row.genotype) return String(row.genotype)
  if (row.sex_label || row.sex) return String(row.sex_label || row.sex)
  return null
}

function rankedOutcomes(rows) {
  return [...(rows || [])]
    .map((row) => ({ name: outcomeName(row), pct: toPercent(row) }))
    .filter((row) => row.name)
    .sort((a, b) => (b.pct ?? -1) - (a.pct ?? -1))
}

function joinOutcomes(rows, limit = 2) {
  const list = rankedOutcomes(rows).slice(0, limit)
  if (!list.length) return 'Not calculated for this pair'
  return list.map((row) => (row.pct == null ? row.name : `${row.name} · ${formatPct(row.pct)}`)).join('  ·  ')
}

function distributionValue(rows, noun) {
  const list = rankedOutcomes(rows)
  if (!list.length) return 'Not calculated for this pair'
  const name = list[0].name
  const tooLong = name.length > 72 || /joint genotype/i.test(name)
  if (!tooLong) return joinOutcomes(rows, 2)
  const share = list[0].pct == null ? '' : ` · highest share ${formatPct(list[0].pct)}`
  return `${rows.length} ${noun}${share}`
}

function modeKey(locus) {
  return String(locus?.mode?.key || locus?.inheritanceType || '').toLowerCase()
}

function countModes(loci = []) {
  const dominant = loci.filter((locus) => /dominant/.test(modeKey(locus)) && !/recessive/.test(modeKey(locus))).length
  const recessive = loci.filter((locus) => /recessive/.test(modeKey(locus))).length
  const sexLinked = loci.filter((locus) => locus.sexLinked || /sex_linked|sex-linked/.test(modeKey(locus))).length
  return { dominant, recessive, sexLinked, total: loci.length }
}

function factorLine(factor, fallback) {
  if (!factor) return fallback || 'Not stored on this result'
  const weight = Number.isFinite(factor.weightPercent) ? `${Math.round(factor.weightPercent)}% weight` : 'weight not stored'
  return `${factor.points} / ${factor.max} points · ${weight}`
}

/**
 * Opening board after a breeding run. Panelists see the full why-and-how
 * process here before the chapter proofs below.
 */
export default function PanelistProcessBrief({
  pair,
  gica,
  gicaScore,
  rbgiaTrace,
  algorithm,
  forecast,
  probabilities,
  complexityReport,
  species,
  parentSnapshot,
  onOpen,
}) {
  const model = useMemo(
    () => buildCompatibilityModel(gica || {}, species || {}),
    [gica, species],
  )
  const loci = rbgiaTrace?.loci || []
  const modes = countModes(loci)
  const summary = rbgiaTrace?.summary || {}
  const parent1 = parentSnapshot?.parent_1?.bird_id || pair?.parent_1?.bird_id || 'Parent 1'
  const parent2 = parentSnapshot?.parent_2?.bird_id || pair?.parent_2?.bird_id || 'Parent 2'
  const method = algorithm?.method || summary.method || complexityReport?.method || 'AGAPORA-RBGIA-GICA-v3'
  const scoreText = gicaScore == null ? 'Not documented' : `${Math.round(gicaScore)} / 100`
  const status = pair?.label || gica?.label || model.label || 'Not documented'
  const eggs = documented(forecast?.estimated_eggs)
  const hatchlings = documented(forecast?.estimated_hatchlings)
  const hatchRate = documented(forecast?.hatch_rate || forecast?.hatch_rate_percent)
  const hatchPct = formatPct(forecast?.hatch_rate ?? forecast?.hatch_rate_percent)
  const deterministic = summary.deterministic !== false && algorithm?.deterministic !== false
  const factors = model.factors || []
  const factorByKey = Object.fromEntries(factors.map((factor) => [factor.key, factor]))

  const phases = [
    {
      id: 'normalize',
      n: '01',
      title: 'Normalize the stored pair',
      why: 'Later steps cannot invent a genotype that was never stored. Looks are not scored.',
      how: 'Phenotype, genotype, visual mutations, and known split or carrier genes are read from the parent records and kept in a consistent form. A missing gene stays missing.',
      proof: 'compatibility',
      proofLabel: 'Open the encoded pair',
    },
    {
      id: 'map',
      n: '02',
      title: 'Map inheritance with RBGIA',
      why: 'Mendelian segregation, independent assortment, and chromosomal sex-linkage have to stay visible. The same parents must always fill the same Punnett boxes.',
      how: 'Each stored gene is translated into alleles, split into gametes, and crossed. Dominant, recessive, and sex-linked genes each follow their own rule. The result is a genotype probability distribution, not a dice roll.',
      proof: 'inheritance',
      proofLabel: 'Open the RBGIA computation',
    },
    {
      id: 'phenotype',
      n: '03',
      title: 'Resolve the phenotype',
      why: 'Breeders judge visible traits. The score also needs the hidden splits those looks can carry.',
      how: 'Each genotype is mapped to base color, a visual mutation, or a hidden split. A mutation that changes the color series can override the base. A split that is already visual is not listed twice.',
      proof: 'distribution',
      proofLabel: 'Open offspring odds',
    },
    {
      id: 'score',
      n: '04',
      title: 'Weight the pair with GICA',
      why: 'One score has to show desirable traits, recessive risk, genetic diversity, and mutation load. It must not rewrite the RBGIA fractions.',
      how: 'Each factor earns points out of a fixed maximum. That maximum is its weight. Raw is points divided by the maximum. The score is the weighted sum, from 0 to 100.',
      proof: 'gica',
      proofLabel: 'Open the weight scoring',
    },
    {
      id: 'forecast',
      n: '05',
      title: 'Forecast clutch and hatchlings',
      why: 'Clutch size is not a fixed number of chicks. The forecast is a decision-support estimate inside the species range.',
      how: 'The species clutch range is adjusted by the compatibility score, genetic diversity, and mutation or genetic load. Expected hatchlings apply the stored hatch rate to the forecasted eggs.',
      proof: 'forecast',
      proofLabel: 'Open the clutch forecast',
    },
  ]

  const ledger = [
    { label: 'Encoded parents', value: `${parent1} × ${parent2}`, section: 'compatibility' },
    { label: 'Algorithm', value: `${method} · ${deterministic ? 'deterministic' : 'determinism not confirmed'}`, section: 'flow' },
    { label: 'RBGIA genes', value: loci.length ? `${modes.total} genes · ${summary.totalOutcomes ?? '—'} joint genotypes` : 'No per-gene trace stored', section: 'inheritance' },
    { label: 'Dominant · recessive · sex-linked', value: loci.length ? `${modes.dominant} dominant · ${modes.recessive} recessive · ${modes.sexLinked} sex-linked` : 'Not calculated', section: 'inheritance' },
    {
      label: 'Genotype distribution',
      value: distributionValue(probabilities?.genotype, 'joint genotypes'),
      section: 'distribution',
    },
    {
      label: 'Phenotype distribution',
      value: summary.appearanceCount
        ? joinOutcomes(rbgiaTrace?.appearanceOutcomes, 2)
        : distributionValue(probabilities?.phenotype, 'phenotypes'),
      section: 'distribution',
    },
    { label: 'Visual mutations', value: joinOutcomes(probabilities?.visual_mutations), section: 'distribution' },
    { label: 'Split / hidden genes', value: joinOutcomes(probabilities?.split_hidden_genes), section: 'distribution' },
    { label: 'Sex distribution', value: joinOutcomes(probabilities?.sex), section: 'distribution' },
    { label: 'GICA score', value: `${scoreText} · ${status}`, section: 'final-output' },
    { label: 'Desirable / inheritance information', value: factorLine(factorByKey.inheritance_information), section: 'gica' },
    { label: 'Recessive risk', value: factorLine(factorByKey.genetic_risk, gica?.risk?.level ? `Risk level ${gica.risk.level}` : null), section: 'gica' },
    { label: 'Genetic diversity', value: factorLine(factorByKey.genetic_diversity, gica?.diversity?.level ? `Diversity ${gica.diversity.level}` : null), section: 'gica' },
    { label: 'Mutation / genetic load', value: factorLine(factorByKey.mutation_compatibility), section: 'gica' },
    { label: 'Species fit', value: factorLine(factorByKey.species_compatibility), section: 'gica' },
    { label: 'Breeding constraints', value: factorLine(factorByKey.breeding_constraints), section: 'gica' },
    { label: 'Forecasted eggs', value: eggs || 'Not documented', section: 'forecast' },
    { label: 'Expected hatchlings', value: hatchlings || 'Not documented', section: 'forecast' },
    { label: 'Hatch rate', value: hatchPct || hatchRate || 'Not documented', section: 'forecast' },
    {
      label: 'Time complexity',
      value: complexityReport ? `${complexityReport.time.bound} · ${complexityReport.time.substituted} · ${complexityReport.time.measuredOps} steps` : 'Not counted',
      section: 'complexity',
    },
    {
      label: 'Space complexity',
      value: complexityReport ? `${complexityReport.space.bound} · ${complexityReport.space.substituted} · ${complexityReport.space.measuredUnits} stored units` : 'Not counted',
      section: 'complexity',
    },
  ]

  return (
    <section className="panel-brief" id="process-brief" aria-labelledby="panel-brief-title">
      <header className="panel-brief__mast">
        <p className="panel-brief__kicker">First view after breeding completes</p>
        <h2 id="panel-brief-title">Why this result exists, and how it was computed</h2>
        <p>
          Panelists review the process before any chick picture. RBGIA computes inheritance from Mendelian and chromosomal rules.
          GICA weights that evidence into one score. The clutch forecast uses the score and never rewrites the Punnett squares.
          Identical stored parents always produce this same board.
        </p>
      </header>

      <div className="panel-brief__roles" aria-label="What each panelist checks on this board">
        <article>
          <h3>Breeders</h3>
          <p>Predicted offspring traits, recessive risk, diversity, and whether the score matches those outcomes.</p>
        </article>
        <article>
          <h3>Hobbyists</h3>
          <p>The odds in plain language, the recommendation, and the clutch estimate — without a hidden formula.</p>
        </article>
        <article>
          <h3>IT experts</h3>
          <p>Determinism, the Punnett trace, the weight formula, and the time and space this run actually used.</p>
        </article>
      </div>

      <ol className="panel-brief__phases">
        {phases.map((phase) => (
          <li key={phase.id}>
            <p className="panel-brief__phase-n">{phase.n}</p>
            <h3>{phase.title}</h3>
            <p><span>Why. </span>{phase.why}</p>
            <p><span>How. </span>{phase.how}</p>
            <button type="button" onClick={() => onOpen?.(phase.proof)}>
              {phase.proofLabel}
            </button>
          </li>
        ))}
      </ol>

      <div className="panel-brief__engines">
        <article aria-labelledby="panel-rbgia-title">
          <p className="panel-brief__engine-kicker">Computation · RBGIA</p>
          <h3 id="panel-rbgia-title">Rule-Based Genetic Inheritance Algorithm</h3>
          <p>
            {parent1} × {parent2}. Method {method}. {deterministic ? 'This run is deterministic: the same input fills the same boxes.' : 'Determinism was not confirmed on this payload.'}
          </p>
          <dl>
            <div>
              <dt>Genes walked</dt>
              <dd>{modes.total || '—'}</dd>
            </div>
            <div>
              <dt>Joint genotypes</dt>
              <dd>{summary.totalOutcomes ?? '—'}</dd>
            </div>
            <div>
              <dt>Visible looks</dt>
              <dd>{summary.appearanceCount ?? '—'}</dd>
            </div>
            <div>
              <dt>Sex-linked genes</dt>
              <dd>{modes.sexLinked}</dd>
            </div>
          </dl>
          <p className="panel-brief__engine-note">
            {modes.dominant} dominant, {modes.recessive} recessive, {modes.sexLinked} sex-linked.
            GICA may read these odds. It does not change them.
          </p>
        </article>

        <article aria-labelledby="panel-gica-title">
          <p className="panel-brief__engine-kicker">Weight scoring · GICA</p>
          <h3 id="panel-gica-title">Genetic Inheritance Compatibility Algorithm</h3>
          <p className="panel-brief__formula" aria-label="GICA weighted score">
            Score = Σ (weight × raw). Weight = factor maximum ÷ 100. Raw = points ÷ maximum × 100.
          </p>
          <p className="panel-brief__score">
            <strong>{scoreText}</strong>
            <span>{status}</span>
          </p>
          {factors.length ? (
            <ul className="panel-brief__weights">
              {factors.map((factor) => (
                <li key={factor.key}>
                  <span>{factor.name}</span>
                  <span>{factor.points} / {factor.max}</span>
                  <span>{Math.round(factor.weightPercent)}%</span>
                  <span className="panel-brief__bar" aria-hidden="true">
                    <i style={{ width: `${Math.max(0, Math.min(100, factor.raw))}%` }} />
                  </span>
                </li>
              ))}
            </ul>
          ) : (
            <p>No weighted factor breakdown is stored for this result. The score above is the stored engine value.</p>
          )}
          <button type="button" onClick={() => onOpen?.('final-output')}>
            Open the score and why
          </button>
        </article>
      </div>

      <div className="panel-brief__ledger-wrap">
        <h3>Everything this completed breeding must show</h3>
        <p>
          These are the study outputs for this pair: genotype and phenotype distributions, mutations and splits, the numerical score, the weighted factors, the clutch, the hatchlings, and the work the algorithm did.
          Chick pictures are on the second tab. They do not change these values.
        </p>
        <dl className="panel-brief__ledger">
          {ledger.map((row) => (
            <div key={row.label}>
              <dt>{row.label}</dt>
              <dd>{row.value}</dd>
              <dd>
                <button type="button" onClick={() => onOpen?.(row.section)}>
                  Proof
                </button>
              </dd>
            </div>
          ))}
        </dl>
      </div>

      <p className="panel-brief__disclaimer">
        {pair?.recommendation || gica?.recommendation || 'The recommendation is stored with the score in the proof below.'}
        {' '}This board is a decision-support estimate. It does not guarantee breeding success, a specific nest, or exact offspring traits.
      </p>
    </section>
  )
}
