import { useBooking } from "@/context/BookingContext"
import { useLanguage } from "@/context/LanguageContext"
import { Arrow } from "@/components/icons/Arrow"
import { Check } from "@/components/icons/Check"
import "./BookingModal.scss"

export function BookingModal() {
  const { t } = useLanguage()
  const { selectedOffer, submitted, closeBooking, markSubmitted } = useBooking()

  if (!selectedOffer) return null

  const giftCard = selectedOffer === t("Gavekort", "Gift card")

  return (
    <div
      className="modal-backdrop"
      role="presentation"
      onMouseDown={(event) => event.target === event.currentTarget && closeBooking()}
    >
      <div className="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <button className="modal-close" onClick={closeBooking} aria-label={t("Luk", "Close")}>
          ×
        </button>
        {submitted ? (
          <div className="success">
            <span>
              <Check />
            </span>
            <p className="eyebrow">{t("Tak for din besked", "Thank you for your message")}</p>
            <h2>{t("Vi glæder os til at spille med dig.", "We look forward to playing with you.")}</h2>
            <p>
              {t(
                "Vi vender tilbage inden for én hverdag med forslag til en tid.",
                "We will get back to you within one working day with a suggested time.",
              )}
            </p>
            <button className="button" onClick={closeBooking}>
              {t("Luk vinduet", "Close window")}
            </button>
          </div>
        ) : (
          <>
            <p className="eyebrow">{t("Næste skridt", "Next step")}</p>
            <h2 id="modal-title">
              {giftCard
                ? t("Bestil et gavekort", "Order a gift card")
                : `${t("Book", "Book")} ${selectedOffer.toLowerCase()}`}
            </h2>
            <p className="modal-intro">
              {t(
                "Udfyld dine oplysninger, så kontakter vi dig og finder den helt rigtige løsning. Du betaler først, når tiden er aftalt.",
                "Fill in your details and we will contact you to find the right solution. You only pay once a time has been agreed.",
              )}
            </p>
            <form
              onSubmit={(event) => {
                event.preventDefault()
                markSubmitted()
              }}
            >
              <label>
                {t("Navn", "Name")}
                <input name="name" autoFocus required placeholder={t("Dit navn", "Your name")} />
              </label>
              <label>
                {t("E-mail", "Email")}
                <input
                  type="email"
                  name="email"
                  required
                  placeholder={t("dig@email.dk", "you@email.com")}
                />
              </label>
              <label>
                {t("Telefon", "Phone")}
                <input type="tel" name="phone" required placeholder="+45 12 34 56 78" />
              </label>
              <label>
                {giftCard
                  ? t("Ønsket beløb", "Desired amount")
                  : t("Hvem skal spille?", "Who will be playing?")}
                <select name="student" defaultValue="">
                  <option value="" disabled>
                    {t("Vælg her", "Choose here")}
                  </option>
                  {giftCard ? (
                    <>
                      <option>{t("500 kr.", "DKK 500")}</option>
                      <option>{t("1.000 kr.", "DKK 1,000")}</option>
                      <option>{t("2.500 kr.", "DKK 2,500")}</option>
                      <option>{t("Andet beløb", "Other amount")}</option>
                    </>
                  ) : (
                    <>
                      <option>{t("Mig selv", "Myself")}</option>
                      <option>{t("Mit barn", "My child")}</option>
                      <option>{t("En anden", "Someone else")}</option>
                    </>
                  )}
                </select>
              </label>
              <button className="button" type="submit">
                {t("Send forespørgsel", "Send enquiry")} <Arrow />
              </button>
            </form>
          </>
        )}
      </div>
    </div>
  )
}
