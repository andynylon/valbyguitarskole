import { useLanguage } from "@/context/LanguageContext"
import { Logo } from "@/components/Logo/Logo"
import "./Footer.scss"

export function Footer() {
  const { t } = useLanguage()

  return (
    <footer>
      <div className="footer-brand">
        <Logo onDark />
        <p>
          {t("Nærværende guitarundervisning", "Personal guitar lessons")}
          <br />
          {t("for hele Valby.", "for all of Valby.")}
        </p>
      </div>
      <div>
        <p className="footer-label">{t("Find os", "Find us")}</p>
        <p>
          Valby Langgade 142
          <br />
          2500 Valby
        </p>
      </div>
      <div>
        <p className="footer-label">{t("Kontakt", "Contact")}</p>
        <a href="mailto:hej@valbyguitarskole.dk">hej@valbyguitarskole.dk</a>
        <a href="tel:+4526321840">+45 26 32 18 40</a>
      </div>
      <div>
        <p className="footer-label">{t("Følg med", "Follow us")}</p>
        <a href="#instagram">Instagram</a>
        <a href="#facebook">Facebook</a>
      </div>
      <p className="copyright">© 2025 Valby Guitarskole</p>
    </footer>
  )
}
