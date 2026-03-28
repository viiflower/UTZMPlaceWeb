<?php
require __DIR__ . '/../db.php';

header('Content-Type: text/plain; charset=utf-8');

function slugify(string $value): string {
  $value = strtolower(trim($value));
  $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);
  if ($ascii !== false && $ascii !== null) {
    $value = $ascii;
  }

  $value = preg_replace('/[^a-z0-9]+/', '-', $value);
  $value = trim((string) $value, '-');

  return $value !== '' ? $value : 'producto';
}

function xml_escape(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function wrap_svg_text(string $text, int $maxLength = 18, int $maxLines = 3): array {
  $words = preg_split('/\s+/', trim($text)) ?: [];
  $lines = [];
  $current = '';

  foreach ($words as $word) {
    $candidate = $current === '' ? $word : "{$current} {$word}";
    if (mb_strlen($candidate) <= $maxLength || $current === '') {
      $current = $candidate;
      continue;
    }

    $lines[] = $current;
    $current = $word;

    if (count($lines) === $maxLines - 1) {
      break;
    }
  }

  if ($current !== '' && count($lines) < $maxLines) {
    $lines[] = $current;
  }

  $remaining = array_slice($words, count(explode(' ', implode(' ', $lines))));
  if ($remaining && $lines) {
    $lastIndex = count($lines) - 1;
    $lastLine = $lines[$lastIndex];
    $trimmed = mb_substr($lastLine, 0, max(0, $maxLength - 1));
    $lines[$lastIndex] = rtrim($trimmed) . '…';
  }

  return $lines ?: ['Producto'];
}

function category_palette(int $categoryId): array {
  $palettes = [
    1 => ['#0f172a', '#1d4ed8', '#dbeafe', '#93c5fd'],
    2 => ['#3f2f1f', '#d97706', '#fef3c7', '#fcd34d'],
    3 => ['#111827', '#dc2626', '#fee2e2', '#fca5a5'],
    4 => ['#1f2937', '#0891b2', '#cffafe', '#67e8f9'],
    5 => ['#4c1d95', '#ec4899', '#fce7f3', '#f9a8d4'],
    6 => ['#14532d', '#16a34a', '#dcfce7', '#86efac'],
    7 => ['#0f172a', '#14b8a6', '#ccfbf1', '#5eead4'],
  ];

  return $palettes[$categoryId] ?? ['#1f2937', '#4b5563', '#e5e7eb', '#9ca3af'];
}

function category_illustration(int $categoryId): string {
  switch ($categoryId) {
    case 1:
      return <<<SVG
<g transform="translate(0 10)">
  <rect x="60" y="20" width="240" height="460" rx="38" fill="rgba(255,255,255,.16)" stroke="rgba(255,255,255,.65)" stroke-width="10"/>
  <rect x="86" y="62" width="188" height="332" rx="22" fill="rgba(255,255,255,.22)"/>
  <circle cx="180" cy="436" r="20" fill="rgba(255,255,255,.55)"/>
</g>
SVG;
    case 2:
      return <<<SVG
<g transform="translate(20 10)">
  <rect x="40" y="84" width="252" height="320" rx="26" fill="rgba(255,255,255,.18)" stroke="rgba(255,255,255,.64)" stroke-width="10"/>
  <rect x="78" y="40" width="176" height="74" rx="18" fill="rgba(255,255,255,.24)"/>
  <line x1="86" y1="178" x2="246" y2="178" stroke="rgba(255,255,255,.66)" stroke-width="12" stroke-linecap="round"/>
  <line x1="86" y1="234" x2="224" y2="234" stroke="rgba(255,255,255,.5)" stroke-width="12" stroke-linecap="round"/>
  <line x1="86" y1="290" x2="236" y2="290" stroke="rgba(255,255,255,.5)" stroke-width="12" stroke-linecap="round"/>
</g>
SVG;
    case 3:
      return <<<SVG
<g transform="translate(0 60)">
  <rect x="58" y="162" width="238" height="86" rx="24" fill="rgba(255,255,255,.22)"/>
  <path d="M96 162 L148 110 H236 L274 162 Z" fill="rgba(255,255,255,.34)"/>
  <circle cx="126" cy="272" r="34" fill="rgba(255,255,255,.7)"/>
  <circle cx="246" cy="272" r="34" fill="rgba(255,255,255,.7)"/>
  <circle cx="126" cy="272" r="16" fill="rgba(15,23,42,.22)"/>
  <circle cx="246" cy="272" r="16" fill="rgba(15,23,42,.22)"/>
</g>
SVG;
    case 4:
      return <<<SVG
<g transform="translate(0 18)">
  <rect x="84" y="28" width="194" height="454" rx="34" fill="rgba(255,255,255,.18)" stroke="rgba(255,255,255,.66)" stroke-width="10"/>
  <line x1="84" y1="256" x2="278" y2="256" stroke="rgba(255,255,255,.56)" stroke-width="10"/>
  <circle cx="182" cy="430" r="16" fill="rgba(255,255,255,.58)"/>
</g>
SVG;
    case 5:
      return <<<SVG
<g transform="translate(4 24)">
  <path d="M128 72 L182 30 L236 72 L280 122 L248 154 L226 126 L226 454 L138 454 L138 126 L116 154 L84 122 Z" fill="rgba(255,255,255,.24)" stroke="rgba(255,255,255,.68)" stroke-width="10" stroke-linejoin="round"/>
</g>
SVG;
    case 6:
      return <<<SVG
<g transform="translate(8 96)">
  <path d="M92 154 Q92 102 146 102 H214 Q268 102 268 154 V200 Q268 248 222 248 H206 L178 284 L150 248 H138 Q92 248 92 200 Z" fill="rgba(255,255,255,.24)" stroke="rgba(255,255,255,.66)" stroke-width="10" stroke-linejoin="round"/>
  <circle cx="148" cy="176" r="18" fill="rgba(255,255,255,.72)"/>
  <circle cx="212" cy="176" r="18" fill="rgba(255,255,255,.72)"/>
</g>
SVG;
    case 7:
      return <<<SVG
<g transform="translate(0 38)">
  <rect x="134" y="34" width="80" height="420" rx="18" fill="rgba(255,255,255,.24)"/>
  <rect x="44" y="154" width="260" height="80" rx="18" fill="rgba(255,255,255,.24)"/>
  <rect x="74" y="184" width="200" height="20" rx="10" fill="rgba(255,255,255,.7)"/>
  <rect x="164" y="64" width="20" height="360" rx="10" fill="rgba(255,255,255,.7)"/>
</g>
SVG;
    default:
      return <<<SVG
<g transform="translate(22 46)">
  <rect x="70" y="56" width="216" height="360" rx="28" fill="rgba(255,255,255,.2)" stroke="rgba(255,255,255,.64)" stroke-width="10"/>
</g>
SVG;
  }
}

function build_product_svg(string $name, string $categoryLabel, string $variantLabel, int $categoryId, int $frameIndex): string {
  [$ink, $accent, $soft, $glow] = category_palette($categoryId);
  $titleLines = wrap_svg_text($name, 18, 3);
  $lineMarkup = '';
  $y = 256;

  foreach ($titleLines as $line) {
    $safeLine = xml_escape($line);
    $lineMarkup .= "<tspan x=\"98\" y=\"{$y}\">{$safeLine}</tspan>";
    $y += 78;
  }

  $safeCategory = xml_escape($categoryLabel);
  $safeVariant = xml_escape($variantLabel);
  $seedNote = xml_escape("Galeria local {$frameIndex}");
  $illustration = category_illustration($categoryId);

  return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 900" role="img" aria-label="{$safeCategory} {$safeVariant}">
  <defs>
    <linearGradient id="bg" x1="0" x2="1" y1="0" y2="1">
      <stop offset="0%" stop-color="{$ink}"/>
      <stop offset="100%" stop-color="{$accent}"/>
    </linearGradient>
    <linearGradient id="panel" x1="0" x2="1" y1="0" y2="1">
      <stop offset="0%" stop-color="rgba(255,255,255,.96)"/>
      <stop offset="100%" stop-color="{$soft}"/>
    </linearGradient>
  </defs>

  <rect width="1200" height="900" fill="url(#bg)"/>
  <circle cx="1042" cy="138" r="174" fill="{$glow}" opacity=".34"/>
  <circle cx="114" cy="790" r="148" fill="rgba(255,255,255,.08)"/>
  <rect x="54" y="54" width="1092" height="792" rx="42" fill="rgba(255,255,255,.08)" stroke="rgba(255,255,255,.12)" stroke-width="2"/>

  <g transform="translate(0 0)">
    <rect x="72" y="72" width="632" height="756" rx="36" fill="url(#panel)"/>
    <rect x="98" y="116" width="198" height="42" rx="21" fill="{$accent}" opacity=".14"/>
    <text x="118" y="144" font-family="Segoe UI, Arial, sans-serif" font-size="24" font-weight="700" fill="{$accent}">{$safeCategory}</text>
    <text x="98" y="256" font-family="Segoe UI, Arial, sans-serif" font-size="64" font-weight="800" fill="{$ink}">{$lineMarkup}</text>
    <text x="98" y="596" font-family="Segoe UI, Arial, sans-serif" font-size="30" font-weight="700" fill="{$accent}">{$safeVariant}</text>
    <text x="98" y="648" font-family="Segoe UI, Arial, sans-serif" font-size="24" fill="#475569">{$seedNote}</text>
    <text x="98" y="704" font-family="Segoe UI, Arial, sans-serif" font-size="24" fill="#475569">Producto preparado para pruebas visuales del marketplace</text>
    <rect x="98" y="742" width="220" height="14" rx="7" fill="{$glow}" opacity=".7"/>
    <rect x="98" y="772" width="162" height="14" rx="7" fill="{$accent}" opacity=".3"/>
  </g>

  <g transform="translate(748 152)">
    <rect x="0" y="0" width="352" height="508" rx="42" fill="rgba(255,255,255,.12)" stroke="rgba(255,255,255,.18)" stroke-width="2"/>
    {$illustration}
  </g>
</svg>
SVG;
}

function ensure_product_gallery(string $name, string $categoryLabel, int $categoryId, int $count): array {
  $variants = ['Vista principal', 'Detalle', 'Ficha rapida', 'Presentacion', 'Coleccion'];
  $slug = slugify($name);
  $targetDir = __DIR__ . '/../public/uploads/seed_products';
  $publicPrefix = '/public/uploads/seed_products';

  if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
  }

  $urls = [];
  for ($index = 0; $index < $count; $index++) {
    $variantLabel = $variants[$index] ?? ('Imagen ' . ($index + 1));
    $filename = "{$slug}-" . ($index + 1) . '.svg';
    $absolutePath = $targetDir . '/' . $filename;
    $svg = build_product_svg($name, $categoryLabel, $variantLabel, $categoryId, $index + 1);
    file_put_contents($absolutePath, $svg);
    $urls[] = "{$publicPrefix}/{$filename}";
  }

  return $urls;
}

