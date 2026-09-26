import { describe, expect, it } from 'vitest'
import { loginChecklist, validateLogin, validateRegister } from '../loginRules'

describe('login rules', () => {
  it('accepts a gmail address and a mixed password', () => {
    const result = validateLogin({
      email: 'Ada.Lovelace@gmail.com',
      password: 'lovebird1',
    })

    expect(result.ok).toBe(true)
  })

  it('rejects addresses outside gmail.com', () => {
    const result = validateLogin({
      email: 'ada@yahoo.com',
      password: 'lovebird1',
    })

    expect(result.ok).toBe(false)
    expect(result.errors.email).toMatch(/@gmail\.com/)
  })

  it('rejects a password that is short or missing a number', () => {
    expect(validateLogin({ email: 'ada@gmail.com', password: 'short' }).errors.password).toMatch(/8/)
    expect(validateLogin({ email: 'ada@gmail.com', password: 'longpassword' }).errors.password).toMatch(/number/)
  })

  it('requires a matching confirmation and a real name on register', () => {
    const result = validateRegister({
      name: 'A',
      email: 'ada@gmail.com',
      password: 'lovebird1',
      passwordConfirmation: 'lovebird2',
    })

    expect(result.ok).toBe(false)
    expect(result.errors.name).toBeTruthy()
    expect(result.errors.passwordConfirmation).toMatch(/match/i)
  })

  it('marks every register rule once the form is complete', () => {
    const rules = loginChecklist({
      mode: 'register',
      name: 'Ada Lovelace',
      email: 'ada@gmail.com',
      password: 'lovebird1',
      passwordConfirmation: 'lovebird1',
    })

    expect(rules.every((rule) => rule.met)).toBe(true)
  })
})
