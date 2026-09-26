export function AuthField({ id, label, type = 'text', name, value, autoComplete, error, onChange }) {
  return (
    <div className={`auth-field${error ? ' is-invalid' : ''}`}>
      <label htmlFor={id}>{label}</label>
      <input
        id={id}
        name={name}
        type={type}
        value={value}
        autoComplete={autoComplete}
        aria-invalid={error ? 'true' : 'false'}
        aria-describedby={error ? `${id}-error` : undefined}
        onChange={onChange}
      />
      {error ? (
        <p id={`${id}-error`} className="auth-field__error" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  )
}

export function RuleList({ rules }) {
  return (
    <ul className="auth-rules">
      {rules.map((rule) => (
        <li key={rule.id} className={rule.met ? 'is-met' : undefined}>
          {rule.label}
        </li>
      ))}
    </ul>
  )
}

export function AuthLayout({ rules, banner, footer, children }) {
  return (
    <main className="auth">
      <div className="auth__center">
        <section className="auth__panel">
          <p className="auth__kicker">The system</p>
          <h1 className="auth__title">Genetic clarity for every pairing.</h1>
          <p className="auth__lede">
            AGAPORA keeps the flock you record, then explains a lovebird pair in two layers that never rewrite each other.
          </p>
          <ul className="auth__points">
            <li>
              <strong>RBGIA</strong> traces inheritance from stored genotypes — alleles, Punnett squares, and offspring odds. Missing codes stay missing.
            </li>
            <li>
              <strong>GICA</strong> scores the pair on species, mutations, risk, and breeding limits. It uses those odds. It does not change them.
            </li>
          </ul>
        </section>

        <section className="auth__card">
          {banner ? (
            <p className="auth__banner" role="alert">
              {banner}
            </p>
          ) : null}
          {children}
          <RuleList rules={rules} />
          <p className="auth__switch">{footer}</p>
        </section>
      </div>
    </main>
  )
}

export function SessionGate({ label = 'Checking your session…' }) {
  return (
    <div className="session-gate" role="status">
      <p>{label}</p>
    </div>
  )
}
