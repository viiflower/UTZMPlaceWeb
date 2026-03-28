import { useEffect, useId, useRef, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useApp } from '@/context/AppContext'

const QUICK_PROMPTS = [
  '¿Cómo pago un pedido?',
  '¿Cómo publico un producto?',
  'Quiero ver mis pedidos',
  'Necesito ayuda con una devolución',
  'Necesito soporte humano',
]

const BACK_ACTION = { label: 'Regresar', type: 'back' }

const FAQ_ENTRIES = [
  {
    match: ['pago', 'pagar', 'paypal', 'efectivo', 'método'],
    getResponse: () => ({
      text:
        'Puedes pagar desde la pantalla de checkout con PayPal o efectivo. Revisa bien el método antes de confirmar y verifica el estado del pedido después del pago.',
      actions: [
        { label: 'Ir a pagar', type: 'navigate', to: '/pagar.html' },
        { label: 'Ver métodos', type: 'navigate', to: '/metodos_pago.html' },
      ],
    }),
  },
  {
    match: ['publicar', 'producto', 'vender', 'venta', 'subir'],
    getResponse: ({ isLoggedIn }) => ({
      text: isLoggedIn
        ? 'Para publicar un producto entra a la sección Vender, completa nombre, categoría, descripción, precio, stock e imágenes. Cuando termines, usa el botón Publicar.'
        : 'Para publicar un producto primero necesitas iniciar sesión. Después podrás entrar a Vender y completar los datos de la publicación.',
      actions: isLoggedIn
        ? [{ label: 'Ir a vender', type: 'navigate', to: '/vender.html' }]
        : [{ label: 'Iniciar sesión', type: 'auth', mode: 'login' }],
    }),
  },
  {
    match: ['pedido', 'pedidos', 'orden', 'ordenes', 'compra'],
    getResponse: ({ isLoggedIn }) => ({
      text: isLoggedIn
        ? 'En Mis pedidos puedes revisar tus compras y ventas, confirmar entregas o cancelar pedidos que sigan activos.'
        : 'Para revisar tus pedidos necesitas iniciar sesión. Luego podrás abrir la sección Pedidos desde la barra superior.',
      actions: isLoggedIn
        ? [{ label: 'Abrir pedidos', type: 'navigate', to: '/pedidos.html' }]
        : [{ label: 'Ingresar', type: 'auth', mode: 'login' }],
    }),
  },
  {
    match: ['mensaje', 'mensajes', 'chat', 'hablar', 'vendedor', 'comprador'],
    getResponse: ({ isLoggedIn }) => ({
      text: isLoggedIn
        ? 'La conversación entre comprador y vendedor aparece en Mensajes después de que exista una orden. Desde ahí puedes dar seguimiento al pedido.'
        : 'La sección de mensajes se habilita cuando inicias sesión y existe una orden relacionada contigo.',
      actions: isLoggedIn
        ? [{ label: 'Ir a mensajes', type: 'navigate', to: '/mensajes.html' }]
        : [{ label: 'Crear cuenta', type: 'auth', mode: 'register' }],
    }),
  },
  {
    match: ['devolución', 'devolver', 'reembolso', 'problema', 'cancelación'],
    getResponse: () => ({
      text:
        'Si hubo un problema con la entrega o el producto, documenta el caso y revisa las secciones de Devoluciones y Reembolsos. El pago debe mantenerse dentro de la plataforma para facilitar la revisión.',
      actions: [
        { label: 'Devoluciones', type: 'navigate', to: '/devoluciones.html' },
        { label: 'Reembolsos', type: 'navigate', to: '/reembolsos.html' },
      ],
    }),
  },
  {
    match: ['soporte', 'ayuda humana', 'asesor', 'ticket', 'problema técnico'],
    getResponse: ({ isLoggedIn }) => ({
      text: isLoggedIn
        ? 'Si necesitas seguimiento humano, abre un ticket en Soporte. Desde ahí podrás explicar el problema y recibir respuesta del equipo.'
        : 'Para abrir un ticket con una persona del equipo primero necesitas iniciar sesión.',
      actions: isLoggedIn
        ? [{ label: 'Abrir soporte', type: 'navigate', to: '/soporte.html' }]
        : [{ label: 'Iniciar sesión', type: 'auth', mode: 'login' }],
    }),
  },
  {
    match: ['cuenta', 'perfil', 'sesión', 'login', 'registro'],
    getResponse: ({ isLoggedIn }) => ({
      text: isLoggedIn
        ? 'Desde tu menú de perfil puedes entrar a Cuenta, ver tus publicaciones o cerrar sesión.'
        : 'Puedes iniciar sesión o crear una cuenta desde el menú de perfil. Al hacerlo tendrás acceso a pedidos, mensajes, carrito y publicaciones.',
      actions: isLoggedIn
        ? [{ label: 'Ver cuenta', type: 'navigate', to: '/cuenta.html' }]
        : [
            { label: 'Iniciar sesión', type: 'auth', mode: 'login' },
            { label: 'Crear cuenta', type: 'auth', mode: 'register' },
          ],
    }),
  },
  {
    match: ['envio', 'entrega', 'recoger'],
    getResponse: () => ({
      text:
        'Las entregas se coordinan entre comprador y vendedor. Te conviene acordar fecha, hora y un punto seguro antes de cerrar la operación.',
      actions: [{ label: 'Ver envíos', type: 'navigate', to: '/envios.html' }],
    }),
  },
  {
    match: ['seguridad', 'privacidad', 'datos', 'terminos'],
    getResponse: () => ({
      text:
        'Para temas de seguridad y privacidad revisa nuestras páginas informativas. Mantente dentro de la plataforma y evita compartir información sensible por fuera.',
      actions: [
        { label: 'Seguridad', type: 'navigate', to: '/seguridad.html' },
        { label: 'Términos', type: 'navigate', to: '/terminos_privacidad.html' },
      ],
    }),
  },
]

