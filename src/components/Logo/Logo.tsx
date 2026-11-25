import { useLanguage } from "@/context/LanguageContext"
import "./Logo.scss"

export function Logo({ onDark = false }: { onDark?: boolean }) {
  const { t } = useLanguage()

  return (
    <a
      href="#top"
      className={onDark ? "logo logo--on-dark" : "logo"}
      aria-label={t(
        "Valby Guitarskole, gå til forsiden",
        "Valby Guitar School, go to homepage",
      )}
    >
      <img
        src="/logos/logo-forest-transparent-1800x480.png"
        alt=""
        width={1800}
        height={480}
      />
    </a>
  )
}
