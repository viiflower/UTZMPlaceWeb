import { useEffect, useState } from 'react'
import { accountService } from '@/services'
import { useApp } from '@/context/AppContext'
import { formatCurrency, formatDate } from '@/utils/format'

export default function AccountPage() {
  const { session, refreshSession } = useApp()
  const [activePanel, setActivePanel] = useState('panel-cuenta')
  const [profile, setProfile] = useState({ full_name: '', email: '', store_name: '', seller_bio: '' })
  const [passwordForm, setPasswordForm] = useState({ current: '', next: '' })
  const [avatarFile, setAvatarFile] = useState(null)
  const [paymentMethods, setPaymentMethods] = useState([])
  const [transactions, setTransactions] = useState([])
  const [message, setMessage] = useState('')

  useEffect(() => {
    setProfile({
      full_name: session.user?.full_name || '',
      email: session.user?.email || '',
      store_name: session.user?.store_name || '',
      seller_bio: session.user?.seller_bio || '',
    })
  }, [session.user?.email, session.user?.full_name, session.user?.seller_bio, session.user?.store_name])

  useEffect(() => {
    accountService.getPaymentMethods().then((data) => setPaymentMethods(data.methods || []))
    accountService.getTransactions('buyer').then((data) => setTransactions(data.transactions || []))
  }, [])

  async function saveProfile() {
    try {
      await accountService.updateProfile(profile)
      if (avatarFile) {
        await accountService.uploadAvatar(avatarFile)
      }
      await refreshSession()
      setMessage('Cuenta actualizada correctamente.')
    } catch (error) {
      setMessage(error.message)
    }
  }

  async function updatePassword() {
    try {
      await accountService.updateProfile({
        ...profile,
        old_password: passwordForm.current,
        password: passwordForm.next,
      })
      setPasswordForm({ current: '', next: '' })
      setMessage('Contrasena actualizada correctamente.')
    } catch (error) {
      setMessage(error.message)
    }
  }

  async function removeMethod(id) {
    try {
      await accountService.deletePaymentMethod(id)
      const data = await accountService.getPaymentMethods()
      setPaymentMethods(data.methods || [])
    } catch (error) {
      setMessage(error.message)
    }
  }

  return (
    <main className="container" style={{ padding: '18px 0 34px' }}>
      <div className="account-shell">
        <aside className="account-nav" aria-label="Menu lateral de cuenta">
          <div className="account-nav__header">
            <div className="account-nav__avatar">
              <img src={session.user?.avatar_url || '/public/uploads/blank-profile.png'} alt="Avatar" />
            </div>
            <div className="account-nav__meta">
              <div className="account-nav__eyebrow">Perfil</div>
              <div className="account-nav__name">{session.user?.full_name || 'Invitado'}</div>
            </div>
          </div>
          <div className="account-nav__group">
            <p className="account-nav__title">Cuenta</p>
            <div className="account-nav__list">
              <button className={`account-nav__link ${activePanel === 'panel-cuenta' ? 'is-active' : ''}`} type="button" onClick={() => setActivePanel('panel-cuenta')}>
                Cuenta
              </button>
              <button className={`account-nav__link ${activePanel === 'panel-seguridad' ? 'is-active' : ''}`} type="button" onClick={() => setActivePanel('panel-seguridad')}>
                Contrasena y seguridad
              </button>
            </div>
          </div>
          <div className="account-nav__group">
            <p className="account-nav__title">Pagos y recompensas</p>
            <div className="account-nav__list">
              <button className={`account-nav__link ${activePanel === 'panel-pagos' ? 'is-active' : ''}`} type="button" onClick={() => setActivePanel('panel-pagos')}>
                Configuracion de pagos
              </button>
              <button className={`account-nav__link ${activePanel === 'panel-transacciones' ? 'is-active' : ''}`} type="button" onClick={() => setActivePanel('panel-transacciones')}>
                Transacciones
              </button>
            </div>
          </div>
        </aside>

        <section className="account-main">
          {message ? <div className="card" style={{ padding: 14, marginBottom: 16 }}>{message}</div> : null}

          {activePanel === 'panel-cuenta' ? (
            <div className="card account-card" style={{ padding: 18 }}>
              <h2>Perfil</h2>
              <div className="form-grid">
                <div className="form-group">
                  <label className="form-label">Nombre en pantalla</label>
                  <input className="form-control" value={profile.full_name} onChange={(event) => setProfile((current) => ({ ...current, full_name: event.target.value }))} />
                </div>
                <div className="form-group">
                  <label className="form-label">Correo electronico</label>
                  <input className="form-control" value={profile.email} onChange={(event) => setProfile((current) => ({ ...current, email: event.target.value }))} />
                </div>
                <div className="form-group">
                  <label className="form-label">Nombre de tienda</label>
                  <input className="form-control" value={profile.store_name} onChange={(event) => setProfile((current) => ({ ...current, store_name: event.target.value }))} />
                </div>
              </div>
              <div className="form-group">
                <label className="form-label">Descripcion del vendedor</label>
                <textarea className="form-control" rows="4" value={profile.seller_bio} onChange={(event) => setProfile((current) => ({ ...current, seller_bio: event.target.value }))} />
              </div>
              <div className="form-group">
                <label className="form-label">Foto de perfil</label>
                <input className="form-control" type="file" accept="image/*" onChange={(event) => setAvatarFile(event.target.files?.[0] || null)} />
              </div>
              <div className="form-actions">
                <button className="btn" type="button" onClick={saveProfile}>
                  Guardar cambios
                </button>
              </div>
            </div>
          ) : null}

          {activePanel === 'panel-seguridad' ? (
            <div className="card account-card" style={{ padding: 18 }}>
              <h2>Contrasena y seguridad</h2>
              <div className="form-grid">
                <div className="form-group">
                  <label className="form-label">Contrasena actual</label>
                  <input className="form-control" type="password" value={passwordForm.current} onChange={(event) => setPasswordForm((current) => ({ ...current, current: event.target.value }))} />
                </div>
                <div className="form-group">
                  <label className="form-label">Nueva contrasena</label>
                  <input className="form-control" type="password" value={passwordForm.next} onChange={(event) => setPasswordForm((current) => ({ ...current, next: event.target.value }))} />
                </div>
              </div>
              <div className="form-actions">
                <button className="btn" type="button" onClick={updatePassword}>
                  Actualizar contrasena
                </button>
              </div>
            </div>
          ) : null}

          {activePanel === 'panel-pagos' ? (
            <div className="card account-card" style={{ padding: 18 }}>
              <h2>Configuracion de pagos</h2>
              {paymentMethods.length ? (
                <div className="pm-list">
                  {paymentMethods.map((method) => (
                    <div key={method.id} className="pm-item">
                      <div>
                        <div className="pm-brand">{method.label}</div>
                        <div className="pm-mask">{method.last4 ? `**** **** **** ${method.last4}` : method.label}</div>
                      </div>
                      <button type="button" onClick={() => removeMethod(method.id)}>
                        Eliminar
                      </button>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="form-hint">Aun no tienes metodos guardados.</div>
              )}
            </div>
          ) : null}

          {activePanel === 'panel-transacciones' ? (
            <div className="card account-card" style={{ padding: 18 }}>
              <h2>Transacciones</h2>
              {transactions.length ? (
                <div className="transactions-scroll">
                  <table className="table table-transactions">
                    <thead>
                      <tr>
                        <th>Tipo</th>
                        <th>Metodo</th>
                        <th>Estado</th>
                        <th>Monto</th>
                        <th>Fecha</th>
                      </tr>
                    </thead>
                    <tbody>
                      {transactions.map((transaction) => (
                        <tr key={transaction.id}>
                          <td>{transaction.type_label}</td>
                          <td>{transaction.payment_method_label}</td>
                          <td>{transaction.status_label}</td>
                          <td>{`${transaction.flow === 'out' ? '-' : '+'} ${formatCurrency(transaction.amount_cents)}`}</td>
                          <td>{formatDate(transaction.paid_at, true)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="form-hint">No cuentas con transacciones.</div>
              )}
            </div>
          ) : null}
        </section>
      </div>
    </main>
  )
}
