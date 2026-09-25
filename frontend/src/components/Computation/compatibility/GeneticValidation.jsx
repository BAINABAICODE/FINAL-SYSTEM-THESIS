const ICONS = { error: '✕', warning: '⚠', info: 'ℹ' }
const LABELS = { error: 'Error', warning: 'Warning', info: 'Notice' }

export default function GeneticValidation({ findings }) {
  if (!findings.length) {
    return <p className="gx-alert is-ok">✓ No genetic validation issues were detected for the stored parental data.</p>
  }
  return (
    <ul className="gx-findings">
      {findings.map((finding, index) => (
        <li key={`${finding.code}-${index}`} className={`gx-finding is-${finding.severity}`}>
          <span className="gx-finding__icon" aria-hidden="true">{ICONS[finding.severity]}</span>
          <div>
            <p className="gx-finding__title"><span className="gx-visually-hidden">{LABELS[finding.severity]}: </span>{finding.title}</p>
            <p className="gx-finding__message">{finding.message}</p>
            <p className="gx-finding__source">Source: <code>{finding.source}</code></p>
          </div>
        </li>
      ))}
    </ul>
  )
}
