import { useEffect, useState } from 'react'
import Navbar from './components/Navbar/Navbar.jsx'
import Homepage from './components/Homepage/Homepage.jsx'
import About from './components/About/About.jsx'
import Help from './components/Help/Help.jsx'
import BirdsManagement from './components/BirdsManagement/BirdsManagement.jsx'
import BreedingWorkflow from './components/Breeding/BreedingWorkflow.jsx'
import Predictions from './components/Predictions/Predictions.jsx'
import ComputationResult from './components/Computation/ComputationResult.jsx'
import './App.css'

function getRouteFromHash() {
  const hash = window.location.hash.replace(/^#/, '').split('?')[0]
  const computation = hash.match(/^computation\/(\d+)(?:\/([a-z0-9-]+))?$/)
  if (computation) {
    return { name: 'computation', id: computation[1], section: computation[2] || 'flow' }
  }
  if (hash === 'about' || hash.startsWith('about')) return { name: 'about' }
  if (hash === 'help' || hash.startsWith('help')) return { name: 'help' }
  if (hash === 'birds' || hash.startsWith('birds')) return { name: 'birds' }
  if (hash === 'breeding' || hash.startsWith('breeding')) return { name: 'breeding' }
  if (hash.startsWith('computation-history') || hash.startsWith('predictions')) return { name: 'history' }
  return { name: 'home' }
}

export default function App() {
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

  return (
    <div className="site">
      <Navbar activeRoute={route.name} />
      {route.name === 'about' ? (
        <About />
      ) : route.name === 'help' ? (
        <Help />
      ) : route.name === 'birds' ? (
        <BirdsManagement />
      ) : route.name === 'breeding' ? (
        <BreedingWorkflow />
      ) : route.name === 'history' ? (
        <Predictions />
      ) : route.name === 'computation' ? (
        <ComputationResult resultId={route.id} sectionId={route.section} />
      ) : (
        <Homepage />
      )}
    </div>
  )
}
