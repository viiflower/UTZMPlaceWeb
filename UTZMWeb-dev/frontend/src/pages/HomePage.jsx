import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import ProductCarousel from '@/components/ProductCarousel'
import { CATEGORIES, getCategoryLabel } from '@/config/categories'
import { productService } from '@/services'
import { useApp } from '@/context/AppContext'

export default function HomePage() {
  const { favorites } = useApp()
  const [searchParams, setSearchParams] = useSearchParams()
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const query = searchParams.get('q') || ''
  const category = searchParams.get('category') || ''
  const sort = searchParams.get('sort') || 'recent'
  const availability = searchParams.get('availability') || 'in_stock'
  const favoritesOnly = searchParams.get('favorites') === '1'

  useEffect(() => {
    let active = true

    async function loadProducts() {
      setLoading(true)
      setError('')

      try {
        const data = await productService.getProducts({ query, category, sort, availability })
        if (active) {
          setProducts(Array.isArray(data) ? data : [])
        }
      } catch (requestError) {
        if (active) {
          setError(requestError.message)
          setProducts([])
        }
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }

    loadProducts()

    return () => {
      active = false
    }
  }, [availability, category, query, sort])

  function updateCatalogParam(key, value) {
    const params = new URLSearchParams(searchParams)

    if (!value || value === 'recent' || value === 'in_stock') {
      params.delete(key)
    } else {
      params.set(key, value)
    }

    setSearchParams(params)
  }

  const visibleProducts = useMemo(() => {
    if (!favoritesOnly) {
      return products
    }

    const favoriteIds = new Set(favorites.map((item) => Number(item.product_id)))
    return products.filter((product) => favoriteIds.has(Number(product.id)))
  }, [favorites, favoritesOnly, products])

  const hasActiveCatalogFilters = Boolean(query || category || sort !== 'recent' || availability !== 'in_stock' || favoritesOnly)

  const sections = useMemo(() => {
    if (hasActiveCatalogFilters) {
      const title = query
        ? `Resultados: ${query}`
        : category
          ? getCategoryLabel(category)
          : 'Resultados filtrados'
      return [{ title, items: visibleProducts }]
    }

    const groupedSections = CATEGORIES.map((categoryItem) => ({
      title: categoryItem.label,
      items: visibleProducts.filter((product) => Number(product.category_id) === Number(categoryItem.id)),
    })).filter((section) => section.items.length)

    const uncategorized = visibleProducts.filter((product) => !product.category_id)
    if (uncategorized.length) {
      groupedSections.push({ title: 'Sin categoria', items: uncategorized })
    }

    return groupedSections
  }, [category, hasActiveCatalogFilters, query, visibleProducts])

  return (
    <main className="container" style={{ padding: '18px 0 34px' }}>
      <section className="catalog-toolbar card">
        <div className="catalog-toolbar__head">
          <h2>Explorar productos</h2>
          <p className="form-hint">
            {category
              ? `Categoria activa: ${getCategoryLabel(category)}. Ajusta orden, disponibilidad o favoritos.`
              : 'Ajusta orden, disponibilidad o favoritos. Las categorias se controlan desde la barra superior.'}
          </p>
        </div>
        <div className="catalog-toolbar__grid">
          <div className="form-group">
            <label className="form-label">Ordenar por</label>
            <select className="form-control" value={sort} onChange={(event) => updateCatalogParam('sort', event.target.value)}>
              <option value="recent">Mas recientes</option>
              <option value="oldest">Mas antiguos</option>
              <option value="price_asc">Precio menor a mayor</option>
              <option value="price_desc">Precio mayor a menor</option>
            </select>
          </div>
          <div className="form-group">
            <label className="form-label">Disponibilidad</label>
            <select className="form-control" value={availability} onChange={(event) => updateCatalogParam('availability', event.target.value)}>
              <option value="in_stock">Solo disponibles</option>
              <option value="all">Todos</option>
              <option value="out_of_stock">Solo sin stock</option>
            </select>
          </div>
          <div className="form-group">
            <label className="form-label">Favoritos</label>
            <button
              className={`catalog-toolbar__toggle${favoritesOnly ? ' is-active' : ''}`}
              type="button"
              onClick={() => updateCatalogParam('favorites', favoritesOnly ? '' : '1')}
            >
              {favoritesOnly ? 'Mostrando favoritos' : 'Mostrar solo favoritos'}
            </button>
          </div>
        </div>
      </section>

      {loading ? (
        <section className="strip">
          <div className="card" style={{ padding: 18 }}>
            Cargando productos...
          </div>
        </section>
      ) : null}

      {!loading && error ? (
        <section className="strip">
          <div className="card" style={{ padding: 18 }}>
            {error}
          </div>
        </section>
      ) : null}

      {!loading && !error ? (
        sections.map((section) => <ProductCarousel key={section.title} title={section.title} items={section.items} />)
      ) : null}

      {!loading && !error && !visibleProducts.length ? (
        <section className="strip">
          <div className="card" style={{ padding: 18 }}>
            No se encontraron productos con los filtros actuales.
          </div>
        </section>
      ) : null}
    </main>
  )
}
