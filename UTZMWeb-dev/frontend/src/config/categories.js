export const CATEGORIES = [
  { id: 1, slug: 'electronica', label: 'Electrónica' },
  { id: 2, slug: 'papeleria', label: 'Papelería' },
  { id: 3, slug: 'vehiculos', label: 'Vehículos' },
  { id: 4, slug: 'electrodomesticos', label: 'Electrodomésticos' },
  { id: 5, slug: 'moda', label: 'Moda' },
  { id: 6, slug: 'juegos', label: 'Juegos y juguetes' },
  { id: 7, slug: 'salud', label: 'Salud y equipo médico' },
]

export function getCategoryLabel(value) {
  const category = CATEGORIES.find((item) => Number(item.id) === Number(value))
  return category?.label ?? 'Sin categoría'
}
