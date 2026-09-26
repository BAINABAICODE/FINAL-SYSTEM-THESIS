const TOKEN_KEY = 'agapora.auth.token'

export function readToken() {
  try {
    return window.localStorage.getItem(TOKEN_KEY) || ''
  } catch {
    return ''
  }
}

export function writeToken(token) {
  window.localStorage.setItem(TOKEN_KEY, token)
}

export function clearToken() {
  try {
    window.localStorage.removeItem(TOKEN_KEY)
  } catch {
    // Storage can be blocked; the in-memory session still ends.
  }
}
