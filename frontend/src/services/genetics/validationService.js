import { PREDICTION_CONFIDENCE } from './constants'

/**
 * Collects genetic-validation findings from the stored analysis. Every finding cites the
 * payload field it came from so the UI can show *why* a limitation exists. No genotype
 * is ever assumed here; missing data is reported, not filled in.
 */

/**
 * @param {object} params
 * @param {object} params.result full API result (`data`)
 * @param {Array<object>} params.lociTraces per-locus traces from `buildRbgiaTrace`
 */
export function collectValidationFindings({ result = {}, lociTraces = [] }) {
  const presentation = result.result_presentation || {}
  const species = presentation.species_compatibility || {}
  const probabilities = presentation.probabilities || {}
  const confidence = presentation.data_confidence || {}
  const geneticCompat = result.genetic_compatibility || {}
  const snapshot = result.parent_snapshot || {}
  const findings = []

  const push = (severity, code, title, message, source) => findings.push({ severity, code, title, message, source })

  ;(species.errors || []).forEach((error) => push('error', 'species_error', 'Species / pairing error', text(error), 'species_compatibility.errors'))
  ;(geneticCompat.errors || []).forEach((error) => push('error', 'validation_error', 'Pair validation error', text(error), 'genetic_compatibility.errors'))
  ;(species.warnings || []).forEach((warning) => push('warning', 'species_warning', 'Species / pairing notice', text(warning), 'species_compatibility.warnings'))
  if (species.same_species === false) {
    push('warning', 'interspecific', 'Interspecific pairing', 'The selected parents belong to different stored species records. Genetic prediction confidence is reduced and hybrid documentation applies.', 'species_compatibility.same_species')
  }

  ;(probabilities.unavailable || []).forEach((item) =>
    push('warning', 'missing_genotype', `Missing genotype — ${item.name || item.category}`, item.reason || 'No stored genotype for this locus.', 'probabilities.unavailable'),
  )

  lociTraces.forEach((trace) => {
    if (trace.status && trace.status !== 'calculated') {
      push('warning', 'locus_not_calculated', `${trace.name}: not calculated`, trace.reason || `Locus status "${trace.status}".`, 'genetic_analysis.outcomes[].status')
    }
    if (trace.mode?.unknown) {
      push('warning', 'unknown_mode', `${trace.name}: unknown inheritance mode`, `Stored inheritance type "${trace.inheritanceType || 'not provided'}" could not be classified. The locus is shown as documented but not interpreted further.`, 'genetic_analysis.outcomes[].inheritance_type')
    }
    if (trace.verificationStatus && /needs verification|unverified/i.test(trace.verificationStatus)) {
      push('warning', 'unverified_mutation', `${trace.name}: dataset entry not fully verified`, `Verification status: ${trace.verificationStatus}.`, 'genetic_analysis.outcomes[].verification_status')
    }
    ;['cock', 'hen'].forEach((role) => {
      const parent = trace.parents[role]
      if (parent?.assumedNonCarrier) {
        push('info', 'assumed_non_carrier', `${trace.name}: ${role} treated as documented non-carrier`, trace.assumptionNote || 'The unselected parent was treated as a documented non-carrier using the stored wild-type allele. No hidden split was invented.', 'genetic_analysis.outcomes[].parent_contribution')
      }
      if (parent && !parent.valid) {
        push('warning', 'invalid_genotype', `${trace.name}: ${role} genotype unreadable`, parent.reason || 'Genotype could not be parsed.', 'genetic_analysis.outcomes[].genetic_code')
      }
    })
    if (trace.sexLinked && trace.parents.hen?.valid && !trace.parents.hen.hemizygous) {
      push('error', 'invalid_sex_linked', `${trace.name}: invalid sex-linked configuration`, 'A hen must be hemizygous (Z/W) at a Z-linked locus; the stored code lists two Z alleles.', 'genetic_analysis.outcomes[].genetic_code_hen')
    }
    if (trace.verified === false) {
      push('error', 'trace_mismatch', `${trace.name}: trace does not match stored result`, 'The frontend Punnett reconstruction disagrees with the stored RBGIA probabilities. The stored result is authoritative; please report this locus.', 'genetic_analysis.outcomes[].results')
    }
  })

  const visualWithoutSplitInfo = []
  ;['parent_1', 'parent_2'].forEach((key) => {
    const parent = snapshot[key]
    if (!parent) return
    if ((parent.visual_mutations || []).length && !(parent.split_genes || []).length) visualWithoutSplitInfo.push(parent.bird_id || key)
  })
  if (visualWithoutSplitInfo.length) {
    push('info', 'limited_split_info', 'Limited split / hidden-gene information', `${visualWithoutSplitInfo.join(' and ')} ${visualWithoutSplitInfo.length > 1 ? 'have' : 'has'} a visual mutation but no confirmed split/hidden gene record. Predictions use only the available parental data.`, 'parent_snapshot.split_genes')
  }

  const grandparents = ['parent_1', 'parent_2'].map((key) => Number(snapshot[key]?.grandparents_recorded) || 0)
  if (grandparents.some((count) => count === 0)) {
    push('info', 'incomplete_pedigree', 'Incomplete grandparent data', 'One or both parents have no grandparent records. Additional pedigree records may refine hidden-split assumptions but cannot guarantee actual offspring results.', 'parent_snapshot.grandparents_recorded')
  }

  ;(confidence.notes || []).forEach((note) => push('info', 'confidence_note', 'Data-confidence note', text(note), 'data_confidence.notes'))

  return dedupe(findings)
}

export function summariseConfidence({ result = {}, lociTraces = [], findings = [] }) {
  const presentation = result.result_presentation || {}
  const confidence = presentation.data_confidence || {}
  const snapshot = result.parent_snapshot || {}
  const level = String(confidence.level || '').toUpperCase()
  const errors = findings.filter((f) => f.severity === 'error').length
  const warnings = findings.filter((f) => f.severity === 'warning').length

  const genotypeState = level === 'CONFIRMED' && !warnings ? 'complete' : level === 'INSUFFICIENT_DATA' ? 'insufficient' : 'partial'
  const grandparents = ['parent_1', 'parent_2'].map((key) => Number(snapshot[key]?.grandparents_recorded) || 0)
  const pedigreeState = grandparents.every((count) => count > 0) ? 'provided' : grandparents.some((count) => count > 0) ? 'partial' : 'not_provided'
  const coverage = lociTraces.length
    ? lociTraces.filter((t) => t.status === 'calculated' && !t.mode?.unknown).length / lociTraces.length
    : 0

  let overall = PREDICTION_CONFIDENCE.HIGH
  if (errors || genotypeState === 'insufficient') overall = PREDICTION_CONFIDENCE.LIMITED
  else if (warnings || genotypeState === 'partial' || coverage < 1) overall = PREDICTION_CONFIDENCE.MODERATE

  return {
    deterministicRules: true,
    genotypeState,
    pedigreeState,
    coverage,
    coveragePercent: Math.round(coverage * 100),
    lociCalculated: lociTraces.filter((t) => t.status === 'calculated').length,
    lociTotal: lociTraces.length,
    backendLevel: confidence.level || null,
    backendMessage: confidence.message || null,
    overall,
  }
}

function text(value) {
  if (value == null) return ''
  if (typeof value === 'string') return value
  return value.message || value.detail || value.reason || JSON.stringify(value)
}

function dedupe(findings) {
  const seen = new Set()
  return findings.filter((f) => {
    const key = `${f.code}|${f.title}|${f.message}`
    if (seen.has(key)) return false
    seen.add(key)
    return true
  })
}
