import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useApp } from '@/context/AppContext'
import { formatCurrency } from '@/utils/format'
import { productService } from '@/services'
import { buildProductPlaceholder } from '@/utils/productPlaceholder'

function renderStars(value) {
  const rounded = Math.max(0, Math.min(5, Math.round(Number(value) || 0)))
  return '\u2605'.repeat(rounded) + '\u2606'.repeat(5 - rounded)
}

export default function ProductPage() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const { session, isLoggedIn, addToCart, openAuth, replaceCart } = useApp()
  const [product, setProduct] = useState(null)
  const [questions, setQuestions] = useState([])
  const [questionText, setQuestionText] = useState('')
  const [answers, setAnswers] = useState({})
  const [message, setMessage] = useState('')
  const [loading, setLoading] = useState(true)
  const [mainImage, setMainImage] = useState('')

  const productId = Number(searchParams.get('id') || 0)
  const isSeller = useMemo(
    () => Number(session.user?.id) === Number(product?.seller_id),
    [product?.seller_id, session.user?.id],
  )

  async function loadQuestions() {
    if (!productId) return
    const data = await productService.getQnA(productId)
    setQuestions(Array.isArray(data) ? data : [])
  }

  useEffect(() => {
    let active = true

    async function loadProduct() {
      setLoading(true)
      setMessage('')

      try {
        const data = await productService.getProduct(productId)
        if (!active) return
        setProduct(data)
        setMainImage(data.images?.[0] || buildProductPlaceholder(data.name, 'Imagen pendiente'))
        await loadQuestions()
      } catch (error) {
        if (active) {
          setMessage(error.message)
          setProduct(null)
        }
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }

    if (productId) {
      loadProduct()
    } else {
      setLoading(false)
      setMessage('Producto no encontrado.')
    }

    return () => {
      active = false
    }
  }, [productId])

  function handleAddToCart() {
    if (!isLoggedIn) {
      openAuth('login')
      return
    }

    addToCart({
      product_id: Number(product.id),
      name: product.name,
      price_cents: Number(product.price_cents),
      seller_id: Number(product.seller_id),
    })
  }

  function handleBuyNow() {
    if (!isLoggedIn) {
      openAuth('login')
      return
    }

    replaceCart([
      {
        product_id: Number(product.id),
        name: product.name,
        price_cents: Number(product.price_cents),
        seller_id: Number(product.seller_id),
        qty: 1,
      },
    ])
    navigate('/pagar.html')
  }

  async function submitQuestion() {
    if (!questionText.trim()) return

    try {
      await productService.askQuestion({ product_id: productId, text: questionText.trim() })
      setQuestionText('')
      await loadQuestions()
    } catch (error) {
      setMessage(error.message)
    }
  }

  async function submitAnswer(questionId) {
    const value = answers[questionId]?.trim()
    if (!value) return

    try {
      await productService.answerQuestion({ question_id: questionId, text: value })
      setAnswers((current) => ({ ...current, [questionId]: '' }))
      await loadQuestions()
    } catch (error) {
      setMessage(error.message)
    }
  }

  return (
    <main className="container" style={{ padding: '18px 0 34px' }}>
      {loading ? (
        <div className="card" style={{ padding: 18 }}>
          Cargando producto...
        </div>
      ) : null}

      {!loading && message && !product ? (
        <div className="card" style={{ padding: 18 }}>
          {message}
        </div>
      ) : null}

      {!loading && product ? (
        <>
          <section className="product">
            <div className="pgrid">
              <div className="gallery">
                <div className="gallery-main">
                  <img id="p-main" src={mainImage} alt={product.name} />
                </div>
                <div className="gallery-thumbs">
                  {(product.images || []).map((image, index) => (
                    <img
                      key={`${image}-${index}`}
                      src={image}
                      alt={`${product.name} ${index + 1}`}
                      onClick={() => setMainImage(image)}
                    />
                  ))}
                </div>
              </div>

              <div className="pinfo">
                <h1 className="ptitle">{product.name}</h1>
                <div className="pprice">{formatCurrency(product.price_cents)}</div>
                <div className="pstock">{Number(product.stock) > 0 ? 'En stock' : 'Sin stock'}</div>
                <div className="pseller">
                  Vendido por{' '}
                  <strong>
                    <Link className="link" to={`/perfil.html?id=${product.seller_id}`}>
                      {product.store_name || product.seller_name || product.seller_email || 'Vendedor'}
                    </Link>
                  </strong>
                </div>
                <div className="pdesc">{product.description}</div>

                <div className="seller-profile-card">
                  <div className="seller-profile-card__title">Perfil del vendedor</div>
                  <div className="seller-profile-card__name">{product.store_name || product.seller_name || 'Tienda sin nombre'}</div>
                  <div className="seller-profile-card__rating">
                    <strong>{renderStars(product.seller_rating_avg)}</strong>
                    <span>
                      {Number(product.seller_rating_avg || 0).toFixed(1)} / 5
                      {product.seller_review_count ? ` - ${product.seller_review_count} resenas` : ' - Sin resenas todavia'}
                    </span>
                  </div>
                  <p className="seller-profile-card__bio">
                    {product.seller_bio || 'Este vendedor aun no agrego una descripcion.'}
                  </p>
                  <div className="form-actions" style={{ marginTop: 12 }}>
                    <Link className="btn" to={`/perfil.html?id=${product.seller_id}`}>
                      Ver perfil publico
                    </Link>
                  </div>
                </div>

                <div className="pactions">
                  <button className="btn btn-add" type="button" onClick={handleAddToCart}>
                    Agregar al carrito
                  </button>
                  <button className="btn" style={{ background: '#10b981' }} type="button" onClick={handleBuyNow}>
                    Comprar ahora
                  </button>
                </div>
              </div>
            </div>
          </section>

          <section className="qna" aria-labelledby="ttl-qna">
            <h2 id="ttl-qna">Preguntas y respuestas</h2>
            {questions.length ? (
              <div className="q-list">
                {questions.map((question) => (
                  <div key={question.id} className="q-item">
                    <div className="q-text">{question.text}</div>
                    <div className="q-meta">por {question.user?.full_name || 'Usuario'}</div>
                    {question.answer ? (
                      <div className="a-text">
                        <strong>Vendedor:</strong> {question.answer.text}
                      </div>
                    ) : null}
                    {isSeller && !question.answer ? (
                      <div className="ans-row">
                        <input
                          className="form-control"
                          type="text"
                          placeholder="Responder..."
                          value={answers[question.id] || ''}
                          onChange={(event) =>
                            setAnswers((current) => ({
                              ...current,
                              [question.id]: event.target.value,
                            }))
                          }
                        />
                        <button className="btn" type="button" onClick={() => submitAnswer(question.id)}>
                          Enviar
                        </button>
                      </div>
                    ) : null}
                  </div>
                ))}
              </div>
            ) : (
              <div className="form-hint">Aun no hay preguntas.</div>
            )}

            {!isLoggedIn ? (
              <div className="form-hint">Inicia sesion para hacer preguntas.</div>
            ) : !isSeller ? (
              <div className="q-ask">
                <textarea
                  className="form-control"
                  rows="3"
                  placeholder="Escribe tu pregunta"
                  value={questionText}
                  onChange={(event) => setQuestionText(event.target.value)}
                />
                <div className="form-actions">
                  <button className="btn" type="button" onClick={submitQuestion}>
                    Preguntar
                  </button>
                </div>
              </div>
            ) : null}

            {message && product ? <div className="form-hint">{message}</div> : null}
          </section>
        </>
      ) : null}
    </main>
  )
}
