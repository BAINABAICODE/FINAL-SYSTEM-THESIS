import { useEffect, useState } from 'react'
import { useAuth } from '../../auth/AuthContext'
import agaporaIcon from '../../assets/icons/AGAPORA-icon.png'
import './Navbar.css'

const DESKTOP_BREAKPOINT = 1060
const SIGNED_IN_DESKTOP_BREAKPOINT = 1360

const publicLinks = [
  { label: 'Home', href: '#home', route: 'home' },
  { label: 'About', href: '#about', route: 'about' },
  { label: 'Help', href: '#help', route: 'help' },
]

const privateLinks = [
  { label: 'Birds', href: '#birds', route: 'birds' },
  { label: 'History', href: '#computation-history', route: 'history' },
]

function accountInitial(name) {
  const trimmed = String(name ?? '').trim()
  return trimmed ? trimmed.charAt(0).toUpperCase() : 'A'
}

export default function Navbar({ activeRoute = 'home' }) {
  const { status, user, logout } = useAuth()
  const [scrolled, setScrolled] = useState(false)
  const [menuOpen, setMenuOpen] = useState(false)
  const isAuthenticated = status === 'authenticated'
  const links = isAuthenticated ? [...publicLinks, ...privateLinks] : publicLinks

  useEffect(() => {
    const handleScroll = () => setScrolled(window.scrollY > 12)
    handleScroll()
    window.addEventListener('scroll', handleScroll, { passive: true })
    return () => window.removeEventListener('scroll', handleScroll)
  }, [])

  useEffect(() => {
    const desktopBreakpoint = isAuthenticated ? SIGNED_IN_DESKTOP_BREAKPOINT : DESKTOP_BREAKPOINT
    const handleResize = () => {
      if (window.innerWidth >= desktopBreakpoint) setMenuOpen(false)
    }
    handleResize()
    window.addEventListener('resize', handleResize)
    return () => window.removeEventListener('resize', handleResize)
  }, [isAuthenticated])

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
  const handleLogout = () => {
    handleCloseMenu()
    logout()
  }

  const isDark =
    activeRoute === 'home' ||
    activeRoute === 'login' ||
    activeRoute === 'register' ||
    activeRoute === 'birds' ||
    activeRoute === 'history' ||
    activeRoute === 'breeding'

  return (
    <header
      className={`navbar${isDark ? ' navbar--dark' : ''}${isAuthenticated ? ' navbar--signed-in' : ''}${scrolled ? ' is-scrolled' : ''}${menuOpen ? ' is-open' : ''}`}
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

          {status === 'loading' ? null : (
            <a
              className={`navbar__cta${activeRoute === 'breeding' ? ' is-active' : ''}`}
              href={isAuthenticated ? '#breeding' : '#login'}
              onClick={handleCloseMenu}
            >
              {isAuthenticated ? 'Start Breeding' : 'Sign in'}
              <span className="navbar__cta-arrow" aria-hidden="true">
                →
              </span>
            </a>
          )}

          {status === 'loading' ? null : (
            <div className="navbar__account">
              {isAuthenticated ? (
                <>
                  <span className="navbar__avatar" aria-hidden="true">
                    {accountInitial(user?.name)}
                  </span>
                  <span className="navbar__user">{user?.name}</span>
                  <button type="button" className="navbar__logout" onClick={handleLogout}>
                    Log out
                  </button>
                </>
              ) : (
                <a className="navbar__link" href="#register" onClick={handleCloseMenu}>
                  Register
                </a>
              )}
            </div>
          )}
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
