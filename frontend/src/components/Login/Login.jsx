import { useState } from 'react'
import { readApiError } from '../../auth/apiError'
import { useAuth } from '../../auth/AuthContext'
import { loginChecklist, normalizeEmail, validateLogin } from '../../auth/loginRules'
import { authPath, readNextPath } from '../../auth/routes'
import { AuthField, AuthLayout } from '../Auth/AuthLayout'
import '../Auth/AuthLayout.css'

export default function Login() {
  const { login } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [banner, setBanner] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const rules = loginChecklist({ email, password, mode: 'login' })
  const ready = rules.every((rule) => rule.met)

  const handleSubmit = async (event) => {
    event.preventDefault()
    const result = validateLogin({ email, password })
    setFieldErrors(result.errors)
    setBanner('')
    if (!result.ok) return

    setSubmitting(true)
    try {
      await login({
        email: normalizeEmail(email),
        password,
      })
      window.location.hash = readNextPath()
    } catch (error) {
      const parsed = readApiError(error)
      setFieldErrors(parsed.fieldErrors)
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
          New to AGAPORA? <a href={authPath('register')}>Create an account</a>
        </>
      }
    >
      <form className="auth-form" onSubmit={handleSubmit} noValidate>
        <AuthField
          id="login-email"
          name="email"
          label="Gmail address"
          type="email"
          autoComplete="username"
          value={email}
          error={fieldErrors.email}
          onChange={(event) => setEmail(event.target.value)}
        />
        <AuthField
          id="login-password"
          name="password"
          label="Password"
          type="password"
          autoComplete="current-password"
          value={password}
          error={fieldErrors.password}
          onChange={(event) => setPassword(event.target.value)}
        />
        <button className="auth-submit" type="submit" disabled={!ready || submitting}>
          {submitting ? 'Signing in…' : 'Sign in'}
        </button>
      </form>
    </AuthLayout>
  )
}
