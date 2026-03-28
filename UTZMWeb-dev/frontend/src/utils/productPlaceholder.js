function escapeXml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;')
}

function wrapTitle(value, maxLength = 18, maxLines = 3) {
  const words = String(value || 'Producto').trim().split(/\s+/).filter(Boolean)
  const lines = []
  let current = ''

  for (const word of words) {
    const next = current ? `${current} ${word}` : word
    if (next.length <= maxLength || !current) {
      current = next
      continue
    }

    lines.push(current)
    current = word

    if (lines.length === maxLines - 1) break
  }

  if (current && lines.length < maxLines) {
    lines.push(current)
  }

  if (!lines.length) {
    lines.push('Producto')
  }

  if (words.join(' ').length > lines.join(' ').length) {
    const last = lines[lines.length - 1]
    lines[lines.length - 1] = `${last.slice(0, Math.max(0, maxLength - 1)).trimEnd()}…`
  }

  return lines
}

export function buildProductPlaceholder(name, subtitle = 'Sin imagen disponible') {
  const lines = wrapTitle(name)
  const titleMarkup = lines
    .map((line, index) => `<tspan x="72" y="${210 + index * 68}">${escapeXml(line)}</tspan>`)
    .join('')

  const svg = `
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 900" role="img" aria-label="${escapeXml(name || 'Producto')}">
      <defs>
        <linearGradient id="bg" x1="0" x2="1" y1="0" y2="1">
          <stop offset="0%" stop-color="#0f172a" />
          <stop offset="100%" stop-color="#155e75" />
        </linearGradient>
      </defs>
      <rect width="1200" height="900" fill="url(#bg)" />
      <circle cx="1040" cy="160" r="180" fill="rgba(255,255,255,.12)" />
      <circle cx="160" cy="760" r="140" fill="rgba(255,255,255,.08)" />
      <rect x="48" y="48" width="1104" height="804" rx="44" fill="rgba(255,255,255,.08)" stroke="rgba(255,255,255,.14)" />
      <rect x="72" y="72" width="680" height="756" rx="36" fill="rgba(248,250,252,.96)" />
      <rect x="72" y="72" width="680" height="88" rx="36" fill="#e0f2fe" />
      <text x="104" y="128" font-family="Segoe UI, Arial, sans-serif" font-size="30" font-weight="700" fill="#0f766e">UTZMPLACE</text>
      <text x="72" y="210" font-family="Segoe UI, Arial, sans-serif" font-size="58" font-weight="800" fill="#0f172a">${titleMarkup}</text>
      <text x="72" y="520" font-family="Segoe UI, Arial, sans-serif" font-size="28" font-weight="700" fill="#0f766e">${escapeXml(subtitle)}</text>
      <text x="72" y="584" font-family="Segoe UI, Arial, sans-serif" font-size="24" fill="#475569">Agrega imagenes del producto para una ficha mas completa.</text>
      <rect x="72" y="650" width="224" height="16" rx="8" fill="#67e8f9" opacity=".8" />
      <rect x="72" y="682" width="180" height="16" rx="8" fill="#155e75" opacity=".25" />
      <g transform="translate(820 182)">
        <rect x="0" y="0" width="280" height="420" rx="40" fill="rgba(255,255,255,.12)" stroke="rgba(255,255,255,.22)" />
        <rect x="64" y="46" width="152" height="290" rx="30" fill="rgba(255,255,255,.2)" stroke="rgba(255,255,255,.62)" stroke-width="10" />
        <rect x="84" y="78" width="112" height="198" rx="18" fill="rgba(255,255,255,.26)" />
        <circle cx="140" cy="308" r="16" fill="rgba(255,255,255,.62)" />
      </g>
    </svg>
  `.trim()

  return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`
}

export function getProductImageSource(image, name, subtitle) {
  return image || buildProductPlaceholder(name, subtitle)
}
