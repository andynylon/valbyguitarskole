import { useBooking } from "@/context/BookingContext"
import { useLanguage } from "@/context/LanguageContext"
import { Arrow } from "@/components/icons/Arrow"
import "./Contact.scss"

export function Contact() {
  const { t } = useLanguage()
  const { openBooking } = useBooking()

  return (
    <section className="contact section" id="kontakt">
      <div>
        <p className="eyebrow">{t("Klar til næste skridt?", "Ready for the next step?")}</p>
        <h2>
          {t("Kom forbi.", "Come by.")}
          <br />
          <em>{t("Prøv det af.", "Give it a try.")}</em>
        </h2>
      </div>
      <div className="contact-side">
        <p>
          {t(
            "En prøvetime på 30 minutter koster 300 kr. og kræver ingen binding. Vi finder sammen en tid, der passer.",
            "A 30-minute trial lesson costs DKK 300 with no commitment. Together, we will find a time that works.",
          )}
        </p>
        <button
          className="button button-light"
          onClick={() => openBooking(t("Prøvetime", "Trial lesson"))}
        >
          {t("Book din prøvetime", "Book your trial lesson")} <Arrow />
        </button>
      </div>
    </section>
  )
}
