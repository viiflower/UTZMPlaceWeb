import { Link } from 'react-router-dom'
import { useApp } from '@/context/AppContext'
import { formatCurrency } from '@/utils/format'
import { getProductImageSource } from '@/utils/productPlaceholder'

export default function ProductCard({ product }) {
  const { addToCart, isFavorite, toggleFavorite, isLoggedIn, openAuth } = useApp()

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

  return (
    <article className="card card--uniform">
      <div className="card-top">
        <button
          className={`btn-fav${isFavorite(product.id) ? ' is-active' : ''}`}
          type="button"
          aria-label="Agregar a favoritos"
          onClick={() =>
            toggleFavorite({
              product_id: Number(product.id),
              name: product.name,
              price_cents: Number(product.price_cents),
            })
          }
        >
          {isFavorite(product.id) ? '♥' : '♡'}
        </button>
      </div>
      <Link className="card-media" to={`/producto.html?id=${product.id}`}>
        <img
          src={getProductImageSource(product.image, product.name, 'Imagen pendiente')}
          alt={product.name}
        />
      </Link>
      <div className="card-body">
        <h3 className="card-title">
          <Link className="link" style={{ color: 'inherit', textDecoration: 'none' }} to={`/producto.html?id=${product.id}`}>
            {product.name}
          </Link>
        </h3>
        <div className="price">
          <span className="now">{formatCurrency(product.price_cents)}</span>
        </div>
      </div>
      <div className="card-actions">
        <button className="btn btn-add" type="button" onClick={handleAddToCart}>
          Agregar al carrito
        </button>
      </div>
    </article>
  )
}
