import type { SiteContent } from "@/data/content"
import type { Language } from "@/data/offers"

export const giftCourses = [
  { offer: "Prøvetime", da: "1 lektion", en: "1 lesson", price: { da: "300 kr.", en: "DKK 300" } },
  { offer: "5 lektioner", da: "5 lektioner", en: "5 lessons", price: { da: "1.500 kr.", en: "DKK 1,500" } },
  { offer: "10 lektioner", da: "10 lektioner", en: "10 lessons", price: { da: "2.850 kr.", en: "DKK 2,850" } },
  { offer: "Halvt år", da: "20 lektioner", en: "20 lessons", price: { da: "5.750 kr.", en: "DKK 5,750" } },
] as const

export type GiftCourseOffer = (typeof giftCourses)[number]["offer"]

export function giftCoursePrice(content: SiteContent, language: Language, offer: string) {
  const course = giftCourses.find((item) => item.offer === offer)
  if (!course) return ""
  const match = content.pricing.offers.find((item) => item.name.da === course.offer)
  return match?.price[language] ?? course.price[language]
}

export function giftPackageLabel(content: SiteContent, language: Language, offer: string) {
  const course = giftCourses.find((item) => item.offer === offer)
  if (!course) return ""
  return `${course[language]} · ${giftCoursePrice(content, language, offer)}`
}

export function giftPackageOptions(content: SiteContent, language: Language) {
  return giftCourses.map((course) => giftPackageLabel(content, language, course.offer))
}
