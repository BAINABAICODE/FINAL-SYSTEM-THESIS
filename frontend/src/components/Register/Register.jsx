import { useState } from 'react'
import { readApiError } from '../../auth/apiError'
import { useAuth } from '../../auth/AuthContext'
import { loginChecklist, normalizeEmail, validateRegister } from '../../auth/loginRules'
import { authPath, readNextPath } from '../../auth/routes'
import { AuthField, AuthLayout } from '../Auth/AuthLayout'
import '../Auth/AuthLayout.css'

export default function Register() {
  const { register } = useAuth()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [banner, setBanner] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const rules = loginChecklist({
    name,
    email,
    password,
    passwordConfirmation,
    mode: 'register',
  })
  const ready = rules.every((rule) => rule.met)

  const handleSubmit = async (event) => {
    event.preventDefault()
    const result = validateRegister({ name, email, password, passwordConfirmation })
    setFieldErrors(result.errors)
    setBanner('')
    if (!result.ok) return

    setSubmitting(true)
    try {
      await register({
        name: name.trim(),
        email: normalizeEmail(email),
        password,
        password_confirmation: passwordConfirmation,
      })
      window.location.hash = readNextPath()
    } catch (error) {
      const parsed = readApiError(error)
      setFieldErrors({
        ...parsed.fieldErrors,
        passwordConfirmation: parsed.fieldErrors.password_confirmation,
      })
      setBanner(parsed.banner)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <AuthLayout
      rules={rules}
      banner={banner}
      footer={
        <>
          Already registered? <a href={authPath('login')}>Sign in</a>
        </>
      }
    >
      <form className="auth-form" onSubmit={handleSubmit} noValidate>
        <AuthField
          id="register-name"
          name="name"
          label="Name"
          autoComplete="name"
          value={name}
          error={fieldErrors.name}
          onChange={(event) => setName(event.target.value)}
        />
        <AuthField
          id="register-email"
          name="email"
          label="Gmail address"
          type="email"
          autoComplete="username"
          value={email}
          error={fieldErrors.email}
          onChange={(event) => setEmail(event.target.value)}
        />
        <AuthField
          id="register-password"
          name="password"
          label="Password"
          type="password"
          autoComplete="new-password"
          value={password}
          error={fieldErrors.password}
          onChange={(event) => setPassword(event.target.value)}
        />
        <AuthField
          id="register-password-confirmation"
          name="password_confirmation"
          label="Confirm password"
          type="password"
          autoComplete="new-password"
          value={passwordConfirmation}
          error={fieldErrors.passwordConfirmation}
          onChange={(event) => setPasswordConfirmation(event.target.value)}
        />
        <button className="auth-submit" type="submit" disabled={!ready || submitting}>
          {submitting ? 'Creating account…' : 'Create account'}
        </button>
      </form>
    </AuthLayout>
  )
}
