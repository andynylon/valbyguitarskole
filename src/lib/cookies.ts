export function readCookie(name: string) {
  const row = document.cookie.split("; ").find((part) => part.startsWith(`${name}=`))
  if (!row) return null
  return decodeURIComponent(row.slice(name.length + 1))
}

export function writeCookie(name: string, value: string) {
  const maxAge = 60 * 60 * 24 * 365
  document.cookie = `${name}=${encodeURIComponent(value)}; Max-Age=${maxAge}; Path=/; SameSite=Lax`
}