function normalizeText(value) {
  return value
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^\w\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

function createAssistantMessage(text, actions = []) {
  return {
    id: crypto.randomUUID(),
    role: 'assistant',
    text,
    actions,
  }
}

function createMenuMessage() {
  return createAssistantMessage('Selecciona una opción del menú de asistencia para continuar.')
}

function createContextualGreeting(pathname, isLoggedIn) {
  if (pathname === '/pagar.html') {
    return createAssistantMessage(
      'Estoy viendo que estás en checkout. Si tienes dudas sobre pago, PayPal o efectivo, pregúntame y te guío.',
      [{ label: 'Dudas de pago', type: 'prompt', value: '¿Cómo pago un pedido?' }],
    )
  }

  if (pathname === '/vender.html') {
    return createAssistantMessage(
      'Si vas a publicar, puedo ayudarte con categorías, stock, precio o el proceso de venta.',
      [{ label: '¿Cómo publico un producto?', type: 'prompt', value: '¿Cómo publico un producto?' }],
    )
  }

  if (pathname === '/pedidos.html') {
    return createAssistantMessage(
      'Aquí puedo ayudarte a entender tus pedidos, entregas o cancelaciones.',
      [{ label: 'Quiero ver mis pedidos', type: 'prompt', value: 'Quiero ver mis pedidos' }],
    )
  }

  return createAssistantMessage(
    isLoggedIn
      ? 'Hola, soy el asistente virtual de Utzmplace. Puedo ayudarte con pagos, pedidos, ventas, envíos o devoluciones.'
      : 'Hola, soy el asistente virtual de Utzmplace. Puedo ayudarte con pagos, registro, publicaciones, envíos o devoluciones.',
  )
}

function findBestResponse(message, context) {
  const normalized = normalizeText(message)

  for (const entry of FAQ_ENTRIES) {
    if (entry.match.some((keyword) => normalized.includes(keyword))) {
      return entry.getResponse(context)
    }
  }

  return {
    text:
      'Puedo ayudarte con temas comunes de la plataforma: pagos, pedidos, mensajes, publicar productos, cuenta, envíos, devoluciones y seguridad. Escribe uno de esos temas y te guío.',
    actions: [
      { label: 'Pagos', type: 'prompt', value: '¿Cómo pago un pedido?' },
      { label: 'Publicar', type: 'prompt', value: '¿Cómo publico un producto?' },
      { label: 'Devoluciones', type: 'prompt', value: 'Necesito ayuda con una devolución' },
    ],
  }
}

export default function AssistantWidget() {
  const navigate = useNavigate()
  const location = useLocation()
  const { isLoggedIn, openAuth } = useApp()
  const inputId = useId()
  const listRef = useRef(null)
  const [isOpen, setIsOpen] = useState(false)
  const [isTyping, setIsTyping] = useState(false)
  const [draft, setDraft] = useState('')
  const [showSuggestions, setShowSuggestions] = useState(true)
  const [messages, setMessages] = useState(() => [createContextualGreeting(location.pathname, isLoggedIn)])

  useEffect(() => {
    if (!isOpen || !listRef.current) {
      return
    }

    listRef.current.scrollTop = listRef.current.scrollHeight
  }, [isOpen, isTyping, messages])

  useEffect(() => {
    function handleEscape(event) {
      if (event.key === 'Escape') {
        setIsOpen(false)
      }
    }

    window.addEventListener('keydown', handleEscape)
    return () => window.removeEventListener('keydown', handleEscape)
  }, [])

  useEffect(() => {
    setMessages((current) => {
      const firstMessage = current[0]
      const nextGreeting = createContextualGreeting(location.pathname, isLoggedIn)

      if (!firstMessage || firstMessage.role !== 'assistant') {
        return [nextGreeting, ...current]
      }

      return [nextGreeting, ...current.slice(1)]
    })
    setShowSuggestions(true)
  }, [isLoggedIn, location.pathname])

  function runAction(action) {
    if (action.type === 'back') {
      setShowSuggestions(true)
      setMessages((current) => [...current, createMenuMessage()])
      return
    }

    if (action.type === 'navigate') {
      navigate(action.to)
      setIsOpen(false)
      return
    }

    if (action.type === 'auth') {
      openAuth(action.mode)
      setIsOpen(false)
      return
    }

    if (action.type === 'prompt') {
      submitMessage(action.value, { fromMenu: true })
    }
  }

  function submitMessage(rawMessage, options = {}) {
    const text = rawMessage.trim()
    if (!text || isTyping) {
      return
    }

    const { fromMenu = false } = options

    const userMessage = {
      id: crypto.randomUUID(),
      role: 'user',
      text,
      actions: [],
    }

    const response = findBestResponse(text, { isLoggedIn, pathname: location.pathname })

    setMessages((current) => [...current, userMessage])
    if (fromMenu) {
      setShowSuggestions(false)
    }
    setDraft('')
    setIsOpen(true)
    setIsTyping(true)

    window.setTimeout(() => {
      const responseActions = fromMenu ? [...response.actions, BACK_ACTION] : response.actions
      setMessages((current) => [...current, createAssistantMessage(response.text, responseActions)])
      setIsTyping(false)
    }, 420)
  }

  function handleSubmit(event) {
    event.preventDefault()
    submitMessage(draft)
  }

  const lastAssistantMessageId = [...messages].reverse().find((message) => message.role === 'assistant')?.id

  return (
    <div className={`assistant-widget${isOpen ? ' is-open' : ''}`}>
      {!isOpen ? (
        <button
          className="assistant-trigger"
          type="button"
          aria-expanded={isOpen}
          aria-controls="assistant-panel"
          onClick={() => setIsOpen(true)}
        >
          <span className="assistant-trigger__icon" aria-hidden="true">
            UT
          </span>
          <span className="assistant-trigger__label">Asistente</span>
        </button>
      ) : null}

      {isOpen ? (
        <section id="assistant-panel" className="assistant-panel" aria-label="Asistente virtual">
          <div className="assistant-panel__head">
            <div>
              <p className="assistant-panel__eyebrow">Ayuda rápida</p>
              <h3>Asistente Utzmplace</h3>
            </div>
            <button className="assistant-close" type="button" onClick={() => setIsOpen(false)} aria-label="Cerrar asistente">
              &times;
            </button>
          </div>

          <div ref={listRef} className="assistant-scroll">
            <div className="assistant-thread">
              {messages.map((message) => (
                <article
                  key={message.id}
                  className={`assistant-bubble assistant-bubble--${message.role}`}
                >
                  <p>{message.text}</p>
                  {message.actions?.length && message.id === lastAssistantMessageId ? (
                    <div className="assistant-actions">
                      {message.actions.map((action) => (
                        <button
                          key={`${message.id}-${action.label}`}
                          className="assistant-chip"
                          type="button"
                          onClick={() => runAction(action)}
                        >
                          {action.label}
                        </button>
                      ))}
                    </div>
                  ) : null}
                </article>
              ))}

            {isTyping ? (
              <div className="assistant-bubble assistant-bubble--assistant assistant-bubble--typing">
                <span />
                <span />
                <span />
              </div>
            ) : null}
          </div>

          {showSuggestions ? (
          <div className="assistant-suggestions" aria-label="Sugerencias rápidas">
            {QUICK_PROMPTS.map((prompt) => (
              <button
                key={prompt}
                className="assistant-suggestion"
                type="button"
                onClick={() => submitMessage(prompt, { fromMenu: true })}
              >
                {prompt}
              </button>
            ))}
          </div>
          ) : null}
          </div>

          <form className="assistant-form" onSubmit={handleSubmit}>
            <label className="sr-only" htmlFor={inputId}>
              Escribe tu duda
            </label>
            <input
              id={inputId}
              type="text"
              placeholder="Escribe tu duda..."
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
            />
            <button type="submit" disabled={!draft.trim() || isTyping}>
              Enviar
            </button>
          </form>
        </section>
      ) : null}
    </div>
  )
}
