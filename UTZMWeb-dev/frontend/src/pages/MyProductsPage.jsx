import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { CATEGORIES } from '@/config/categories'
import { productService } from '@/services'
import { getProductImageSource } from '@/utils/productPlaceholder'

export default function MyProductsPage() {
  const [products, setProducts] = useState([])
  const [message, setMessage] = useState('')

  async function loadProducts() {
    try {
      const data = await productService.getMyProducts()
      setProducts(Array.isArray(data) ? data : [])
    } catch (error) {
      setMessage(error.message)
    }
  }

  useEffect(() => {
    loadProducts()
  }, [])

  async function saveProduct(product) {
    try {
      await productService.updateProduct({
        id: product.id,
        name: product.name,
        description: product.description,
        stock: product.stock,
        price: Number(product.price_cents) / 100,
        category: product.category_id || '',
      })
      setMessage('Publicación actualizada.')
      await loadProducts()
    } catch (error) {
      setMessage(error.message)
    }
  }

  function updateField(index, field, value) {
    setProducts((current) =>
      current.map((product, productIndex) =>
        productIndex === index ? { ...product, [field]: value } : product,
      ),
    )
  }

  return (
    <main className="container" style={{ padding: '18px 0 34px' }}>
      <section className="strip">
        <div className="strip-head">
          <h2>Mis publicaciones</h2>
        </div>
        {message ? <div className="card" style={{ padding: 14 }}>{message}</div> : null}
        <div className="grid">
          {products.map((product, index) => (
            <article key={product.id} className="card">
              <div className="card-media" style={{ aspectRatio: '3 / 2', overflow: 'hidden' }}>
                <img src={getProductImageSource(product.image, product.name, 'Imagen pendiente')} alt={product.name} />
              </div>
              <div className="card-body">
                <div className="form-group">
                  <label className="form-label">Nombre</label>
                  <input className="form-control" value={product.name} onChange={(event) => updateField(index, 'name', event.target.value)} />
                </div>
                <div className="form-group">
                  <label className="form-label">Precio (MXN)</label>
                  <input className="form-control" type="number" min="0" step="0.01" value={Number(product.price_cents) / 100} onChange={(event) => updateField(index, 'price_cents', Math.round(Number(event.target.value || 0) * 100))} />
                </div>
                <div className="form-group">
                  <label className="form-label">Stock</label>
                  <input className="form-control" type="number" min="0" value={product.stock} onChange={(event) => updateField(index, 'stock', Number(event.target.value || 0))} />
                </div>
                <div className="form-group">
                  <label className="form-label">Categoría</label>
                  <select className="form-control" value={product.category_id || ''} onChange={(event) => updateField(index, 'category_id', Number(event.target.value || 0))}>
                    <option value="">Sin categoría</option>
                    {CATEGORIES.map((category) => (
                      <option key={category.id} value={category.id}>
                        {category.label}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="form-group">
                  <label className="form-label">Descripción</label>
                  <textarea className="form-control" rows="3" value={product.description || ''} onChange={(event) => updateField(index, 'description', event.target.value)} />
                </div>
                <div className="form-actions">
                  <button className="btn" type="button" onClick={() => saveProduct(product)}>
                    Guardar
                  </button>
                  <Link className="btn" style={{ background: '#374151' }} to={`/producto.html?id=${product.id}`}>
                    Ver
                  </Link>
                </div>
              </div>
            </article>
          ))}
        </div>
      </section>
    </main>
  )
}
