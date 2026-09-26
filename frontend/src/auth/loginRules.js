export const EMAIL_DOMAIN = 'gmail.com'
export const PASSWORD_MIN = 8
export const PASSWORD_MAX = 72
export const NAME_MIN = 2
export const NAME_MAX = 80

export const MESSAGES = {
  emailRequired: 'Email is required.',
  gmail: 'Use a Gmail address ending in @gmail.com.',
  passwordRequired: 'Password is required.',
  passwordLength: 'Password must be at least 8 characters.',
  passwordMax: 'Password must be at most 72 characters.',
  passwordMix: 'Password must include at least one letter and one number.',
  passwordMatch: 'Password confirmation does not match.',
  name: 'Name must be 2 to 80 characters and start with a letter.',
}

const GMAIL_PATTERN = /^[a-z0-9._%+-]+@gmail\.com$/
const NAME_PATTERN = /^[\p{L}][\p{L}\s'.-]{1,79}$/u

export function normalizeEmail(email) {
  return String(email ?? '').trim().toLowerCase()
}

export function isGmailAddress(email) {
  return GMAIL_PATTERN.test(normalizeEmail(email))
}

export function passwordIssues(password) {
  const value = String(password ?? '')

  return {
    length: value.length >= PASSWORD_MIN && value.length <= PASSWORD_MAX,
    letter: /[A-Za-z]/.test(value),
    number: /\d/.test(value),
  }
}

export function isValidPassword(password) {
  const issues = passwordIssues(password)
  return issues.length && issues.letter && issues.number
}

export function isValidName(name) {
  return NAME_PATTERN.test(String(name ?? '').trim())
}

export function passwordMessage(password) {
  const value = String(password ?? '')
  if (!value) return MESSAGES.passwordRequired
  if (value.length < PASSWORD_MIN) return MESSAGES.passwordLength
  if (value.length > PASSWORD_MAX) return MESSAGES.passwordMax
  return MESSAGES.passwordMix
}

export function validateLogin({ email, password }) {
  const errors = {}
  const normalized = normalizeEmail(email)

  if (!normalized) errors.email = MESSAGES.emailRequired
  else if (!isGmailAddress(normalized)) errors.email = MESSAGES.gmail

  if (!isValidPassword(password)) errors.password = passwordMessage(password)

  return { ok: Object.keys(errors).length === 0, errors }
}

export function validateRegister({ name, email, password, passwordConfirmation }) {
  const errors = validateLogin({ email, password }).errors
  const trimmedName = String(name ?? '').trim()

  if (!trimmedName) errors.name = 'Name is required.'
  else if (!isValidName(trimmedName)) errors.name = MESSAGES.name

  if (!passwordConfirmation) errors.passwordConfirmation = MESSAGES.passwordMatch
  else if (password !== passwordConfirmation) errors.passwordConfirmation = MESSAGES.passwordMatch

  return { ok: Object.keys(errors).length === 0, errors }
}

export function loginChecklist({ email, password, passwordConfirmation, name, mode }) {
  const issues = passwordIssues(password)
  const items = [
    {
      id: 'gmail',
      label: 'Email ends with @gmail.com',
      met: isGmailAddress(email),
    },
    {
      id: 'length',
      label: 'Password is 8 to 72 characters',
      met: issues.length,
    },
    {
      id: 'mix',
      label: 'Password includes a letter and a number',
      met: issues.letter && issues.number,
    },
  ]

  if (mode === 'register') {
    items.unshift({
      id: 'name',
      label: 'Name is 2 to 80 characters and starts with a letter',
      met: isValidName(name),
    })
    items.push({
      id: 'match',
      label: 'Password confirmation matches',
      met: String(password).length > 0 && password === passwordConfirmation,
    })
  }

  return items
}
