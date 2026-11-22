export type Language = "da" | "en"

export type Localized = Record<Language, string>

export type OfferTone = "cream" | "yellow" | "coral" | "green"

export type Offer = {
  name: Localized
  price: Localized
  text: Localized
  note: Localized
  tone: OfferTone
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
    name: { da: "5 lektioner", en: "5 lessons" },
    price: { da: "1.500 kr.", en: "DKK 1,500" },
    text: {
      da: "Et kort forløb, når du vil i gang uden at binde dig til en hel sæson.",
      en: "A short course when you want to begin without committing to a whole season.",
    },
    note: { da: "5 × 30 min. · 300 kr. pr. lektion", en: "5 × 30 min. · DKK 300 per lesson" },
    tone: "yellow",
  },
  {
    name: { da: "10 lektioner", en: "10 lessons" },
    price: { da: "2.850 kr.", en: "DKK 2,850" },
    text: {
      da: "Til dig, der vil skabe momentum og mærke tydelig udvikling.",
      en: "For players who want to build momentum and make clear progress.",
    },
    note: {
      da: "10 × 30 min. · Spar 150 kr.",
      en: "10 × 30 min. · Save DKK 150",
    },
    tone: "coral",
  },
  {
    name: { da: "Halvt år", en: "Six months" },
    price: { da: "5.750 kr.", en: "DKK 5,750" },
    text: {
      da: "Fast ugentlig tid og et personligt forløb gennem hele sæsonen.",
      en: "A fixed weekly time and a personal course throughout the season.",
    },
    note: {
      da: "20 × 30 min. · Spar 250 kr.",
      en: "20 × 30 min. · Save DKK 250",
    },
    tone: "green",
  },
]
