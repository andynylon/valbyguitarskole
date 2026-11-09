export type Language = "da" | "en"

export type Localized = Record<Language, string>

export type OfferTone = "cream" | "yellow" | "coral" | "green"

export type Offer = {
  name: Localized
  price: Localized
  text: Localized
  note: Localized
  tone: OfferTone
  featured?: boolean
}

export const offers: Offer[] = [
  {
    name: { da: "Prøvetime", en: "Trial lesson" },
    price: { da: "300 kr.", en: "DKK 300" },
    text: {
      da: "Mærk efter, om undervisningen og kemien passer til dig.",
      en: "See whether the teaching style and personal chemistry feel right.",
    },
    note: { da: "30 min. · Ingen binding", en: "30 min. · No commitment" },
    tone: "cream",
  },
  {
    name: { da: "10 lektioner", en: "10 lessons" },
    price: { da: "2.700 kr.", en: "DKK 2,700" },
    text: {
      da: "Til dig, der vil skabe momentum og mærke tydelig udvikling.",
      en: "For players who want to build momentum and make clear progress.",
    },
    note: {
      da: "10 × 30 min. · Spar 300 kr.",
      en: "10 × 30 min. · Save DKK 300",
    },
    tone: "coral",
    featured: true,
  },
  {
    name: { da: "Halvt år", en: "Six months" },
    price: { da: "5.100 kr.", en: "DKK 5,100" },
    text: {
      da: "Fast ugentlig tid og et personligt forløb gennem hele sæsonen.",
      en: "A fixed weekly time and a personal course throughout the season.",
    },
    note: {
      da: "20 × 30 min. · 850 kr./md. i 6 mdr.",
      en: "20 × 30 min. · DKK 850/month for 6 months",
    },
    tone: "green",
  },
]
