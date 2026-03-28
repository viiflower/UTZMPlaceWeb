import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { orderService, supportService } from '@/services'
import { useApp } from '@/context/AppContext'
import { formatDate } from '@/utils/format'

const CATEGORY_OPTIONS = [
  { value: 'pagos', label: 'Pagos' },
  { value: 'pedido', label: 'Pedidos' },
  { value: 'envio', label: 'Envíos y entregas' },
  { value: 'cuenta', label: 'Cuenta y acceso' },
  { value: 'publicacion', label: 'Publicaciones' },
  { value: 'seguridad', label: 'Seguridad' },
  { value: 'otro', label: 'Otro' },
]

const STATUS_LABELS = {
  open: 'Abierto',
  in_progress: 'En progreso',
  waiting_user: 'En espera del usuario',
  resolved: 'Resuelto',
  closed: 'Cerrado',
}

const PRIORITY_LABELS = {
  low: 'Baja',
  normal: 'Normal',
  high: 'Alta',
  urgent: 'Urgente',
}

const ROLE_LABELS = {
  customer: 'Cliente',
  support: 'Soporte',
  admin: 'Administrador',
}

function statusLabel(value) {
  return STATUS_LABELS[value] || value
}

function priorityLabel(value) {
  return PRIORITY_LABELS[value] || value
}

function categoryLabel(value) {
  return CATEGORY_OPTIONS.find((option) => option.value === value)?.label || value
}

function roleLabel(value) {
  return ROLE_LABELS[value] || value
}

