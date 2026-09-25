import { useEffect, useState } from 'react'
import agaporaIcon from '../../assets/icons/AGAPORA-icon.png'
import './Navbar.css'

const DESKTOP_BREAKPOINT = 1060

const links = [
  { label: 'Home', href: '#home', route: 'home' },
  { label: 'About', href: '#about', route: 'about' },
  { label: 'Help', href: '#help', route: 'help' },
  { label: 'Birds', href: '#birds', route: 'birds' },
  { label: 'Computation History', href: '#computation-history', route: 'history' },
]

export default function Navbar({ activeRoute = 'home' }) {
  const [scrolled, setScrolled] = useState(false)
  const [menuOpen, setMenuOpen] = useState(false)

  useEffect(() => {
    const handleScroll = () => setScrolled(window.scrollY > 12)
    handleScroll()
    window.addEventListener('scroll', handleScroll, { passive: true })
    return () => window.removeEventListener('scroll', handleScroll)
  }, [])

  useEffect(() => {
    const handleResize = () => {
      if (window.innerWidth >= DESKTOP_BREAKPOINT) setMenuOpen(false)
    }
    window.addEventListener('resize', handleResize)
    return () => window.removeEventListener('resize', handleResize)
  }, [])

  useEffect(() => {
    if (!menuOpen) return undefined
    const handleKeyDown = (event) => {
      if (event.key === 'Escape') setMenuOpen(false)
    }
    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [menuOpen])

  const handleCloseMenu = () => setMenuOpen(false)
  const handleToggleMenu = () => setMenuOpen((open) => !open)

  const isDark =
    activeRoute === 'home' ||
    activeRoute === 'birds' ||
    activeRoute === 'history' ||
    activeRoute === 'breeding'

  return (
    <header
      className={`navbar${isDark ? ' navbar--dark' : ''}${scrolled ? ' is-scrolled' : ''}${menuOpen ? ' is-open' : ''}`}
    >
      <div className="navbar__inner">
        <a className="navbar__brand" href="#home" onClick={handleCloseMenu} aria-label="AGAPORA home">
          <span className="navbar__brand-mark">
            <img className="navbar__brand-icon" src={agaporaIcon} alt="" width={64} height={64} />
          </span>
          <span className="navbar__brand-text">AGAPORA</span>
        </a>

        <nav id="primary-navigation" className="navbar__nav" aria-label="Primary">
          <ul className="navbar__links">
            {links.map((link) => (
              <li key={link.route}>
                <a
                  href={link.href}
                  className={`navbar__link${link.route === activeRoute ? ' is-active' : ''}`}
                  aria-current={link.route === activeRoute ? 'page' : undefined}
                  onClick={handleCloseMenu}
                >
                  {link.label}
                </a>
              </li>
            ))}
          </ul>

          <a
            className={`navbar__cta${activeRoute === 'breeding' ? ' is-active' : ''}`}
            href="#breeding"
            onClick={handleCloseMenu}
          >
            Start Breeding
            <span className="navbar__cta-arrow" aria-hidden="true">
              →
            </span>
          </a>
        </nav>

        <button
          type="button"
          className="navbar__toggle"
          aria-label={menuOpen ? 'Close menu' : 'Open menu'}
          aria-expanded={menuOpen}
          aria-controls="primary-navigation"
          onClick={handleToggleMenu}
        >
          <span className="navbar__toggle-lines" />
        </button>
      </div>

      {menuOpen ? <button type="button" className="navbar__scrim" aria-label="Close menu" onClick={handleCloseMenu} /> : null}
    </header>
  )
}
