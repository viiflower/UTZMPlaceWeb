const GUEST_KEY = 'guest'

function safeParse(rawValue) {
  if (!rawValue) {
    return {}
  }

  try {
    const parsed = JSON.parse(rawValue)
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch {
    return {}
  }
}

export function getStorageUserKey(user) {
  return user?.id ? String(user.id) : GUEST_KEY
}

export function readCollection(key, userKey = GUEST_KEY) {
  const storage = safeParse(localStorage.getItem(key))
  return Array.isArray(storage[userKey]) ? storage[userKey] : []
}

export function writeCollection(key, userKey, items) {
  const storage = safeParse(localStorage.getItem(key))
  storage[userKey] = Array.isArray(items) ? items : []
  localStorage.setItem(key, JSON.stringify(storage))
}

export function migrateGuestCollection(key, userKey) {
  const storage = safeParse(localStorage.getItem(key))
  const guestItems = Array.isArray(storage[GUEST_KEY]) ? storage[GUEST_KEY] : []
  const userItems = Array.isArray(storage[userKey]) ? storage[userKey] : []

  if (!guestItems.length || userItems.length) {
    return
  }

  storage[userKey] = guestItems
  storage[GUEST_KEY] = []
  localStorage.setItem(key, JSON.stringify(storage))
}
