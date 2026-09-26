import { useEffect, useState } from 'react'
import { useAuth } from './auth/AuthContext'
import { getRouteFromHash, PUBLIC_ROUTES, readNextPath } from './auth/routes'
import About from './components/About/About.jsx'
import { SessionGate } from './components/Auth/AuthLayout.jsx'
import './components/Auth/AuthLayout.css'
import BirdsManagement from './components/BirdsManagement/BirdsManagement.jsx'
import BreedingWorkflow from './components/Breeding/BreedingWorkflow.jsx'
import ComputationResult from './components/Computation/ComputationResult.jsx'
import Help from './components/Help/Help.jsx'
import Homepage from './components/Homepage/Homepage.jsx'
import Login from './components/Login/Login.jsx'
import Navbar from './components/Navbar/Navbar.jsx'
import Predictions from './components/Predictions/Predictions.jsx'
import Register from './components/Register/Register.jsx'
import './App.css'

const AUTH_SCREENS = new Set(['login', 'register'])

export default function App() {
  const { status } = useAuth()
  const [route, setRoute] = useState(getRouteFromHash)

  useEffect(() => {
    const syncRoute = () => setRoute(getRouteFromHash())
    syncRoute()
    window.addEventListener('hashchange', syncRoute)
    return () => window.removeEventListener('hashchange', syncRoute)
  }, [])

  useEffect(() => {
    window.scrollTo(0, 0)
  }, [route.name, route.id])

  useEffect(() => {
    if (status === 'loading') return undefined

    if (status === 'guest' && !PUBLIC_ROUTES.has(route.name)) {
      const next = window.location.hash.replace(/^#/, '') || 'home'
      window.location.hash = `login?next=${encodeURIComponent(next)}`
      return undefined
    }

    if (status === 'authenticated' && AUTH_SCREENS.has(route.name)) {
      window.location.hash = readNextPath()
    }

    return undefined
  }, [status, route.name])

  const isPublic = PUBLIC_ROUTES.has(route.name)
  const waitingForSession = !isPublic && status !== 'authenticated'
  const leavingAuthScreen = AUTH_SCREENS.has(route.name) && status === 'authenticated'

  let page = <Homepage />
  if (waitingForSession) {
    page = <SessionGate label={status === 'guest' ? 'Sign in to continue…' : 'Checking your session…'} />
  } else if (leavingAuthScreen) {
    page = <SessionGate label="Opening your ledger…" />
  } else if (route.name === 'login') {
    page = <Login />
  } else if (route.name === 'register') {
    page = <Register />
  } else if (route.name === 'about') {
    page = <About />
  } else if (route.name === 'help') {
    page = <Help />
  } else if (route.name === 'birds') {
    page = <BirdsManagement />
  } else if (route.name === 'breeding') {
    page = <BreedingWorkflow />
  } else if (route.name === 'history') {
    page = <Predictions />
  } else if (route.name === 'computation') {
    page = <ComputationResult resultId={route.id} sectionId={route.section} />
  }

  return (
    <div className="site">
      <Navbar activeRoute={route.name} />
      {page}
    </div>
  )
}
