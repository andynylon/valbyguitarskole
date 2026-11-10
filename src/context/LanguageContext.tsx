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
  const [language, setLanguage] = useState<Language>("da")
  const [storedLanguageLoaded, setStoredLanguageLoaded] = useState(false)

  useEffect(() => {
    const saved = localStorage.getItem("valby-language")
    if (saved === "en") setLanguage("en")
    setStoredLanguageLoaded(true)
  }, [])

  useEffect(() => {
    if (!storedLanguageLoaded) return

    document.documentElement.lang = language
    localStorage.setItem("valby-language", language)
  }, [language, storedLanguageLoaded])

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