$categoryNames = [
  1 => 'Electronica',
  2 => 'Papeleria',
  3 => 'Vehiculos',
  4 => 'Electrodomesticos',
  5 => 'Moda',
  6 => 'Juegos y juguetes',
  7 => 'Salud y equipo medico',
];

$sellers = [
  'tech.carla@seed.utzm.local' => 'Carla Tech',
  'tech.bruno@seed.utzm.local' => 'Bruno Mobile',
  'papeleria.mariana@seed.utzm.local' => 'Mariana Campus',
  'vehiculos.esteban@seed.utzm.local' => 'Esteban Motor',
  'hogar.lucia@seed.utzm.local' => 'Lucia Hogar',
  'moda.andrea@seed.utzm.local' => 'Andrea Moda',
  'moda.sofia@seed.utzm.local' => 'Sofia Sneakers',
  'juegos.diego@seed.utzm.local' => 'Diego Play',
  'salud.paula@seed.utzm.local' => 'Paula Salud',
];

$products = [
  [
    'seller_email' => 'tech.carla@seed.utzm.local',
    'category_id' => 1,
    'name' => 'Apple iPhone 15 Pro 256 GB Titanio Natural',
    'description' => 'iPhone 15 Pro de 256 GB desbloqueado. Pantalla Super Retina XDR de 6.1 pulgadas, chip A17 Pro, camara principal de 48 MP y grabacion ProRes. Equipo en muy buen estado, con cable USB-C y salud de bateria superior al 90%.',
    'price_cents' => 1899900,
    'stock' => 2,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'tech.bruno@seed.utzm.local',
    'category_id' => 1,
    'name' => 'Samsung Galaxy S24 Ultra 256 GB Titanium Black',
    'description' => 'Galaxy S24 Ultra con S Pen incluido, pantalla Dynamic AMOLED 2X de 6.8 pulgadas, 12 GB de RAM y camara principal de 200 MP. Version libre para cualquier compania, ideal para productividad y fotografia.',
    'price_cents' => 2149900,
    'stock' => 2,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'tech.carla@seed.utzm.local',
    'category_id' => 1,
    'name' => 'Lenovo ThinkPad T14 Gen 5 Ryzen 7 32 GB RAM',
    'description' => 'Laptop ThinkPad T14 Gen 5 con procesador AMD Ryzen 7 PRO, 32 GB de RAM, SSD de 1 TB y pantalla WUXGA de 14 pulgadas. Equipo orientado a trabajo profesional, con teclado retroiluminado y lector de huella.',
    'price_cents' => 2799900,
    'stock' => 3,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'tech.bruno@seed.utzm.local',
    'category_id' => 1,
    'name' => 'Sony WH-1000XM5 Audifonos Bluetooth',
    'description' => 'Audifonos Sony WH-1000XM5 con cancelacion de ruido activa, autonomia de hasta 30 horas y carga rapida por USB-C. Compatibles con multipunto y asistentes de voz.',
    'price_cents' => 659900,
    'stock' => 4,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'tech.carla@seed.utzm.local',
    'category_id' => 1,
    'name' => 'Canon EOS R50 Kit RF-S 18-45 mm',
    'description' => 'Camara mirrorless Canon EOS R50 con sensor APS-C de 24.2 MP, grabacion 4K y lente RF-S 18-45 mm. Excelente opcion para contenido, retrato y fotografia de viaje.',
    'price_cents' => 1649900,
    'stock' => 2,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'papeleria.mariana@seed.utzm.local',
    'category_id' => 2,
    'name' => 'Cuaderno Moleskine Classic Large Rayado',
    'description' => 'Cuaderno Moleskine Classic Large de tapa dura con 240 paginas rayadas color marfil. Formato 13 x 21 cm, bolsillo interior, cierre elastico y papel libre de acido.',
    'price_cents' => 64900,
    'stock' => 8,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'papeleria.mariana@seed.utzm.local',
    'category_id' => 2,
    'name' => 'Set de Plumones Pilot G2 Premium Gel 12 Piezas',
    'description' => 'Juego de 12 plumones Pilot G2 con tinta gel de secado rapido, punta fina de 0.7 mm y agarre comodo. Recomendados para apuntes, oficina y escritura prolongada.',
    'price_cents' => 42900,
    'stock' => 10,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'papeleria.mariana@seed.utzm.local',
    'category_id' => 2,
    'name' => 'Lapices Staedtler Mars Lumograph 12B-6H',
    'description' => 'Set Staedtler Mars Lumograph con 12 lapices de grafito para dibujo tecnico y artistico. Incluye graduaciones desde 6H hasta 12B, con mina resistente al quiebre.',
    'price_cents' => 37900,
    'stock' => 6,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'papeleria.mariana@seed.utzm.local',
    'category_id' => 2,
    'name' => 'Pluma Fuente Lamy Safari Charcoal Punto M',
    'description' => 'Pluma fuente Lamy Safari color charcoal con cuerpo ABS, plumilla mediana de acero inoxidable y sistema de cartucho o convertidor. Un clasico para escritura diaria.',
    'price_cents' => 69900,
    'stock' => 5,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'papeleria.mariana@seed.utzm.local',
    'category_id' => 2,
    'name' => 'Calculadora Cientifica Casio FX-991LA CW',
    'description' => 'Calculadora cientifica Casio FX-991LA CW con 540 funciones, hoja de calculo, codigos QR para graficas y alimentacion dual. Ideal para bachillerato, ingenieria y ciencias.',
    'price_cents' => 59900,
    'stock' => 7,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'vehiculos.esteban@seed.utzm.local',
    'category_id' => 3,
    'name' => 'Toyota Corolla LE 2022 Automatico',
    'description' => 'Sedan Toyota Corolla LE modelo 2022 con motor 2.0 litros, transmision CVT, camara de reversa y paquete Toyota Safety Sense. Historial de servicios al dia y factura original.',
    'price_cents' => 3799000,
    'stock' => 1,
    'gallery_count' => 3,
  ],
  [
    'seller_email' => 'vehiculos.esteban@seed.utzm.local',
    'category_id' => 3,
    'name' => 'Volkswagen Jetta Comfortline 2020',
    'description' => 'Volkswagen Jetta Comfortline 2020 con motor turbo 1.4 TSI, pantalla tactil, aire acondicionado automatico y seis bolsas de aire. Auto familiar con consumo eficiente y espacio amplio.',
    'price_cents' => 3285000,
    'stock' => 1,
    'gallery_count' => 3,
  ],
  [
    'seller_email' => 'vehiculos.esteban@seed.utzm.local',
    'category_id' => 3,
    'name' => 'Nissan NP300 Frontier XE 2021',
    'description' => 'Pickup Nissan NP300 Frontier XE 2021 con motor 2.5 litros, caja manual de seis velocidades, batea protegida y capacidad de carga para trabajo ligero o negocio.',
    'price_cents' => 4049000,
    'stock' => 1,
    'gallery_count' => 3,
  ],
  [
    'seller_email' => 'vehiculos.esteban@seed.utzm.local',
    'category_id' => 3,
    'name' => 'Honda CB190R 2023 Seminueva',
    'description' => 'Motocicleta Honda CB190R 2023 con motor monocilindrico de 184 cc, freno delantero de disco y tablero digital. Excelente para ciudad, con mantenimiento reciente.',
    'price_cents' => 569000,
    'stock' => 1,
    'gallery_count' => 3,
  ],
  [
    'seller_email' => 'vehiculos.esteban@seed.utzm.local',
    'category_id' => 3,
    'name' => 'Yamaha FZ-S FI 2022',
    'description' => 'Yamaha FZ-S FI 2022 con motor 149 cc de inyeccion electronica, freno delantero ABS y postura comoda para trayectos diarios. Unidad cuidada, lista para circular.',
    'price_cents' => 489000,
    'stock' => 1,
    'gallery_count' => 3,
  ],
  [
    'seller_email' => 'hogar.lucia@seed.utzm.local',
    'category_id' => 4,
    'name' => 'Refrigerador LG InstaView 27 pies cubicos',
    'description' => 'Refrigerador LG InstaView Door-in-Door de 27 pies cubicos con dispensador de agua y hielo, compresor Inverter Linear y enfriamiento uniforme. Ideal para familias grandes.',
    'price_cents' => 4299900,
    'stock' => 2,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'hogar.lucia@seed.utzm.local',
    'category_id' => 4,
    'name' => 'Freidora de Aire Ninja Max XL 5.2 L',
    'description' => 'Freidora de aire Ninja Max XL con capacidad de 5.2 litros, temperatura de hasta 232 C y programas para freir, hornear, recalentar y deshidratar. Reduce el uso de aceite sin perder crocancia.',
    'price_cents' => 329900,
    'stock' => 5,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'hogar.lucia@seed.utzm.local',
    'category_id' => 4,
    'name' => 'Lavadora Whirlpool 20 kg Xpert System',
    'description' => 'Lavadora Whirlpool Xpert System de 20 kg con ciclos inteligentes, ahorro de agua y tapa con cierre suave. Recomendada para cargas voluminosas y uso frecuente.',
    'price_cents' => 1429900,
    'stock' => 3,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'hogar.lucia@seed.utzm.local',
    'category_id' => 4,
    'name' => 'Aspiradora Dyson V8 Absolute Inalambrica',
    'description' => 'Aspiradora Dyson V8 Absolute con motor digital, cepillo motorbar y hasta 40 minutos de autonomia. Funciona como escoba electrica y aspiradora de mano.',
    'price_cents' => 799900,
    'stock' => 4,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'hogar.lucia@seed.utzm.local',
    'category_id' => 4,
    'name' => 'Licuadora Oster Reversible con Vaso de Vidrio',
    'description' => 'Licuadora Oster con motor reversible, cuchilla bidireccional y vaso de vidrio Boroclass resistente a cambios de temperatura. Ideal para smoothies, salsas y molidos ligeros.',
    'price_cents' => 239900,
    'stock' => 6,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'moda.andrea@seed.utzm.local',
    'category_id' => 5,
    'name' => 'Jeans Levi\'s 501 Original Fit Azul Medio',
    'description' => 'Jeans Levi\'s 501 Original Fit de mezclilla 100% algodon con corte recto, botonadura frontal y lavado azul medio. Modelo iconico para uso casual diario.',
    'price_cents' => 149900,
    'stock' => 7,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'moda.sofia@seed.utzm.local',
    'category_id' => 5,
    'name' => 'Tenis Adidas Gazelle Indoor Verde',
    'description' => 'Adidas Gazelle Indoor en gamuza verde con suela de goma y silueta clasica. Tenis lifestyle con excelente combinacion para outfits casuales o streetwear.',
    'price_cents' => 239900,
    'stock' => 5,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'moda.sofia@seed.utzm.local',
    'category_id' => 5,
    'name' => 'Nike Air Force 1 \'07 White',
    'description' => 'Nike Air Force 1 \'07 color blanco con upper de piel, media suela con encapsulado Air y suela cupsole. Modelo muy solicitado por su versatilidad y durabilidad.',
    'price_cents' => 259900,
    'stock' => 5,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'moda.andrea@seed.utzm.local',
    'category_id' => 5,
    'name' => 'Lentes Ray-Ban Clubmaster RB3016',
    'description' => 'Lentes Ray-Ban Clubmaster RB3016 con montura combinada en acetato y metal, lentes G-15 y estilo retro atemporal. Accesorio premium unisex.',
    'price_cents' => 319900,
    'stock' => 4,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'moda.andrea@seed.utzm.local',
    'category_id' => 5,
    'name' => 'Reloj Fossil Grant Chronograph FS4735',
    'description' => 'Reloj Fossil Grant Chronograph con caja de 44 mm, correa de piel cafe y movimiento de cuarzo. Diseno clasico para uso formal o casual elegante.',
    'price_cents' => 289900,
    'stock' => 3,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'juegos.diego@seed.utzm.local',
    'category_id' => 6,
    'name' => 'Consola PlayStation 5 Slim 1 TB',
    'description' => 'PlayStation 5 Slim con unidad de disco, SSD de 1 TB y control DualSense. Compatible con juegos fisicos y digitales, salida 4K y audio 3D.',
    'price_cents' => 1199900,
    'stock' => 3,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'juegos.diego@seed.utzm.local',
    'category_id' => 6,
    'name' => 'Xbox Series X 1 TB Carbon Black',
    'description' => 'Xbox Series X con SSD NVMe de 1 TB, compatibilidad con Game Pass y rendimiento hasta 4K a 120 fps en juegos seleccionados. Incluye control inalambrico original.',
    'price_cents' => 1129900,
    'stock' => 3,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'juegos.diego@seed.utzm.local',
    'category_id' => 6,
    'name' => 'LEGO Star Wars Millennium Falcon 75375',
    'description' => 'Set LEGO Star Wars Millennium Falcon 75375 de la coleccion Starship Collection. Incluye 921 piezas, placa de exhibicion y soporte para coleccionistas.',
    'price_cents' => 184900,
    'stock' => 6,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'juegos.diego@seed.utzm.local',
    'category_id' => 6,
    'name' => 'Nerf Elite 2.0 Commander RD-6',
    'description' => 'Lanzador Nerf Elite 2.0 Commander RD-6 con tambor para seis dardos y accesorios modulares. Juguete recomendado para juego recreativo al aire libre.',
    'price_cents' => 69900,
    'stock' => 8,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'juegos.diego@seed.utzm.local',
    'category_id' => 6,
    'name' => 'Hot Wheels Pack 20 Autos Escala 1:64',
    'description' => 'Pack Hot Wheels de 20 autos metalicos a escala 1:64 con surtido oficial de modelos deportivos, clasicos y fantasia. Excelente para regalo o coleccion inicial.',
    'price_cents' => 52900,
    'stock' => 9,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'salud.paula@seed.utzm.local',
    'category_id' => 7,
    'name' => 'Baumanometro Digital Omron HEM-7140',
    'description' => 'Monitor de presion arterial Omron HEM-7140 con deteccion de latido irregular, inflado automatico y memoria de lecturas. Equipo domestico confiable para seguimiento diario.',
    'price_cents' => 99900,
    'stock' => 6,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'salud.paula@seed.utzm.local',
    'category_id' => 7,
    'name' => 'Glucometro Accu-Chek Guide',
    'description' => 'Glucometro Accu-Chek Guide con pantalla retroiluminada, puerto de tiras iluminado y sincronizacion con app para registrar mediciones. Incluye estuche rigido.',
    'price_cents' => 114900,
    'stock' => 5,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'salud.paula@seed.utzm.local',
    'category_id' => 7,
    'name' => 'Estetoscopio 3M Littmann Classic III',
    'description' => 'Estetoscopio 3M Littmann Classic III para auscultacion general, con campana de doble frecuencia y tubo resistente a aceites y alcohol. Uso comun en consulta y enfermeria.',
    'price_cents' => 259900,
    'stock' => 4,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'salud.paula@seed.utzm.local',
    'category_id' => 7,
    'name' => 'Silla de Ruedas Drive Medical de Aluminio',
    'description' => 'Silla de ruedas Drive Medical plegable de aluminio con descansabrazos abatibles, llantas solidas y capacidad de carga de 113 kg. Opcion ligera para traslado y uso diario.',
    'price_cents' => 389900,
    'stock' => 2,
    'gallery_count' => 2,
  ],
  [
    'seller_email' => 'salud.paula@seed.utzm.local',
    'category_id' => 7,
    'name' => 'Nebulizador Beurer IH 18',
    'description' => 'Nebulizador compresor Beurer IH 18 para vias respiratorias, con alto porcentaje de particulas respirables y accesorios para adulto y nino. Adecuado para tratamientos en casa.',
    'price_cents' => 139900,
    'stock' => 5,
    'gallery_count' => 2,
  ],
];

