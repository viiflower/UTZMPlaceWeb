import { useEffect, useRef } from 'react'
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom'
import Shell from '@/components/Shell'
import RequireAuth from '@/components/RequireAuth'
import { AppProvider, useApp } from '@/context/AppContext'
import AccountPage from '@/pages/AccountPage'
import CheckoutPage from '@/pages/CheckoutPage'
import HomePage from '@/pages/HomePage'
import InfoPage from '@/pages/InfoPage'
import MessagesPage from '@/pages/MessagesPage'
import MyProductsPage from '@/pages/MyProductsPage'
import OrdersPage from '@/pages/OrdersPage'
import ProductPage from '@/pages/ProductPage'
import PublicProfilePage from '@/pages/PublicProfilePage'
import SellPage from '@/pages/SellPage'
import SupportPage from '@/pages/SupportPage'

function QueryAuthWatcher() {
  const location = useLocation()
  const { openAuth } = useApp()
  const handledLoginSearchRef = useRef('')

  useEffect(() => {
    const params = new URLSearchParams(location.search)
    if (params.get('login') === '1' && handledLoginSearchRef.current !== location.search) {
      handledLoginSearchRef.current = location.search
      openAuth('login')
      return
    }

    if (params.get('login') !== '1') {
      handledLoginSearchRef.current = ''
    }
  }, [location.search, openAuth])

  return null
}

function ProtectedRoute({ children }) {
  return <RequireAuth>{children}</RequireAuth>
}

export default function App() {
  return (
    <BrowserRouter basename={import.meta.env.BASE_URL}>
      <AppProvider>
        <QueryAuthWatcher />
        <Routes>
          <Route element={<Shell />}>
            <Route index element={<HomePage />} />
            <Route path="index.html" element={<Navigate replace to="/" />} />
            <Route path="producto.html" element={<ProductPage />} />
            <Route path="perfil.html" element={<PublicProfilePage />} />
            <Route path="cuenta.html" element={<ProtectedRoute><AccountPage /></ProtectedRoute>} />
            <Route path="mis_publicaciones.html" element={<ProtectedRoute><MyProductsPage /></ProtectedRoute>} />
            <Route path="vender.html" element={<ProtectedRoute><SellPage /></ProtectedRoute>} />
            <Route path="pagar.html" element={<ProtectedRoute><CheckoutPage /></ProtectedRoute>} />
            <Route path="pedidos.html" element={<ProtectedRoute><OrdersPage /></ProtectedRoute>} />
            <Route path="mensajes.html" element={<ProtectedRoute><MessagesPage /></ProtectedRoute>} />
            <Route path="soporte.html" element={<ProtectedRoute><SupportPage /></ProtectedRoute>} />
            <Route path=":slug.html" element={<InfoPage />} />
            <Route path="*" element={<Navigate replace to="/" />} />
          </Route>
        </Routes>
      </AppProvider>
    </BrowserRouter>
  )
}
