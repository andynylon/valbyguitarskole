import { useState } from "react"
import { useBooking } from "@/context/BookingContext"
import { useText } from "@/context/ContentContext"
import { useLanguage } from "@/context/LanguageContext"
import { Arrow } from "@/components/icons/Arrow"
import { Logo } from "@/components/Logo/Logo"
import "./Header.scss"

export function Header() {
  const { language, setLanguage, t } = useLanguage()
  const { content, tx } = useText()
  const { openBooking } = useBooking()
  const [menuOpen, setMenuOpen] = useState(false)
  const english = language === "en"

  const links = [
    { href: "#undervisning", label: tx(content.nav.lessons) },
    { href: "#priser", label: tx(content.nav.pricing) },
    { href: "#om", label: tx(content.nav.about) },
    { href: "#kontakt", label: tx(content.nav.contact) },
  ]

  const closeMenu = () => setMenuOpen(false)

  return (
    <header className="site-header">
      <Logo />
      <nav
        className={menuOpen ? "nav open" : "nav"}
        aria-label={t("Primær navigation", "Primary navigation")}
      >
        {links.map((link) => (
          <a key={link.href} href={link.href} onClick={closeMenu}>
            {link.label}
          </a>
        ))}
        <div className="language-selector" aria-label={t("Vælg sprog", "Choose language")}>
          <button
            className={english ? "" : "active"}
            aria-pressed={!english}
            onClick={() => setLanguage("da")}
          >
            DA
          </button>
          <span aria-hidden="true">/</span>
          <button
            className={english ? "active" : ""}
            aria-pressed={english}
            onClick={() => setLanguage("en")}
          >
            EN
          </button>
        </div>
        <button
          className="button button-small nav-cta"
          onClick={() => {
            openBooking(tx(content.contact.bookingName))
            closeMenu()
          }}
        >
          {tx(content.nav.book)} <Arrow />
        </button>
      </nav>
      <button
        className="menu-button"
        aria-label={menuOpen ? t("Luk menu", "Close menu") : t("Åbn menu", "Open menu")}
        aria-expanded={menuOpen}
        onClick={() => setMenuOpen((open) => !open)}
      >
        <span />
        <span />
      </button>
    </header>
  )
}
