import raw from "@/data/content.json"

export type Localized = {
  da: string
  en: string
}

export type SiteContent = typeof raw

export const initialContent: SiteContent = raw

export function fillMissingContent<T>(current: T, fallback: T): T {
  if (Array.isArray(current) && Array.isArray(fallback)) {
    return current.map((item, index) => {
      const fallbackItem = fallback[index]
      if (
        item &&
        fallbackItem &&
        typeof item === "object" &&
        typeof fallbackItem === "object" &&
        !Array.isArray(item) &&
        !Array.isArray(fallbackItem)
      ) {
        return fillMissingContent(item, fallbackItem)
      }
      return item
    }) as T
  }
  if (!fallback || typeof fallback !== "object" || Array.isArray(fallback)) return current
  if (!current || typeof current !== "object" || Array.isArray(current)) return current
  const next = { ...(current as Record<string, unknown>) }
  for (const [key, value] of Object.entries(fallback as Record<string, unknown>)) {
    if (!(key in next)) next[key] = value
    else next[key] = fillMissingContent(next[key], value)
  }
  return next as T
}

export function isSiteContent(value: unknown): value is SiteContent {
  if (!value || typeof value !== "object") return false
  const content = value as Partial<SiteContent>
  return (
    typeof content.updated === "string" &&
    typeof content.hero?.titleLine1?.da === "string" &&
    typeof content.hero?.titleLine1?.en === "string" &&
    Array.isArray(content.pricing?.offers) &&
    content.pricing.offers.length > 0
  )
}
