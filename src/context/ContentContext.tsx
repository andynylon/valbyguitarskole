import {
  createContext,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from "react"
import { initialContent, isSiteContent, type SiteContent } from "@/data/content"
import { useLanguage } from "@/context/LanguageContext"

type ContentContextValue = {
  content: SiteContent
}

const ContentContext = createContext<ContentContextValue | null>(null)

export function ContentProvider({ children }: { children: ReactNode }) {
  const [content, setContent] = useState(initialContent)

  useEffect(() => {
    const controller = new AbortController()

    fetch("/api/content.php", { cache: "no-store", signal: controller.signal })
      .then((response) => (response.ok ? response.json() : null))
      .then((next: unknown) => {
        if (!isSiteContent(next) || next.updated === content.updated) return
        setContent(next)
      })
      .catch(() => {})

    return () => controller.abort()
  }, [content.updated])

  return <ContentContext.Provider value={{ content }}>{children}</ContentContext.Provider>
}

export function useContent() {
  const context = useContext(ContentContext)
  if (!context) throw new Error("useContent must be used within ContentProvider")
  return context.content
}

export function useText() {
  const { language } = useLanguage()
  const content = useContent()
  const tx = (value: { da: string; en: string }) => value[language] || value.da
  return { content, tx, language }
}

export function DocumentMeta() {
  const { language } = useLanguage()
  const content = useContent()

  useEffect(() => {
    document.title = content.meta.title[language]
    document.querySelector('meta[name="description"]')?.setAttribute(
      "content",
      content.meta.description[language],
    )
  }, [content, language])

  return null
}
