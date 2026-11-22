import raw from "@/data/content.json"

export type Localized = {
  da: string
  en: string
}

export type SiteContent = typeof raw

export const initialContent: SiteContent = raw

function offerTone(item: unknown): string | null {
  if (!item || typeof item !== "object" || Array.isArray(item)) return null
  const tone = (item as { tone?: unknown }).tone
  return typeof tone === "string" && tone !== "" ? tone : null
}

function isOfferList(current: unknown[], fallback: unknown[]): boolean {
  return (
    fallback.length > 0 &&
    fallback.every((item) => offerTone(item) !== null) &&
    current.every((item) => offerTone(item) !== null)
  )
}

function fillOfferList(current: unknown[], fallback: unknown[]): unknown[] {
  const byTone = new Map<string, unknown>()
  for (const item of current) {
    const tone = offerTone(item)
    if (tone) byTone.set(tone, item)
  }

  const used = new Set<string>()
  const merged: unknown[] = []
  for (const fallbackItem of fallback) {
    const tone = offerTone(fallbackItem)
    if (!tone) continue
    used.add(tone)
    const existing = byTone.get(tone)
    merged.push(existing ? fillMissingContent(existing, fallbackItem) : fallbackItem)
  }
  for (const item of current) {
    const tone = offerTone(item)
    if (tone && !used.has(tone)) merged.push(item)
  }
  return merged
}

export function fillMissingContent<T>(current: T, fallback: T): T {
  if (Array.isArray(current) && Array.isArray(fallback)) {
    if (isOfferList(current, fallback)) {
      return fillOfferList(current, fallback) as T
    }
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