$defaultAvatar = '/public/uploads/blank-profile.png';
$defaultPasswordHash = password_hash('SeedMarket123!', PASSWORD_BCRYPT);
$hasRoleColumn = (bool) $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'role'")->fetchColumn();
$sellerIds = [];
$createdUsers = 0;
$updatedUsers = 0;
$createdProducts = 0;
$updatedProducts = 0;
$imageRows = 0;

try {
  $pdo->beginTransaction();

  foreach ($sellers as $email => $fullName) {
    $findUser = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $findUser->execute([$email]);
    $userId = $findUser->fetchColumn();

    if ($userId) {
      if ($hasRoleColumn) {
        $updateUser = $pdo->prepare('UPDATE users SET full_name = ?, avatar_url = ?, role = ? WHERE id = ?');
        $updateUser->execute([$fullName, $defaultAvatar, 'customer', $userId]);
      } else {
        $updateUser = $pdo->prepare('UPDATE users SET full_name = ?, avatar_url = ? WHERE id = ?');
        $updateUser->execute([$fullName, $defaultAvatar, $userId]);
      }
      $updatedUsers++;
    } else {
      if ($hasRoleColumn) {
        $insertUser = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, avatar_url, role) VALUES (?,?,?,?,?) RETURNING id');
        $insertUser->execute([$fullName, $email, $defaultPasswordHash, $defaultAvatar, 'customer']);
      } else {
        $insertUser = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, avatar_url) VALUES (?,?,?,?) RETURNING id');
        $insertUser->execute([$fullName, $email, $defaultPasswordHash, $defaultAvatar]);
      }
      $userId = $insertUser->fetchColumn();
      $createdUsers++;
    }

    $sellerIds[$email] = (int) $userId;
  }

  foreach ($products as $product) {
    $sellerId = $sellerIds[$product['seller_email']];
    $findProduct = $pdo->prepare('SELECT id FROM products WHERE seller_id = ? AND name = ? LIMIT 1');
    $findProduct->execute([$sellerId, $product['name']]);
    $productId = $findProduct->fetchColumn();

    if ($productId) {
      $updateProduct = $pdo->prepare('UPDATE products SET description = ?, price_cents = ?, stock = ?, category_id = ? WHERE id = ?');
      $updateProduct->execute([
        $product['description'],
        $product['price_cents'],
        $product['stock'],
        $product['category_id'],
        $productId,
      ]);
      $updatedProducts++;
    } else {
      $insertProduct = $pdo->prepare('INSERT INTO products (seller_id, name, description, price_cents, stock, category_id) VALUES (?,?,?,?,?,?) RETURNING id');
      $insertProduct->execute([
        $sellerId,
        $product['name'],
        $product['description'],
        $product['price_cents'],
        $product['stock'],
        $product['category_id'],
      ]);
      $productId = $insertProduct->fetchColumn();
      $createdProducts++;
    }

    $galleryUrls = ensure_product_gallery(
      $product['name'],
      $categoryNames[$product['category_id']] ?? 'Producto',
      (int) $product['category_id'],
      (int) ($product['gallery_count'] ?? 2)
    );

    $deleteImages = $pdo->prepare('DELETE FROM product_images WHERE product_id = ?');
    $deleteImages->execute([$productId]);

    $insertImage = $pdo->prepare('INSERT INTO product_images (product_id, url, sort_order) VALUES (?,?,?)');
    foreach ($galleryUrls as $index => $url) {
      $insertImage->execute([$productId, $url, $index]);
      $imageRows++;
    }
  }

  $pdo->commit();

  $seededCounts = $pdo->prepare(
    'SELECT p.category_id, COUNT(*) AS total
     FROM products p
     INNER JOIN users u ON u.id = p.seller_id
     WHERE u.email LIKE ?
     GROUP BY p.category_id
     ORDER BY p.category_id'
  );
  $seededCounts->execute(['%@seed.utzm.local']);
  $counts = $seededCounts->fetchAll(PDO::FETCH_ASSOC);

  echo "Seeder ejecutado correctamente.\n";
  echo "Usuarios creados: {$createdUsers}\n";
  echo "Usuarios actualizados: {$updatedUsers}\n";
  echo "Productos creados: {$createdProducts}\n";
  echo "Productos actualizados: {$updatedProducts}\n";
  echo "Filas de imagen insertadas: {$imageRows}\n";
  echo "Conteo de publicaciones sembradas por categoria:\n";

  foreach ($counts as $row) {
    echo "  Categoria {$row['category_id']}: {$row['total']}\n";
  }
} catch (Throwable $error) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }

  http_response_code(500);
  echo "Error al ejecutar el seeder: " . $error->getMessage() . "\n";
  exit(1);
}
