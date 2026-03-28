let paypalSdkPromise = null
let paypalSdkKey = ''

export function loadPaypalSdk({ clientId, currency }) {
  if (!clientId) {
    return Promise.reject(new Error('Falta configurar el client ID de PayPal.'))
  }

  const normalizedCurrency = String(currency || 'MXN').toUpperCase()
  const nextKey = `${clientId}:${normalizedCurrency}`

  if (window.paypal && paypalSdkKey === nextKey) {
    return Promise.resolve(window.paypal)
  }

  if (paypalSdkPromise && paypalSdkKey === nextKey) {
    return paypalSdkPromise
  }

  const existing = document.getElementById('paypal-js-sdk')
  if (existing && paypalSdkKey !== nextKey) {
    existing.remove()
  }

  paypalSdkKey = nextKey
  paypalSdkPromise = new Promise((resolve, reject) => {
    const currentScript = document.getElementById('paypal-js-sdk')
    if (currentScript) {
      const handleLoad = () => {
        cleanup()
        if (window.paypal) {
          resolve(window.paypal)
        } else {
          paypalSdkPromise = null
          reject(new Error('No se pudo inicializar el SDK de PayPal.'))
        }
      }

      const handleError = () => {
        cleanup()
        paypalSdkPromise = null
        reject(new Error('No se pudo cargar el SDK de PayPal.'))
      }

      const cleanup = () => {
        currentScript.removeEventListener('load', handleLoad)
        currentScript.removeEventListener('error', handleError)
      }

      currentScript.addEventListener('load', handleLoad)
      currentScript.addEventListener('error', handleError)
      return
    }

    const script = document.createElement('script')
    script.id = 'paypal-js-sdk'
    script.src = `https://www.paypal.com/sdk/js?client-id=${encodeURIComponent(clientId)}&currency=${encodeURIComponent(normalizedCurrency)}`
    script.async = true
    script.onload = () => {
      if (window.paypal) {
        resolve(window.paypal)
      } else {
        paypalSdkPromise = null
        reject(new Error('No se pudo inicializar el SDK de PayPal.'))
      }
    }
    script.onerror = () => {
      paypalSdkPromise = null
      reject(new Error('No se pudo cargar el SDK de PayPal.'))
    }
    document.body.appendChild(script)
  })

  return paypalSdkPromise
}
