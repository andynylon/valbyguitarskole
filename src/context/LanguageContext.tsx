import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react"
import type { Language } from "@/data/offers"

type LanguageContextValue = {
  language: Language
  setLanguage: (language: Language) => void
  t: (danish: string, english: string) => string
}

const LanguageContext = createContext<LanguageContextValue | null>(null)

export function LanguageProvider({ children }: { children: ReactNode }) {
  const [language, setLanguage] = useState<Language>(() =>
    localStorage.getItem("valby-language") === "en" ? "en" : "da",
  )

  useEffect(() => {
    const english = language === "en"
    document.documentElement.lang = language
    localStorage.setItem("valby-language", language)
    document.title = english
      ? "Valby Guitar School — Find your sound"
      : "Valby Guitarskole — Find din lyd"
    document.querySelector('meta[name="description"]')?.setAttribute(
      "content",
      english
        ? "Personal guitar lessons in Valby for children, young people and adults — from beginners to professional players."
        : "Nærværende guitarundervisning i Valby for børn, unge og voksne. Book en prøvetime, et klippekort eller et halvt års undervisning.",
    )
  }, [language])

  const value = useMemo<LanguageContextValue>(
    () => ({
      language,
      setLanguage,
      t: (danish, english) => (language === "en" ? english : danish),
    }),
    [language],
  )

  return (
    <LanguageContext.Provider value={value}>{children}</LanguageContext.Provider>
  )
}

export function useLanguage() {
  const context = useContext(LanguageContext)
  if (!context) {
    throw new Error("useLanguage must be used within LanguageProvider")
  }
  return context
}
