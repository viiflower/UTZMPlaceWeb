import { useEffect, useMemo, useState } from 'react'
import { messageService, orderService } from '@/services'
import { formatCurrency, formatDate } from '@/utils/format'
import { useApp } from '@/context/AppContext'

export default function MessagesPage() {
  const { session } = useApp()
  const [orders, setOrders] = useState([])
  const [activeOrder, setActiveOrder] = useState(null)
  const [messages, setMessages] = useState([])
  const [body, setBody] = useState('')
  const [attachment, setAttachment] = useState(null)
  const [message, setMessage] = useState('')

  async function loadOrders() {
    try {
      const data = await orderService.getOrders(50)
      setOrders(Array.isArray(data) ? data : [])
    } catch (error) {
      setMessage(error.message)
    }
  }

  async function loadMessages(orderId, sinceId = 0) {
    if (!orderId) return
    try {
      const data = await messageService.getMessages({ orderId, sinceId })
      const nextMessages = Array.isArray(data.messages) ? data.messages : []
      setMessages((current) => (sinceId > 0 ? [...current, ...nextMessages] : nextMessages))
    } catch (error) {
      setMessage(error.message)
    }
  }

  useEffect(() => {
    loadOrders()
  }, [])

  useEffect(() => {
    if (!activeOrder) return

    loadMessages(activeOrder.id)
    const timer = window.setInterval(() => {
      const lastId = messages[messages.length - 1]?.id || 0
      loadMessages(activeOrder.id, lastId)
    }, 5000)

    return () => window.clearInterval(timer)
  }, [activeOrder?.id, messages])

  const threadTitle = useMemo(() => {
    if (!activeOrder) return ''
    const isBuyer = Number(activeOrder.buyer_id) === Number(session.user?.id)
    return `${isBuyer ? activeOrder.seller_name : activeOrder.buyer_name} • ${activeOrder.status} • ${formatCurrency(activeOrder.total_cents)}`
  }, [activeOrder, session.user?.id])

  async function sendMessage(event) {
    event.preventDefault()
    if (!activeOrder) return

    try {
      const data = await messageService.sendMessage({
        orderId: activeOrder.id,
        body,
        attachment,
      })
      setMessages((current) => [...current, data.message])
      setBody('')
      setAttachment(null)
      await loadOrders()
    } catch (error) {
      setMessage(error.message)
    }
  }

  return (
    <main className="container" style={{ padding: '18px 0 34px' }}>
      <section className="strip">
        <div className="strip-head">
          <h2>Mensajes</h2>
          <p className="q-meta">Habla con el vendedor o comprador después de concretar una orden.</p>
        </div>

        {message ? <div className="card" style={{ padding: 14 }}>{message}</div> : null}

        <div className="messages-layout">
          <aside className="messages-sidebar">
            {orders.length ? (
              <div className="messages-threads">
                {orders.map((order) => {
                  const isBuyer = Number(order.buyer_id) === Number(session.user?.id)
                  return (
                    <button key={order.id} className={`thread-item${activeOrder?.id === order.id ? ' active' : ''}`} type="button" onClick={() => setActiveOrder(order)}>
                      <p className="thread-title">
                        {isBuyer ? order.seller_name : order.buyer_name} • Pedido #{order.id}
                      </p>
                      <p className="thread-meta">
                        {order.status} • {formatDate(order.created_at, true)}
                      </p>
                      <p className="thread-last">{order.last_message || 'Comienza la conversación'}</p>
                    </button>
                  )
                })}
              </div>
            ) : (
              <div className="messages-empty">Cuando realices una orden verás los hilos aquí.</div>
            )}
          </aside>

          <section className={`messages-panel${activeOrder ? '' : ' is-disabled'}`}>
            {activeOrder ? (
              <>
                <div className="messages-header">
                  <h3>Conversación</h3>
                  <p className="q-meta">{threadTitle}</p>
                </div>
                <div className="messages-scroll">
                  {messages.map((item) => (
                    <div key={item.id} className={`msg-bubble${item.is_mine ? ' mine' : ''}`}>
                      {item.body ? <p className="msg-text">{item.body}</p> : null}
                      {item.attachment_url ? (
                        <a className="msg-attachment" href={item.attachment_url} target="_blank" rel="noreferrer">
                          <img src={item.attachment_url} alt="Adjunto del chat" />
                        </a>
                      ) : null}
                      <div className="msg-meta">
                        {item.is_mine ? 'Tú' : item.sender_name} • {formatDate(item.created_at, true)}
                      </div>
                    </div>
                  ))}
                </div>
                {activeOrder.status !== 'cancelled' ? (
                  <form className="messages-form" onSubmit={sendMessage}>
                    <div className="messages-field">
                      <textarea value={body} onChange={(event) => setBody(event.target.value)} placeholder="Escribe tu mensaje..." maxLength={1000} />
                      <div className="messages-tools">
                        <label className="messages-upload">
                          <input type="file" accept="image/*" onChange={(event) => setAttachment(event.target.files?.[0] || null)} />
                          Adjuntar imagen
                        </label>
                      </div>
                    </div>
                    <button className="btn" type="submit">
                      Enviar
                    </button>
                  </form>
                ) : (
                  <div className="messages-empty">Pedido cancelado por el comprador. Solo puedes ver el historial.</div>
                )}
              </>
            ) : (
              <div className="messages-empty" style={{ padding: 20 }}>
                Selecciona un pedido para ver la conversación.
              </div>
            )}
          </section>
        </div>
      </section>
    </main>
  )
}
