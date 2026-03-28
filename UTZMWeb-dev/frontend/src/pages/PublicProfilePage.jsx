import { useEffect, useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import ProductCarousel from '@/components/ProductCarousel'
import { accountService } from '@/services'
import { formatDate } from '@/utils/format'

function formatRating(value) {
  return `${Number(value || 0).toFixed(1)} / 5`
}

export default function PublicProfilePage() {
  const [searchParams] = useSearchParams()
  const [profile, setProfile] = useState(null)
  const [reviews, setReviews] = useState([])
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const userId = Number(searchParams.get('id') || 0)

  useEffect(() => {
    let active = true

    async function loadProfile() {
      setLoading(true)
      setError('')

      try {
        const data = await accountService.getPublicProfile(userId)
        if (!active) return
        setProfile(data.profile || null)
        setReviews(Array.isArray(data.reviews) ? data.reviews : [])
        setProducts(Array.isArray(data.products) ? data.products : [])
      } catch (requestError) {
        if (!active) return
        setError(requestError.message)
        setProfile(null)
        setReviews([])
        setProducts([])
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }

    if (userId > 0) {
      loadProfile()
    } else {
      setLoading(false)
      setError('Perfil no valido.')
    }

    return () => {
      active = false
    }
  }, [userId])

  const profileTitle = useMemo(() => {
    if (!profile) return 'Perfil publico'
    return profile.store_name || profile.full_name || 'Perfil publico'
  }, [profile])

  return (
    <main className="container public-profile-page">
      {loading ? (
        <div className="card" style={{ padding: 18 }}>
          Cargando perfil...
        </div>
      ) : null}

      {!loading && error ? (
        <div className="card" style={{ padding: 18 }}>
          {error}
        </div>
      ) : null}

      {!loading && !error && profile ? (
        <>
          <section className="public-profile-hero card">
            <div className="public-profile-hero__main">
              <img
                className="public-profile-hero__avatar"
                src={profile.avatar_url}
                alt={profile.full_name || 'Perfil'}
              />
              <div className="public-profile-hero__content">
                <div className="public-profile-hero__eyebrow">Perfil publico</div>
                <h1>{profileTitle}</h1>
                <p className="public-profile-hero__name">{profile.full_name || 'Usuario sin nombre'}</p>
                <div className="public-profile-hero__stats">
                  <span>{formatRating(profile.avg_rating)} de calificación</span>
                  <span>{profile.review_count} reseñas</span>
                  <span>{profile.product_count} productos</span>
                  <span>{profile.sales_count} ventas completadas</span>
                </div>
                <p className="public-profile-hero__bio">
                  {profile.seller_bio || 'Este usuario todavia no agrego una descripcion publica.'}
                </p>
              </div>
            </div>
          </section>

          <section className="public-profile-section">
            <div className="strip-head">
              <h2>Reseñas recibidas</h2>
              <span className="muted">
                {reviews.length} {reviews.length === 1 ? 'reseña visible' : 'reseñas visibles'}
              </span>
            </div>

            {!reviews.length ? (
              <div className="card" style={{ padding: 18 }}>
                Todavia no hay reseñas para este perfil.
              </div>
            ) : (
              <div className="public-profile-reviews">
                {reviews.map((review) => (
                  <article key={review.id} className="public-profile-review card">
                    <div className="public-profile-review__head">
                      <div>
                        <div className="public-profile-review__author">
                          <Link className="link" to={`/perfil.html?id=${review.reviewer_id}`}>
                            {review.reviewer_name || 'Usuario'}
                          </Link>
                        </div>
                        <div className="public-profile-review__meta">{formatDate(review.created_at, true)}</div>
                      </div>
                      <strong>{formatRating(review.rating)}</strong>
                    </div>
                    <p>{review.comment || 'Sin comentario.'}</p>
                  </article>
                ))}
              </div>
            )}
          </section>

          <section className="public-profile-section">
            <ProductCarousel
              title="Productos publicados"
              items={products}
              emptyText="Este usuario no tiene productos publicados por ahora."
            />
          </section>
        </>
      ) : null}
    </main>
  )
}
