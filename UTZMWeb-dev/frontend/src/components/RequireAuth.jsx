import { Navigate, useLocation } from 'react-router-dom'
import { useApp } from '@/context/AppContext'

export default function RequireAuth({ children }) {
  const { isLoggedIn, sessionReady } = useApp()
  const location = useLocation()

  if (!sessionReady) {
    return (
      <main className="container" style={{ padding: '24px 0' }}>
        <div className="card" style={{ padding: 20 }}>
          Cargando...
        </div>
      </main>
    )
  }

  if (!isLoggedIn) {
    const next = encodeURIComponent(`${location.pathname}${location.search}`)
    return <Navigate replace to={`/?login=1&next=${next}`} />
  }

  return children
}
