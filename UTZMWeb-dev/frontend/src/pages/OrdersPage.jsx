import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { orderService } from '@/services'
import { useApp } from '@/context/AppContext'
import { formatCurrency, formatDate } from '@/utils/format'

function renderStars(rating) {
  const rounded = Math.max(0, Math.min(5, Number(rating) || 0))
  return '\u2605'.repeat(rounded) + '\u2606'.repeat(5 - rounded)
}

export default function OrdersPage() {
  const { session } = useApp()
  const [orders, setOrders] = useState([])
  const [selectedOrder, setSelectedOrder] = useState(null)
  const [reviews, setReviews] = useState([])
  const [reviewForm, setReviewForm] = useState({ rating: 5, comment: '' })
  const [message, setMessage] = useState('')
  const [reviewsLoading, setReviewsLoading] = useState(false)

  async function loadOrders() {
    try {
      const data = await orderService.getOrders()
      setOrders(Array.isArray(data) ? data : [])
    } catch (error) {
      setMessage(error.message)
    }
  }

  useEffect(() => {
    loadOrders()
  }, [])

  useEffect(() => {
    if (!selectedOrder || selectedOrder.status !== 'delivered') {
      setReviews([])
      return
    }

    let active = true
    setReviewsLoading(true)

    orderService.getOrderReviews(selectedOrder.id)
      .then((data) => {
        if (active) {
          setReviews(data.reviews || [])
        }
      })
      .catch((error) => {
        if (active) {
          setMessage(error.message)
          setReviews([])
        }
      })
      .finally(() => {
        if (active) {
          setReviewsLoading(false)
        }
      })

    return () => {
      active = false
    }
  }, [selectedOrder])

  const groupedOrders = useMemo(() => {
    const userId = Number(session.user?.id)
    return {
      purchases: orders.filter((order) => Number(order.buyer_id) === userId),
      sales: orders.filter((order) => Number(order.seller_id) === userId),
    }
  }, [orders, session.user?.id])

  async function confirmDelivery(orderId) {
    try {
      await orderService.markDelivered({ orderId })
      await loadOrders()
    } catch (error) {
      setMessage(error.message)
    }
  }

  async function cancelOrder(orderId) {
    try {
      await orderService.cancelOrder(orderId)
      await loadOrders()
    } catch (error) {
      setMessage(error.message)
    }
  }

  async function submitReview() {
    if (!selectedOrder) return

    try {
      await orderService.saveOrderReview({
        order_id: selectedOrder.id,
        rating: Number(reviewForm.rating),
        comment: reviewForm.comment,
      })
      setReviewForm({ rating: 5, comment: '' })
      const data = await orderService.getOrderReviews(selectedOrder.id)
      setReviews(data.reviews || [])
      await loadOrders()
      setMessage('Resena guardada correctamente.')
    } catch (error) {
      setMessage(error.message)
    }
  }

  function OrderGroup({ title, items, role }) {
    return (
      <section className="orders-group card">
        <div className="orders-section__head">
          <h3>{title}</h3>
          <span className="muted">
            {items.length} {items.length === 1 ? 'pedido' : 'pedidos'}
          </span>
        </div>
        <div className="orders-cards">
          {items.map((order) => {
            const canConfirm = role === 'seller' && order.status !== 'delivered' && order.status !== 'cancelled'
            const canCancel = order.status !== 'delivered' && order.status !== 'cancelled'
            const counterpartId = role === 'buyer' ? order.seller_id : order.buyer_id
            const counterpartName = role === 'buyer' ? order.seller_name : order.buyer_name

            return (
              <div key={order.id} className="card order-card" onClick={() => setSelectedOrder(order)}>
                <div className="card-body">
                  <div className="order-card-head">
                    <div>
                      <h3 className="card-title" style={{ margin: 0 }}>
                        Pedido #{order.id}
                      </h3>
                      <div className="order-sub">
                        {role === 'buyer' ? 'Vendedor' : 'Comprador'}:{' '}
                        <Link
                          className="link"
                          to={`/perfil.html?id=${counterpartId}`}
                          onClick={(event) => event.stopPropagation()}
                        >
                          {counterpartName}
                        </Link>
                      </div>
                    </div>
                    <div className="order-head-right">
                      <span className="price">
                        <span className="now">{formatCurrency(order.total_cents)}</span>
                      </span>
                      <span className="status-pill">{order.status}</span>
                    </div>
                  </div>
                  <div className="order-summary">
                    <span><strong>Fecha:</strong> {formatDate(order.created_at)}</span>
                    <span><strong>Pago:</strong> {order.payment_method_label || order.payment_method_type}</span>
                  </div>
                  <div className="card-actions" style={{ marginTop: 10 }}>
                    {canConfirm ? (
                      <button className="btn" type="button" onClick={(event) => { event.stopPropagation(); confirmDelivery(order.id) }}>
                        Confirmar entrega
                      </button>
                    ) : null}
                    {canCancel ? (
                      <button className="btn btn-danger" type="button" onClick={(event) => { event.stopPropagation(); cancelOrder(order.id) }}>
                        Cancelar
                      </button>
                    ) : null}
                    {order.status === 'delivered' ? (
                      <button className="btn" type="button" onClick={(event) => { event.stopPropagation(); setSelectedOrder(order) }}>
                        Ver resenas
                      </button>
                    ) : null}
                  </div>
                </div>
              </div>
            )
          })}
        </div>
      </section>
    )
  }

  const myReview = reviews.find((review) => review.is_mine)
  const receivedReview = reviews.find((review) => !review.is_mine)
  const counterpartName = selectedOrder
    ? Number(selectedOrder.buyer_id) === Number(session.user?.id)
      ? selectedOrder.seller_name
      : selectedOrder.buyer_name
    : ''
  const counterpartId = selectedOrder
    ? Number(selectedOrder.buyer_id) === Number(session.user?.id)
      ? selectedOrder.seller_id
      : selectedOrder.buyer_id
    : 0

  return (
    <main className="container" style={{ padding: '18px 0 34px' }}>
      <div className="orders-wrap">
        <div className="strip-head">
          <h2>Mis pedidos</h2>
          <p className="form-hint" style={{ margin: 0 }}>
            Consulta tus pedidos como comprador o vendedor.
          </p>
        </div>
        {message ? <div className="card" style={{ padding: 14 }}>{message}</div> : null}
        <div className="orders-sections">
          <OrderGroup title="Pedidos realizados" items={groupedOrders.purchases} role="buyer" />
          <OrderGroup title="Pedidos vendidos" items={groupedOrders.sales} role="seller" />
        </div>
      </div>

      {selectedOrder ? (
        <div className="modal" onClick={() => setSelectedOrder(null)}>
          <div className="modal-content order-detail-modal" style={{ maxWidth: 620 }} onClick={(event) => event.stopPropagation()}>
            <button className="modal-close" type="button" onClick={() => setSelectedOrder(null)}>
              &times;
            </button>
            <h2>Detalle del pedido</h2>
            <div className="form-group"><div className="form-label">Estado</div><div>{selectedOrder.status}</div></div>
            <div className="form-group"><div className="form-label">Total</div><div>{formatCurrency(selectedOrder.total_cents)}</div></div>
            <div className="form-group"><div className="form-label">Creado</div><div>{formatDate(selectedOrder.created_at, true)}</div></div>
            <div className="form-group"><div className="form-label">Pagado</div><div>{selectedOrder.payment_paid_at ? formatDate(selectedOrder.payment_paid_at, true) : 'No pagado'}</div></div>
            <div className="form-group"><div className="form-label">Metodo de pago</div><div>{selectedOrder.payment_method_label || selectedOrder.payment_method_type}</div></div>
            {counterpartId ? (
              <div className="form-group">
                <div className="form-label">Perfil</div>
                <div>
                  <Link className="link" to={`/perfil.html?id=${counterpartId}`}>
                    Ver perfil de {counterpartName}
                  </Link>
                </div>
              </div>
            ) : null}

            {selectedOrder.status === 'delivered' ? (
              <div className="order-review-block">
                <h3>Calificaciones y resenas</h3>
                {reviewsLoading ? <div className="form-hint">Cargando resenas...</div> : null}

                {myReview ? (
                  <div className="order-review-card">
                    <strong>
                      Tu resena para{' '}
                      {counterpartId ? (
                        <Link className="link" to={`/perfil.html?id=${counterpartId}`}>
                          {counterpartName}
                        </Link>
                      ) : counterpartName}
                    </strong>
                    <div className="order-review-card__rating">{renderStars(myReview.rating)} ({myReview.rating}/5)</div>
                    <p>{myReview.comment || 'Sin comentario.'}</p>
                  </div>
                ) : (
                  <div className="order-review-form">
                    <div className="form-group">
                      <label className="form-label">Tu calificacion para {counterpartName}</label>
                      <select className="form-control" value={reviewForm.rating} onChange={(event) => setReviewForm((current) => ({ ...current, rating: Number(event.target.value) }))}>
                        <option value="5">5 - Excelente</option>
                        <option value="4">4 - Muy bueno</option>
                        <option value="3">3 - Bueno</option>
                        <option value="2">2 - Regular</option>
                        <option value="1">1 - Malo</option>
                      </select>
                    </div>
                    <div className="form-group">
                      <label className="form-label">Comentario</label>
                      <textarea className="form-control" rows="4" value={reviewForm.comment} onChange={(event) => setReviewForm((current) => ({ ...current, comment: event.target.value }))} />
                    </div>
                    <div className="form-actions">
                      <button className="btn" type="button" onClick={submitReview}>
                        Guardar resena
                      </button>
                    </div>
                  </div>
                )}

                {receivedReview ? (
                  <div className="order-review-card">
                    <strong>
                      Resena recibida de{' '}
                      <Link className="link" to={`/perfil.html?id=${receivedReview.reviewer_id}`}>
                        {receivedReview.reviewer_name}
                      </Link>
                    </strong>
                    <div className="order-review-card__rating">{renderStars(receivedReview.rating)} ({receivedReview.rating}/5)</div>
                    <p>{receivedReview.comment || 'Sin comentario.'}</p>
                  </div>
                ) : !reviewsLoading ? (
                  <div className="form-hint">La otra parte todavia no ha dejado su resena.</div>
                ) : null}
              </div>
            ) : null}
          </div>
        </div>
      ) : null}
    </main>
  )
}
