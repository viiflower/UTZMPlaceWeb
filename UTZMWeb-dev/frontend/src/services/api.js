async function parseJson(response) {
  try {
    return await response.json()
  } catch {
    return {}
  }
}

export async function request(url, options = {}) {
  const response = await fetch(url, {
    credentials: 'include',
    ...options,
    headers: {
      ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
      ...(options.headers ?? {}),
    },
  })

  const data = await parseJson(response)
  if (!response.ok || data.error) {
    const error = new Error(data.error ?? 'No se pudo completar la operacion.')
    error.data = data
    error.status = response.status
    throw error
  }

  return data
}
