import { useEffect, useMemo, useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { CATEGORIES } from '@/config/categories'
import { useApp } from '@/context/AppContext'
import { formatCurrency } from '@/utils/format'
import AssistantWidget from './AssistantWidget'
import AuthModal from './AuthModal'

const FOOTER_SECTIONS = [
  {
    title: 'Pagos',
    links: [
      { href: '/metodos_pago.html', label: 'Métodos de pago' },
      { href: '/seguridad.html', label: 'Seguridad' },
      { href: '/facturacion.html', label: 'Facturación' },
    ],
  },
  {
    title: 'Compras',
    links: [
      { href: '/envios.html', label: 'Envíos' },
      { href: '/devoluciones.html', label: 'Devoluciones' },
      { href: '/reembolsos.html', label: 'Reembolsos' },
    ],
  },
  {
    title: 'Nosotros',
    links: [{ href: '/sobre_nosotros.html', label: 'Sobre nosotros' }],
  },
  {
    title: 'Ayuda',
    links: [
      { href: '/centro_de_ayuda.html', label: 'Centro de ayuda' },
      { href: '/contacto.html', label: 'Contacto' },
      { href: '/terminos_privacidad.html', label: 'Términos y privacidad' },
    ],
  },
]

function Header() {
  const navigate = useNavigate()
  const location = useLocation()
  const [searchParams] = useSearchParams()
  const {
    cartItems,
    favorites,
    isLoggedIn,
    isSupport,
    openAuth,
    logout,
    session,
    removeFromCart,
    setCartQuantity,
    theme,
    setTheme,
  } = useApp()
  const [isCartOpen, setIsCartOpen] = useState(false)
  const [isProfileOpen, setIsProfileOpen] = useState(false)
  const [isFavoritesOpen, setIsFavoritesOpen] = useState(false)
  const [searchText, setSearchText] = useState(searchParams.get('q') || '')

  const cartTotal = useMemo(
    () => cartItems.reduce((sum, item) => sum + Number(item.price_cents) * Number(item.qty), 0),
    [cartItems],
  )

  useEffect(() => {
    setIsProfileOpen(false)
  }, [isLoggedIn])

  useEffect(() => {
    function handlePointerDown(event) {
      if (!event.target.closest('.profile')) {
        setIsProfileOpen(false)
      }
    }

    document.addEventListener('mousedown', handlePointerDown)
    return () => document.removeEventListener('mousedown', handlePointerDown)
  }, [])

  function submitSearch(event) {
    event.preventDefault()
    const params = new URLSearchParams()
    if (searchText.trim()) {
      params.set('q', searchText.trim())
    }
    navigate(`/${params.toString() ? `?${params}` : ''}`)
  }

  function applyCategory(categoryId) {
    const params = new URLSearchParams()
    const currentQuery = searchText.trim()
    if (currentQuery) params.set('q', currentQuery)
    params.set('category', String(categoryId))
    navigate(`/?${params}`)
  }

  function closeProfileMenu() {
    setIsProfileOpen(false)
  }

  function closeFavoritesMenu() {
    setIsFavoritesOpen(false)
  }

  function handleAuthMenu(mode) {
    closeProfileMenu()
    openAuth(mode)
  }

  function handleLogout() {
    closeProfileMenu()
    logout()
  }

  return (
    <header className="header">
      <div className="topbar container">
        <Link className="logo" to="/" aria-label="Ir al inicio">
          <svg viewBox="0 0 48 48" aria-hidden="true">
            <defs>
              <clipPath id="logoClip">
                <circle cx="24" cy="24" r="22" />
              </clipPath>
            </defs>
            <image
              href="/public/uploads/logo.jpeg"
              x="2"
              y="2"
              width="44"
              height="44"
              preserveAspectRatio="xMidYMid slice"
              clipPath="url(#logoClip)"
            />
            <circle cx="24" cy="24" r="22" fill="none" stroke="#38bdf8" strokeWidth="2" />
          </svg>
          <span>Utzmplace</span>
        </Link>

        <form className="search" onSubmit={submitSearch} role="search" aria-label="Buscar productos">
          <input
            id="searchInput"
            type="search"
            placeholder="Buscar productos, marcas y más"
            aria-label="Buscar"
            value={searchText}
            onChange={(event) => setSearchText(event.target.value)}
          />
          <button type="submit" aria-label="Buscar">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79L20 21.5 21.5 20l-6-6zM9.5 14C7 14 5 12 5 9.5S7 5 9.5 5 14 7 14 9.5 12 14 9.5 14z" />
            </svg>
          </button>
        </form>

        <nav className="actions" aria-label="Acciones de usuario">
          {isLoggedIn ? (
            <>
              <NavLink className="link" to="/mensajes.html">
                Mensajes
              </NavLink>
              <NavLink className="link" to="/pedidos.html">
                Pedidos
              </NavLink>
            </>
          ) : null}

          <button
            className="theme-toggle"
            id="themeToggle"
            type="button"
            onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
          >
            <span className="theme-toggle__label">
              {theme === 'dark' ? 'Modo claro' : 'Modo oscuro'}
            </span>
            <span className="theme-toggle__knob" aria-hidden="true" />
          </button>

          <div
            className="icon-btn favorites-toggle"
            onClick={() => setIsFavoritesOpen((current) => !current)}
            role="button"
            tabIndex={0}
            aria-label="Favoritos"
          >
            ♥
            {favorites.length ? <span className="badge">{favorites.length}</span> : null}
            <div className={`cart-menu${isFavoritesOpen ? ' open' : ''}`} onMouseLeave={closeFavoritesMenu}>
              {favorites.length ? (
                <div className="cart-list">
                  {favorites.map((item) => (
                    <div key={item.product_id} className="cart-item">
                      <Link className="cart-name" to={`/producto.html?id=${item.product_id}`}>
                        {item.name}
                      </Link>
                      <div className="cart-price">{formatCurrency(item.price_cents)}</div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="cart-empty">No tienes favoritos</div>
              )}
            </div>
          </div>

          <div
            id="cartBtn"
            className="icon-btn"
            role="button"
            aria-label="Carrito"
            aria-haspopup="menu"
            aria-expanded={isCartOpen}
            tabIndex={0}
            onClick={() => setIsCartOpen((current) => !current)}
          >
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-1.99.9-1.99 2S15.9 22 17 22s2-.9 2-2-.9-2-2-2zM7.16 14h9.45c.75 0 1.4-.41 1.73-1.03l3.58-6.49A1 1 0 0 0 21 5H6.21l-.94-2H1v2h2l3.6 7.59-1.35 2.44C4.52 15.37 5.48 17 7 17h12v-2H7.42l.74-1z" />
            </svg>
            {cartItems.length ? <span className="badge">{cartItems.length}</span> : null}
            <div className={`cart-menu${isCartOpen ? ' open' : ''}`} role="menu" aria-label="Carrito">
              {cartItems.length ? (
                <>
                  <div className="cart-list">
                    {cartItems.map((item) => (
                      <div key={item.product_id} className="cart-item">
                        <div className="cart-line">
                          <Link className="cart-name" to={`/producto.html?id=${item.product_id}`}>
                            {item.name}
                          </Link>
                          <button
                            className="cart-remove"
                            type="button"
                            onClick={(event) => {
                              event.stopPropagation()
                              removeFromCart(item.product_id)
                            }}
                          >
                            &times;
                          </button>
                        </div>
                        <div className="cart-qty-row">
                          <input
                            className="cart-qty-input"
                            type="number"
                            min="1"
                            max="99"
                            value={item.qty}
                            onChange={(event) => {
                              event.stopPropagation()
                              setCartQuantity(item.product_id, event.target.value)
                            }}
                          />
                          <div className="cart-price">
                            {formatCurrency(Number(item.price_cents) * Number(item.qty))}
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                  <div className="cart-total">
                    <span>Total</span>
                    <strong>{formatCurrency(cartTotal)}</strong>
                  </div>
                  <Link className="btn btn-pay" id="payBtn" to="/pagar.html">
                    Pagar
                  </Link>
                </>
              ) : (
                <div className="cart-empty">No hay productos en el carrito</div>
              )}
            </div>
          </div>

          <div className="profile" aria-label="Menú de perfil">
            <button
              id="profileBtn"
              className="profile-btn"
              type="button"
              aria-haspopup="menu"
              aria-expanded={isProfileOpen}
              onClick={() => setIsProfileOpen((current) => !current)}
            >
              <img
                id="avatarImg"
                src={session.user?.avatar_url || '/public/uploads/blank-profile.png'}
                alt="Avatar"
              />
              <span id="profileLabel">
                {isLoggedIn ? session.user?.full_name || session.user?.email : 'Iniciar sesión'}
              </span>
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 10l5 5 5-5z" />
              </svg>
            </button>
            <ul id="profileMenu" className={`profile-menu${isProfileOpen ? ' open' : ''}`} role="menu" onMouseLeave={closeProfileMenu}>
              {!isLoggedIn ? (
                <>
                  <li role="menuitem">
                    <button className="menu-link" type="button" onClick={() => handleAuthMenu('login')}>
                      Ingresar
                    </button>
                  </li>
                  <li role="menuitem">
                    <button className="menu-link" type="button" onClick={() => handleAuthMenu('register')}>
                      Crear cuenta
                    </button>
                  </li>
                </>
              ) : (
                <>
                  <li role="menuitem">
                    <Link to="/mis_publicaciones.html" onClick={closeProfileMenu}>Mis publicaciones</Link>
                  </li>
                  <li role="menuitem">
                    <Link to="/cuenta.html" onClick={closeProfileMenu}>Cuenta</Link>
                  </li>
                  <li role="menuitem">
                    <Link to="/soporte.html" onClick={closeProfileMenu}>{isSupport ? 'Bandeja de soporte' : 'Soporte'}</Link>
                  </li>
                  <li role="menuitem">
                    <Link to="/vender.html" onClick={closeProfileMenu}>Vender</Link>
                  </li>
                  <li role="menuitem">
                    <button className="menu-link" type="button" onClick={handleLogout}>
                      Cerrar sesión
                    </button>
                  </li>
                </>
              )}
            </ul>
          </div>
        </nav>
      </div>

      <nav id="catNav" className="categories" aria-label="Categorías">
        <ul className="container">
          {CATEGORIES.map((category) => (
            <li key={category.id} className="cat">
              <button
                className={`cat-btn${searchParams.get('category') === String(category.id) && location.pathname === '/' ? ' is-active' : ''}`}
                type="button"
                onClick={() => applyCategory(category.id)}
              >
                {category.label}
              </button>
            </li>
          ))}
        </ul>
      </nav>
    </header>
  )
}

function Footer() {
  return (
    <footer className="footer">
      <div className="container footer-grid">
        {FOOTER_SECTIONS.map((section) => (
          <div key={section.title}>
            <h4>{section.title}</h4>
            {section.links.map((link) => (
              <Link key={link.href} to={link.href}>
                {link.label}
              </Link>
            ))}
          </div>
        ))}
      </div>
      <div className="copy">&copy; {new Date().getFullYear()} Utzmplace</div>
    </footer>
  )
}

export default function Shell() {
  return (
    <div className="react-app-shell">
      <Header />
      <Outlet />
      <Footer />
      <AssistantWidget />
      <AuthModal />
    </div>
  )
}
