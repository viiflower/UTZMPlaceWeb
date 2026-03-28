import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { orderService, paymentService } from '@/services'
import { useApp } from '@/context/AppContext'
import { formatCurrency } from '@/utils/format'
import { loadPaypalSdk } from '@/lib/paypal'

export default function CheckoutPage() {
  const navigate = useNavigate()
  const { cartItems, clearCart } = useApp()
  const paypalButtonsRef = useRef(null)
  const paypalButtonsInstanceRef = useRef(null)
  const skipEmptyCartRedirectRef = useRef(false)
  const [method, setMethod] = useState('paypal')
  const [message, setMessage] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [isPaypalLoading, setIsPaypalLoading] = useState(false)
  const [paypalConfig, setPaypalConfig] = useState(null)

  const total = useMemo(
    () => cartItems.reduce((sum, item) => sum + Number(item.price_cents) * Number(item.qty), 0),
    [cartItems],
  )

  useEffect(() => {
    paymentService
      .getPaypalConfig()
      .then(setPaypalConfig)
      .catch((error) => setMessage(error.message))
  }, [])

  useEffect(() => {
    if (!cartItems.length && !skipEmptyCartRedirectRef.current) {
      navigate('/')
    }
  }, [cartItems.length, navigate])

  useEffect(() => {
    if (method !== 'paypal') {
      setIsPaypalLoading(false)
      return
    }

    if (!paypalConfig || !paypalButtonsRef.current || !total || !cartItems.length) {
      setIsPaypalLoading(true)
      return
    }

    let isActive = true
    setIsPaypalLoading(true)
    setMessage('')
    paypalButtonsRef.current.innerHTML = ''

    const mountButtons = async () => {
      try {
        const paypal = await loadPaypalSdk({
          clientId: paypalConfig.client_id,
          currency: paypalConfig.currency,
        })

        if (!isActive || !paypalButtonsRef.current) {
          return
        }

        const buttons = paypal.Buttons({
          style: {
            layout: 'vertical',
            shape: 'rect',
            label: 'paypal',
          },
          createOrder(data, actions) {
            setMessage('')
            return actions.order.create({
              purchase_units: [
                {
                  amount: {
                    currency_code: paypalConfig.currency,
                    value: (total / 100).toFixed(2),
                  },
                },
              ],
            })
          },
          async onApprove(data, actions) {
            setIsSubmitting(true)
            setMessage('')

            try {
              await actions.order.capture()
              await orderService.checkoutCart({
                cartItems,
                paymentMeta: {
                  type: 'paypal',
                  label: 'PayPal',
                  last4: null,
                  paypal_order_id: data.orderID,
                },
              })
              skipEmptyCartRedirectRef.current = true
              clearCart()
              navigate('/pedidos.html')
            } catch (error) {
              setMessage(error.message)
            } finally {
              setIsSubmitting(false)
            }
          },
          onCancel() {
            setMessage('El pago con PayPal fue cancelado.')
          },
          onError() {
            setMessage('No se pudo procesar el pago con PayPal.')
          },
        })

        if (buttons.isEligible()) {
          paypalButtonsInstanceRef.current = buttons
          await buttons.render(paypalButtonsRef.current)
          if (isActive) {
            setIsPaypalLoading(false)
          }
        } else {
          setMessage('PayPal no esta disponible para este navegador o configuracion.')
          setIsPaypalLoading(false)
        }
      } catch (error) {
        if (isActive) {
          setMessage(error.message)
          setIsPaypalLoading(false)
        }
      }
    }

    mountButtons()

    return () => {
      isActive = false
      const buttons = paypalButtonsInstanceRef.current
      paypalButtonsInstanceRef.current = null
      if (buttons?.close) {
        buttons.close().catch(() => {})
      }
      if (paypalButtonsRef.current) {
        paypalButtonsRef.current.innerHTML = ''
      }
    }
  }, [cartItems, clearCart, method, navigate, paypalConfig, total])

  async function handleCashPay() {
    setIsSubmitting(true)
    setMessage('')

    try {
      await orderService.checkoutCart({
        cartItems,
        paymentMeta: { type: 'cash', label: 'Efectivo', last4: null },
      })
      skipEmptyCartRedirectRef.current = true
      clearCart()
      navigate('/pedidos.html')
    } catch (error) {
      setMessage(error.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <main className="container checkout-page">
      <section className="card pay-card checkout-card">
        <div className="checkout-card__header">
          <h2 className="checkout-card__title">Completar pago</h2>
          <p className="form-hint checkout-card__hint">Elige un método para finalizar tu compra.</p>
        </div>

        <div className="form-group checkout-methods-group">
          <label className="form-label">Metodo de pago</label>
          <div className="checkout-methods">
            <label className={`checkout-method-option${method === 'paypal' ? ' is-active' : ''}`}>
              <input type="radio" checked={method === 'paypal'} onChange={() => setMethod('paypal')} />
              <span>PayPal</span>
            </label>
            <label className={`checkout-method-option${method === 'cash' ? ' is-active' : ''}`}>
              <input type="radio" checked={method === 'cash'} onChange={() => setMethod('cash')} />
              <span>Efectivo</span>
            </label>
          </div>
        </div>

        {method === 'paypal' ? (
          <div className="form-group checkout-paypal-group">
            <div className="form-hint checkout-paypal-copy">
              Serás redirigido a PayPal para completar tu pago de forma segura.
            </div>
            <div className="checkout-paypal-shell">
              {isPaypalLoading ? <div className="checkout-paypal-loading">Cargando PayPal...</div> : null}
              <div ref={paypalButtonsRef} className="checkout-paypal-slot" />
            </div>
          </div>
        ) : null}

        <div className="cart-total checkout-total">
          <span>Total</span>
          <strong>{formatCurrency(total)}</strong>
        </div>

        {message ? <div className="form-hint checkout-message">{message}</div> : null}

        {method === 'cash' ? (
          <div className="form-actions checkout-actions">
            <button className="btn" type="button" onClick={handleCashPay} disabled={isSubmitting}>
              {isSubmitting ? 'Procesando...' : 'Confirmar pago en efectivo'}
            </button>
          </div>
        ) : null}
      </section>
    </main>
  )
}
