import { INHERITANCE_MODE_LABELS } from '../../../services/genetics'
import { KeyValue } from '../shared/primitives'
import { percentText } from '../shared/format'

export default function RbgiaSummary({ trace }) {
  const { summary } = trace
  const [first, second] = summary.top
  const [lookFirst, lookSecond] = summary.topAppearance || []
  return (
    <div className="gx-final">
      <KeyValue rows={[
        ['Total predicted genetic outcomes', `${summary.totalOutcomes} joint genotypes · ${summary.visualOutcomeCount} sex/visual/split outcomes · ${summary.appearanceCount} visual appearance classes`],
        ['Most likely appearance', lookFirst ? `${lookFirst.label} — ${percentText(lookFirst.probability)}` : null],
        ['Second appearance', lookSecond ? `${lookSecond.label} — ${percentText(lookSecond.probability)}` : 'None'],
        ['Highest full outcome', first ? `${first.label}${first.sexLabel ? ` (${first.sexLabel})` : ''} — ${percentText(first.probability)}` : null],
        ['Second full outcome', second ? `${second.label}${second.sexLabel ? ` (${second.sexLabel})` : ''} — ${percentText(second.probability)}` : 'None'],
        ['Sex-linked traits', summary.sexLinkedTraits.length ? summary.sexLinkedTraits.join(', ') : 'None detected'],
        ['Primary inheritance modes', summary.primaryModes.map((key) => `${INHERITANCE_MODE_LABELS[key] || key} (${summary.modeCounts[key]})`).join(', ') || null],
        ['Loci verified against engine', `${summary.lociVerified} / ${summary.lociWithStored}`],
        ['Probability total', percentText(summary.probabilityTotal)],
        ['Prediction basis', 'Mendelian allele combination + deterministic phenotype mapping from the stored mutation database'],
        ['Method', summary.method],
      ]} />
    </div>
  )
}
