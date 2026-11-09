import { offers } from "@/data/offers"
import { useBooking } from "@/context/BookingContext"
import { useLanguage } from "@/context/LanguageContext"
import { Arrow } from "@/components/icons/Arrow"
import { Check } from "@/components/icons/Check"
import "./Pricing.scss"

export function Pricing() {
  const { language, t } = useLanguage()
  const { openBooking } = useBooking()

  return (
    <section className="pricing section" id="priser">
      <div className="pricing-top">
        <div className="section-heading">
          <p className="eyebrow">{t("Vælg dit forløb", "Choose your course")}</p>
          <h2>
            {t("En god start.", "A great start.")}
            <br />
            <em>{t("På dine vilkår.", "On your terms.")}</em>
          </h2>
        </div>
        <p>
          {t(
            "Alle forløb er 1:1-undervisning og tilpasses dit niveau, din musiksmag og det, du gerne vil kunne. Alle priser er for lektioner på 30 minutter.",
            "Every course is taught one-to-one and tailored to your level, musical taste and goals. All prices are for 30-minute lessons.",
          )}
        </p>
      </div>
      <div className="offer-grid">
        {offers.map((offer) => (
          <article className={`offer-card ${offer.tone}`} key={offer.name.da}>
            {offer.featured && (
              <span className="popular">{t("Mest valgt", "Most popular")}</span>
            )}
            <p className="offer-name">{offer.name[language]}</p>
            <h3>{offer.price[language]}</h3>
            <p className="offer-text">{offer.text[language]}</p>
            <p className="offer-note">
              <Check /> {offer.note[language]}
            </p>
            <button onClick={() => openBooking(offer.name[language])}>
              {t("Vælg", "Choose")} {offer.name[language].toLowerCase()} <Arrow />
            </button>
          </article>
        ))}
      </div>
      <div className="gift-card">
        <div className="gift-icon" aria-hidden="true">
          <span>♪</span>
        </div>
        <div>
          <p className="eyebrow">{t("Giv musik i gave", "Give the gift of music")}</p>
          <h3>
            {t(
              "Et gavekort, der bliver ved med at spille",
              "A gift card that keeps on playing",
            )}
          </h3>
          <p>
            {t(
              "Vælg selv beløbet, og få et flot digitalt gavekort klar til at give videre.",
              "Choose the amount and receive a beautiful digital gift card, ready to give.",
            )}
          </p>
        </div>
        <button
          className="button button-light"
          onClick={() => openBooking(t("Gavekort", "Gift card"))}
        >
          {t("Køb gavekort", "Buy a gift card")} <Arrow />
        </button>
      </div>
    </section>
  )
}