export default function SupportPage() {
  const { session, isSupport, isAdmin } = useApp()
  const [tickets, setTickets] = useState([])
  const [selectedTicketId, setSelectedTicketId] = useState(null)
  const [detail, setDetail] = useState(null)
  const [orders, setOrders] = useState([])
  const [agents, setAgents] = useState([])
  const [createForm, setCreateForm] = useState({
    category: 'pagos',
    subject: '',
    description: '',
    order_id: '',
  })
  const [filters, setFilters] = useState({
    status: 'all',
    assignment: 'all',
    q: '',
  })
  const [reply, setReply] = useState('')
  const [staffForm, setStaffForm] = useState({
    status: 'open',
    priority: 'normal',
    assigned_to: '',
  })
  const [roleForm, setRoleForm] = useState({
    email: '',
    role: 'support',
  })
  const [message, setMessage] = useState('')
  const [loadingTickets, setLoadingTickets] = useState(false)
  const [loadingDetail, setLoadingDetail] = useState(false)

  const currentUserId = Number(session.user?.id)

  useEffect(() => {
    document.title = isSupport ? 'Bandeja de soporte | Utzmplace' : 'Soporte | Utzmplace'
  }, [isSupport])

  async function loadTickets() {
    setLoadingTickets(true)
    try {
      const data = await supportService.getTickets(isSupport ? filters : {})
      const nextTickets = Array.isArray(data.tickets) ? data.tickets : []
      setTickets(nextTickets)
      setSelectedTicketId((current) => {
        if (current && nextTickets.some((ticket) => ticket.id === current)) {
          return current
        }
        return nextTickets[0]?.id || null
      })
    } catch (error) {
      setMessage(error.message)
    } finally {
      setLoadingTickets(false)
    }
  }

  useEffect(() => {
    loadTickets()
  }, [filters.assignment, filters.q, filters.status, isSupport])

  useEffect(() => {
    if (isSupport) {
      supportService.getAgents().then((data) => setAgents(data.agents || [])).catch(() => {})
      return
    }

    orderService.getOrders(30).then((data) => setOrders(Array.isArray(data) ? data : [])).catch(() => {})
  }, [isSupport])

  async function loadTicketDetail(ticketId) {
    if (!ticketId) {
      setDetail(null)
      return
    }

    setLoadingDetail(true)
    try {
      const data = await supportService.getTicket(ticketId)
      setDetail(data)
      setStaffForm((current) => ({
        status: data.ticket?.status || current.status,
        priority: data.ticket?.priority || current.priority,
        assigned_to: data.ticket?.assigned_to ? String(data.ticket.assigned_to) : '',
      }))
    } catch (error) {
      setMessage(error.message)
    } finally {
      setLoadingDetail(false)
    }
  }

  useEffect(() => {
    loadTicketDetail(selectedTicketId)
  }, [selectedTicketId])

  async function createTicket() {
    try {
      const payload = {
        category: createForm.category,
        subject: createForm.subject,
        description: createForm.description,
        order_id: createForm.order_id ? Number(createForm.order_id) : null,
      }

      const result = await supportService.createTicket(payload)
      setCreateForm({
        category: 'pagos',
        subject: '',
        description: '',
        order_id: '',
      })
      setMessage('Ticket creado correctamente.')
      await loadTickets()
      setSelectedTicketId(result.ticket_id)
    } catch (error) {
      setMessage(error.message)
    }
  }

  async function sendReply() {
    if (!detail?.ticket?.id) {
      return
    }

    try {
      await supportService.sendMessage({
        ticket_id: detail.ticket.id,
        body: reply,
      })
      setReply('')
      await Promise.all([loadTicketDetail(detail.ticket.id), loadTickets()])
    } catch (error) {
      setMessage(error.message)
    }
  }

  async function saveTicketMeta() {
    if (!detail?.ticket?.id) {
      return
    }

    try {
      await supportService.updateTicket({
        ticket_id: detail.ticket.id,
        status: staffForm.status,
        priority: staffForm.priority,
        assigned_to: staffForm.assigned_to ? Number(staffForm.assigned_to) : null,
      })
      setMessage('Ticket actualizado.')
      await Promise.all([loadTicketDetail(detail.ticket.id), loadTickets()])
    } catch (error) {
      setMessage(error.message)
    }
  }

  async function assignToMe() {
    if (!detail?.ticket?.id) {
      return
    }

    try {
      await supportService.updateTicket({
        ticket_id: detail.ticket.id,
        assigned_to: currentUserId,
      })
      setMessage('Ticket asignado a tu bandeja.')
      await Promise.all([
        loadTicketDetail(detail.ticket.id),
        loadTickets(),
        supportService.getAgents().then((data) => setAgents(data.agents || [])),
      ])
    } catch (error) {
      setMessage(error.message)
    }
  }

  async function updateSupportRole() {
    try {
      await supportService.updateStaffRole(roleForm)
      setRoleForm({ email: '', role: 'support' })
      setMessage('Rol actualizado correctamente.')
      const data = await supportService.getAgents()
      setAgents(data.agents || [])
    } catch (error) {
      setMessage(error.message)
    }
  }

  const selectedTicket = detail?.ticket || null
  const selectedMessages = detail?.messages || []

  const orderOptions = useMemo(() => {
    return orders.map((order) => ({
      id: order.id,
      label: `Pedido #${order.id} • ${order.status}`,
    }))
  }, [orders])

  return (
    <main className="container support-page">
      <div className="support-page__head">
        <div>
          <h1>{isSupport ? 'Bandeja de soporte' : 'Soporte'}</h1>
          <p className="form-hint">
            {isSupport
              ? 'Gestiona tickets, responde usuarios y organiza la asignación del equipo.'
              : 'Abre un ticket para recibir ayuda humana y dar seguimiento a tu caso.'}
          </p>
        </div>
        {!isSupport ? (
          <Link className="btn" to="/centro_de_ayuda.html">
            Ver ayuda rápida
          </Link>
        ) : null}
      </div>

      {message ? <div className="card" style={{ padding: 14 }}>{message}</div> : null}

      <div className="support-layout">
        <aside className="support-sidebar card">
          <div className="support-sidebar__head">
            <div>
              <h2>{isSupport ? 'Tickets' : 'Mis tickets'}</h2>
              <p className="form-hint">{tickets.length} registros</p>
            </div>
            {isSupport ? (
              <div className="support-filters">
                <select
                  className="form-control"
                  value={filters.status}
                  onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))}
                >
                  <option value="all">Todos los estados</option>
                  <option value="open">Abiertos</option>
                  <option value="in_progress">En progreso</option>
                  <option value="waiting_user">En espera del usuario</option>
                  <option value="resolved">Resueltos</option>
                  <option value="closed">Cerrados</option>
                </select>
                <select
                  className="form-control"
                  value={filters.assignment}
                  onChange={(event) => setFilters((current) => ({ ...current, assignment: event.target.value }))}
                >
                  <option value="all">Toda la bandeja</option>
                  <option value="mine">Asignados a mí</option>
                  <option value="unassigned">Sin asignar</option>
                </select>
                <input
                  className="form-control"
                  placeholder="Buscar asunto o usuario"
                  value={filters.q}
                  onChange={(event) => setFilters((current) => ({ ...current, q: event.target.value }))}
                />
              </div>
            ) : null}
          </div>

          <div className="support-ticket-list">
            {loadingTickets ? (
              <div className="support-empty">Cargando tickets...</div>
            ) : tickets.length ? (
              tickets.map((ticket) => (
                <button
                  key={ticket.id}
                  className={`support-ticket-item${selectedTicketId === ticket.id ? ' is-active' : ''}`}
                  type="button"
                  onClick={() => setSelectedTicketId(ticket.id)}
                >
                  <div className="support-ticket-item__head">
                    <strong>{ticket.subject}</strong>
                    <span className={`support-pill support-pill--${ticket.status}`}>{statusLabel(ticket.status)}</span>
                  </div>
                  <div className="support-ticket-item__meta">
                    <span>{priorityLabel(ticket.priority)}</span>
                    <span>{formatDate(ticket.last_message_at, true)}</span>
                  </div>
                  <div className="support-ticket-item__sub">
                    {isSupport
                      ? `${ticket.requester_name} • ${categoryLabel(ticket.category)}`
                      : categoryLabel(ticket.category)}
                  </div>
                </button>
              ))
            ) : (
              <div className="support-empty">
                {isSupport ? 'No hay tickets para estos filtros.' : 'Aún no has creado tickets.'}
              </div>
            )}
          </div>
        </aside>

        <section className="support-main">
          {!isSupport ? (
            <div className="card support-create-card">
              <h2>Abrir ticket</h2>
              <div className="form-grid">
                <div className="form-group">
                  <label className="form-label">Categoría</label>
                  <select
                    className="form-control"
                    value={createForm.category}
                    onChange={(event) => setCreateForm((current) => ({ ...current, category: event.target.value }))}
                  >
                    {CATEGORY_OPTIONS.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="form-group">
                  <label className="form-label">Pedido relacionado</label>
                  <select
                    className="form-control"
                    value={createForm.order_id}
                    onChange={(event) => setCreateForm((current) => ({ ...current, order_id: event.target.value }))}
                  >
                    <option value="">Sin pedido relacionado</option>
                    {orderOptions.map((option) => (
                      <option key={option.id} value={option.id}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="form-group">
                  <label className="form-label">Asunto</label>
                  <input
                    className="form-control"
                    value={createForm.subject}
                    onChange={(event) => setCreateForm((current) => ({ ...current, subject: event.target.value }))}
                  />
                </div>
                <div className="form-group">
                  <label className="form-label">Describe tu problema</label>
                  <textarea
                    className="form-control"
                    rows="4"
                    value={createForm.description}
                    onChange={(event) => setCreateForm((current) => ({ ...current, description: event.target.value }))}
                  />
                </div>
              </div>
              <div className="form-actions">
                <button className="btn" type="button" onClick={createTicket}>
                  Crear ticket
                </button>
              </div>
            </div>
          ) : null}

          {isSupport && selectedTicket ? (
            <div className="card support-manage-card">
              <div className="support-manage-card__head">
                <div>
                  <h2>Gestionar ticket #{selectedTicket.id}</h2>
                  <p className="form-hint">
                    Solicitante: {selectedTicket.requester_name} • {selectedTicket.requester_email}
                  </p>
                </div>
                {selectedTicket.assigned_to !== currentUserId ? (
                  <button className="btn" type="button" onClick={assignToMe}>
                    Asignarme
                  </button>
                ) : null}
              </div>
              <div className="support-manage-grid">
                <div className="form-group">
                  <label className="form-label">Estado</label>
                  <select
                    className="form-control"
                    value={staffForm.status}
                    onChange={(event) => setStaffForm((current) => ({ ...current, status: event.target.value }))}
                  >
                    {Object.keys(STATUS_LABELS).map((status) => (
                      <option key={status} value={status}>
                        {statusLabel(status)}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="form-group">
                  <label className="form-label">Prioridad</label>
                  <select
                    className="form-control"
                    value={staffForm.priority}
                    onChange={(event) => setStaffForm((current) => ({ ...current, priority: event.target.value }))}
                  >
                    {Object.keys(PRIORITY_LABELS).map((priority) => (
                      <option key={priority} value={priority}>
                        {priorityLabel(priority)}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="form-group">
                  <label className="form-label">Asignado a</label>
                  <select
                    className="form-control"
                    value={staffForm.assigned_to}
                    onChange={(event) => setStaffForm((current) => ({ ...current, assigned_to: event.target.value }))}
                  >
                    <option value="">Sin asignar</option>
                    {agents.map((agent) => (
                      <option key={agent.id} value={agent.id}>
                        {agent.full_name} • {roleLabel(agent.role)}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
              <div className="form-actions">
                <button className="btn" type="button" onClick={saveTicketMeta}>
                  Guardar cambios
                </button>
              </div>
            </div>
          ) : null}

          {isAdmin ? (
            <div className="card support-admin-card">
              <h2>Registrar usuarios de soporte</h2>
              <p className="form-hint">
                Promueve cuentas existentes por correo. El primer administrador debe quedar asignado directamente en base de datos.
              </p>
              <div className="support-admin-grid">
                <div className="form-group">
                  <label className="form-label">Correo del usuario</label>
                  <input
                    className="form-control"
                    value={roleForm.email}
                    onChange={(event) => setRoleForm((current) => ({ ...current, email: event.target.value }))}
                  />
                </div>
                <div className="form-group">
                  <label className="form-label">Nuevo rol</label>
                  <select
                    className="form-control"
                    value={roleForm.role}
                    onChange={(event) => setRoleForm((current) => ({ ...current, role: event.target.value }))}
                  >
                    <option value="support">Soporte</option>
                    <option value="admin">Administrador</option>
                    <option value="customer">Cliente</option>
                  </select>
                </div>
              </div>
              <div className="form-actions">
                <button className="btn" type="button" onClick={updateSupportRole}>
                  Actualizar rol
                </button>
              </div>
            </div>
          ) : null}

          <div className="card support-thread-card">
            {loadingDetail ? (
              <div className="support-empty">Cargando conversación...</div>
            ) : selectedTicket ? (
              <>
                <div className="support-thread-card__head">
                  <div>
                    <h2>{selectedTicket.subject}</h2>
                    <p className="form-hint">
                      {categoryLabel(selectedTicket.category)} • {statusLabel(selectedTicket.status)} • {priorityLabel(selectedTicket.priority)}
                    </p>
                    {selectedTicket.order_id ? (
                      <p className="form-hint">Pedido asociado: #{selectedTicket.order_id}</p>
                    ) : null}
                  </div>
                  <div className="support-thread-card__badges">
                    <span className={`support-pill support-pill--${selectedTicket.status}`}>{statusLabel(selectedTicket.status)}</span>
                    <span className="support-priority">{priorityLabel(selectedTicket.priority)}</span>
                  </div>
                </div>

                <div className="support-thread-scroll">
                  <div className="support-summary">
                    <strong>Descripción inicial</strong>
                    <p>{selectedTicket.description}</p>
                  </div>

                  <div className="support-thread">
                    {selectedMessages.map((entry) => (
                      <article
                        key={entry.id}
                        className={`support-message${entry.sender_id === currentUserId ? ' is-mine' : ''}`}
                      >
                        <div className="support-message__meta">
                          <strong>{entry.sender_name}</strong>
                          <span>{entry.sender_role === 'customer' ? 'Usuario' : 'Soporte'}</span>
                          <span>{formatDate(entry.created_at, true)}</span>
                        </div>
                        <p>{entry.body}</p>
                      </article>
                    ))}
                  </div>

                  <div className="support-reply">
                    <textarea
                      className="form-control"
                      rows="4"
                      placeholder="Escribe tu respuesta..."
                      value={reply}
                      onChange={(event) => setReply(event.target.value)}
                    />
                    <div className="form-actions">
                      <button className="btn" type="button" onClick={sendReply}>
                        Enviar respuesta
                      </button>
                    </div>
                  </div>
                </div>
              </>
            ) : (
              <div className="support-empty">Selecciona un ticket para ver el detalle.</div>
            )}
          </div>
        </section>
      </div>
    </main>
  )
}
