import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { CATEGORIES } from '@/config/categories'
import { productService } from '@/services'

const INITIAL_FORM = {
  name: '',
  category: '',
  description: '',
  price: '',
  stock: '',
}

export default function SellPage() {
  const navigate = useNavigate()
  const [form, setForm] = useState(INITIAL_FORM)
  const [files, setFiles] = useState([])
  const [message, setMessage] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function publishProduct() {
    setIsSubmitting(true)
    setMessage('')

    try {
      const data = await productService.createProduct({
        name: form.name,
        category: Number(form.category),
        description: form.description,
        price: Number(form.price),
        stock: Number(form.stock),
      })

      for (const file of files) {
        await productService.uploadProductImage({ productId: data.product_id, file })
      }

      navigate(`/producto.html?id=${data.product_id}`)
    } catch (error) {
      setMessage(error.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <main className="container" style={{ padding: '18px 0 34px' }}>
      <section className="hero-card" style={{ padding: 0 }}>
        <div className="hero-text" style={{ paddingBottom: 0 }}>
          <h1>Publicar producto</h1>
          <p>Completa los datos para crear tu publicación.</p>
        </div>
      </section>

      <section className="card" style={{ padding: 20, marginTop: 16 }}>
        <div className="form-grid">
          <div className="form-group">
            <label className="form-label">Nombre del producto</label>
            <input className="form-control" value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} />
          </div>
          <div className="form-group">
            <label className="form-label">Categoría</label>
            <select className="form-control" value={form.category} onChange={(event) => setForm((current) => ({ ...current, category: event.target.value }))}>
              <option value="">Selecciona una categoría</option>
              {CATEGORIES.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.label}
                </option>
              ))}
            </select>
          </div>
          <div className="form-group">
            <label className="form-label">Descripción</label>
            <textarea className="form-control" rows="6" value={form.description} onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))} />
          </div>
          <div className="form-row-2">
            <div className="form-group">
              <label className="form-label">Precio (MXN)</label>
              <input className="form-control" type="number" min="0" step="0.01" value={form.price} onChange={(event) => setForm((current) => ({ ...current, price: event.target.value }))} />
            </div>
            <div className="form-group">
              <label className="form-label">Stock</label>
              <input className="form-control" type="number" min="1" value={form.stock} onChange={(event) => setForm((current) => ({ ...current, stock: event.target.value }))} />
            </div>
          </div>
          <div className="form-group">
            <label className="form-label">Imágenes</label>
            <input className="form-control" type="file" accept="image/*" multiple onChange={(event) => setFiles(Array.from(event.target.files || []))} />
            <div className="form-hint">Hasta 6 imágenes.</div>
          </div>
        </div>
        {message ? <div className="form-hint">{message}</div> : null}
        <div className="form-actions">
          <button className="btn sell-submit-btn" type="button" onClick={publishProduct} disabled={isSubmitting}>
            {isSubmitting ? 'Publicando...' : 'Publicar'}
          </button>
        </div>
      </section>
    </main>
  )
}
