import { createContext, useContext, useEffect, useMemo, useState } from 'react'
import api from '../api/client'
import { clearToken, readToken, writeToken } from './authStorage'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [status, setStatus] = useState(() => (readToken() ? 'loading' : 'guest'))

  useEffect(() => {
    const token = readToken()
    if (!token) return undefined

    let active = true
    api
      .get('/auth/me')
      .then((response) => {
        if (!active) return
        setUser(response.data.user)
        setStatus('authenticated')
      })
      .catch(() => {
        if (!active) return
        clearToken()
        setUser(null)
        setStatus('guest')
      })

    return () => {
      active = false
    }
  }, [])

  useEffect(() => {
    const handleUnauthorized = () => {
      clearToken()
      setUser(null)
      setStatus('guest')
    }

    window.addEventListener('agapora:unauthorized', handleUnauthorized)
    return () => window.removeEventListener('agapora:unauthorized', handleUnauthorized)
  }, [])

  const value = useMemo(() => {
    const adoptSession = (payload) => {
      writeToken(payload.token)
      setUser(payload.user)
      setStatus('authenticated')
    }

    return {
      user,
      status,
      async login(payload) {
        const response = await api.post('/auth/login', payload)
        adoptSession(response.data)
        return response.data.user
      },
      async register(payload) {
        const response = await api.post('/auth/register', payload)
        adoptSession(response.data)
        return response.data.user
      },
      async logout() {
        try {
          await api.post('/auth/logout')
        } catch {
          // The local session still ends if the server is unreachable.
        }
        clearToken()
        setUser(null)
        setStatus('guest')
        window.location.hash = 'home'
      },
    }
  }, [status, user])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider')
  }
  return context
}
