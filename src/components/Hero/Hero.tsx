import { heroImage } from "@/data/images"
import { useBooking } from "@/context/BookingContext"
import { useLanguage } from "@/context/LanguageContext"
import { Arrow } from "@/components/icons/Arrow"
import "./Hero.scss"

export function Hero() {
  const { t } = useLanguage()
  const { openBooking } = useBooking()

  return (
    <section className="hero">
      <div className="hero-copy">
        <p className="eyebrow">
          {t("Guitarundervisning midt i Valby", "Guitar lessons in the heart of Valby")}
        </p>
        <h1>
          {t("Find din lyd.", "Find your sound.")}
          <br />
          <em>{t("I dit tempo.", "At your pace.")}</em>
        </h1>
        <p className="hero-intro">
          {t(
            "Personlig guitarundervisning for børn, unge og voksne — uanset om du skal lære din første akkord eller finpudse dit spil på højt niveau.",
            "Personal guitar lessons for children, young people and adults — whether you are learning your first chord or refining your playing at an advanced level.",
          )}
        </p>
        <div className="hero-actions">
          <button className="button" onClick={() => openBooking(t("Prøvetime", "Trial lesson"))}>
            {t("Book en prøvetime", "Book a trial lesson")} <Arrow />
          </button>
          <a href="#priser" className="text-link">
            {t("Se priser og forløb", "See prices and courses")} <Arrow />
          </a>
        </div>
      </div>
      <div className="hero-visual">
        <div className="sun-shape" />
        <div className="photo-frame">
          <img
            src={heroImage}
            alt={t(
              "Guitarist spiller akustisk guitar i naturligt lys",
              "Guitarist playing an acoustic guitar in natural light",
            )}
          />
          <span className="photo-credit">
            {t("Foto", "Photo")}: Gabriel Gurrola / Unsplash
          </span>
        </div>
        <div className="hand-note">
          <span>♪</span>
          {t("Her starter", "Music starts")}
          <br />
          {t("musikken", "here")}
        </div>
      </div>
    </section>
  )
}
