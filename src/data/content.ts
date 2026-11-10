import raw from "@/data/content.json"

export type Localized = {
  da: string
  en: string
}

export type SiteContent = typeof raw

export const initialContent: SiteContent = raw

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
