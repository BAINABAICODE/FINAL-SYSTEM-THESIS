const PUBLIC_ROUTE_NAMES = ['home', 'about', 'help', 'login', 'register']

export const PUBLIC_ROUTES = new Set(PUBLIC_ROUTE_NAMES)

export function getRouteFromHash() {
  const hash = window.location.hash.replace(/^#/, '')
  const path = hash.split('?')[0]
  const computation = path.match(/^computation\/(\d+)(?:\/([a-z0-9-]+))?$/)

  if (computation) {
    return { name: 'computation', id: computation[1], section: computation[2] || null }
  }
  if (path === 'about' || path.startsWith('about')) return { name: 'about' }
  if (path === 'help' || path.startsWith('help')) return { name: 'help' }
  if (path === 'birds' || path.startsWith('birds')) return { name: 'birds' }
  if (path === 'breeding' || path.startsWith('breeding')) return { name: 'breeding' }
  if (path.startsWith('computation-history') || path.startsWith('predictions')) return { name: 'history' }
  if (path === 'login' || path.startsWith('login')) return { name: 'login' }
  if (path === 'register' || path.startsWith('register')) return { name: 'register' }
  return { name: 'home' }
}

export function safeNextPath(value) {
  if (!value) return 'home'

  let path = value
  try {
    path = decodeURIComponent(value)
  } catch {
    return 'home'
  }

  path = path.replace(/^#/, '').replace(/^\//, '').split('?')[0]
  if (!/^[a-z0-9]+(?:[/-][a-z0-9]+)*$/.test(path)) return 'home'
  if (path === 'login' || path === 'register') return 'home'
  return path
}

export function readNextPath() {
  const query = window.location.hash.split('?')[1] || ''
  return safeNextPath(new URLSearchParams(query).get('next'))
}

export function authPath(name) {
  const query = window.location.hash.split('?')[1]
  return query ? `#${name}?${query}` : `#${name}`
}
