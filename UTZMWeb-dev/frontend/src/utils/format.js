export function formatCurrency(cents) {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
  }).format((Number(cents) || 0) / 100)
}

export function formatDate(value, withTime = false) {
  if (!value) {
    return 'Sin fecha'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return String(value)
  }

  return date.toLocaleString('es-MX', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
  })
}

export function groupCartBySeller(items) {
  return items.reduce((accumulator, item) => {
    const key = String(item.seller_id)
    if (!accumulator[key]) {
      accumulator[key] = []
    }

    accumulator[key].push({
      product_id: Number(item.product_id),
      qty: Number(item.qty),
    })

    return accumulator
  }, {})
}
