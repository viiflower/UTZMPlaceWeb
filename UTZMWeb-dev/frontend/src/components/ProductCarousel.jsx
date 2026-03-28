import { useEffect, useRef, useState } from 'react'
import ProductCard from '@/components/ProductCard'

export default function ProductCarousel({ title, items = [], emptyText = 'No hay productos disponibles.' }) {
  const viewportRef = useRef(null)
  const [canScrollPrev, setCanScrollPrev] = useState(false)
  const [canScrollNext, setCanScrollNext] = useState(items.length > 0)

  useEffect(() => {
    const viewport = viewportRef.current
    if (!viewport) return undefined

    const updateScrollState = () => {
      const maxScroll = Math.max(0, viewport.scrollWidth - viewport.clientWidth - 4)
      setCanScrollPrev(viewport.scrollLeft > 4)
      setCanScrollNext(viewport.scrollLeft < maxScroll)
    }

    updateScrollState()
    viewport.addEventListener('scroll', updateScrollState, { passive: true })
    window.addEventListener('resize', updateScrollState)

    return () => {
      viewport.removeEventListener('scroll', updateScrollState)
      window.removeEventListener('resize', updateScrollState)
    }
  }, [items.length])

  function scrollCarousel(direction) {
    const viewport = viewportRef.current
    if (!viewport) return

    const card = viewport.querySelector('.carousel-track > *')
    const step = card ? card.getBoundingClientRect().width + 16 : viewport.clientWidth * 0.9
    viewport.scrollBy({
      left: direction * step * 2,
      behavior: 'smooth',
    })
  }

  return (
    <section className="strip">
      <div className="strip-head">
        <h2>{title}</h2>
        {items.length > 1 ? (
          <div className="carousel-controls" aria-label={`Controles de ${title}`}>
            <button
              className="carousel-btn"
              type="button"
              onClick={() => scrollCarousel(-1)}
              disabled={!canScrollPrev}
              aria-label={`Desplazar ${title} hacia la izquierda`}
            >
              ‹
            </button>
            <button
              className="carousel-btn"
              type="button"
              onClick={() => scrollCarousel(1)}
              disabled={!canScrollNext}
              aria-label={`Desplazar ${title} hacia la derecha`}
            >
              ›
            </button>
          </div>
        ) : null}
      </div>

      {!items.length ? (
        <div className="card" style={{ padding: 18 }}>
          {emptyText}
        </div>
      ) : (
        <div className="carousel-host">
          <div className="carousel-window" ref={viewportRef}>
            <div className="carousel-track">
              {items.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          </div>
        </div>
      )}
    </section>
  )
}
