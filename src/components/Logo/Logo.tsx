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
      <svg viewBox="0 0 42 42" aria-hidden="true">
        <path
          d="M21 3C12.3 3 6 10.2 6 19.6 6 30 15.5 38 21 39c5.5-1 15-9 15-19.4C36 10.2 29.7 3 21 3Z"
          fill="currentColor"
        />
        <circle cx="21" cy="17.5" r="6.5" fill="#F5EEDD" />
        <path d="m18 23 6-11M20 25l6-11" stroke="#F5EEDD" strokeWidth="1.2" />
      </svg>
      <span>
        Valby
        <br />
        <strong>Guitarskole</strong>
      </span>
    </a>
  )
}
