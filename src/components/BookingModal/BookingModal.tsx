import { useEffect, useState, type FormEvent } from "react"
import { useBooking } from "@/context/BookingContext"
import { useLanguage } from "@/context/LanguageContext"
import { Arrow } from "@/components/icons/Arrow"
import { Check } from "@/components/icons/Check"
import "./BookingModal.scss"

export function BookingModal() {
  const { t, language } = useLanguage()
  const { selectedOffer, submitted, closeBooking, markSubmitted } = useBooking()
  const [sending, setSending] = useState(false)
  const [error, setError] = useState(false)
  const [studentChoice, setStudentChoice] = useState("")

  useEffect(() => {
    setStudentChoice("")
    setError(false)
  }, [selectedOffer])

  if (!selectedOffer) return null

  const giftCard = selectedOffer === t("Gavekort", "Gift card")

  async function sendEnquiry(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    setSending(true)
    setError(false)

    let student = String(form.get("student") ?? "")
    if (giftCard && student === "other") {
      const amount = Number(String(form.get("amount") ?? "").replace(",", "."))
      if (!Number.isFinite(amount) || amount < 1 || amount > 100000) {
        setSending(false)
        setError(true)
        return
      }
      const rounded = Math.round(amount)
      const formatted = new Intl.NumberFormat(language === "en" ? "en-GB" : "da-DK").format(rounded)
      student = language === "en" ? `DKK ${formatted}` : `${formatted} kr.`
    }

    try {
      const response = await fetch("/api/booking.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({
          offer: selectedOffer,
          language,
          name: form.get("name"),
          email: form.get("email"),
          phone: form.get("phone"),
          student,
          recipient: giftCard ? form.get("recipient") : "",
          greeting: giftCard ? form.get("greeting") : "",
          company: form.get("company"),
        }),
      })
      const result = (await response.json()) as { ok?: boolean }
      if (!response.ok || result.ok !== true) throw new Error("Booking request was not accepted")
      markSubmitted()
    } catch {
      setError(true)
    } finally {
      setSending(false)
    }
  }

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
            <p className="eyebrow">
              {giftCard
                ? t("Bestillingen er modtaget", "The order is received")
                : t("Tak for din besked", "Thank you for your message")}
            </p>
            <h2>
              {giftCard
                ? t("Tjek din mail for betalingen.", "Check your email for payment.")
                : t("Vi glæder os til at spille med dig.", "We look forward to playing with you.")}
            </h2>
            <p>
              {giftCard
                ? t(
                    "Gavekortet sendes til dig, når betalingen er gået ind. Du kan så give det videre.",
                    "The gift card is emailed to you once the payment has arrived. You can then pass it on.",
                  )
                : t(
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
              {giftCard
                ? t(
                    "Vælg beløb, modtager og en kort hilsen. Du får en mail med betalingsoplysninger. Gavekortet sendes, når betalingen er gået ind.",
                    "Choose an amount, a recipient and a short greeting. You will get an email with payment details. The gift card is sent once the payment has arrived.",
                  )
                : t(
                    "Udfyld dine oplysninger, så kontakter vi dig og finder den helt rigtige løsning. Du betaler først, når tiden er aftalt.",
                    "Fill in your details and we will contact you to find the right solution. You only pay once a time has been agreed.",
                  )}
            </p>
            <form onSubmit={sendEnquiry}>
              <div className="honeypot" aria-hidden="true">
                <label>
                  Company
                  <input name="company" tabIndex={-1} autoComplete="off" />
                </label>
              </div>
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
                <select
                  name="student"
                  value={studentChoice}
                  required
                  onChange={(event) => setStudentChoice(event.target.value)}
                >
                  <option value="" disabled>
                    {t("Vælg her", "Choose here")}
                  </option>
                  {giftCard ? (
                    <>
                      <option value={t("500 kr.", "DKK 500")}>{t("500 kr.", "DKK 500")}</option>
                      <option value={t("1.000 kr.", "DKK 1,000")}>{t("1.000 kr.", "DKK 1,000")}</option>
                      <option value={t("2.500 kr.", "DKK 2,500")}>{t("2.500 kr.", "DKK 2,500")}</option>
                      <option value="other">{t("Andet beløb", "Other amount")}</option>
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
              {giftCard && studentChoice === "other" && (
                <label>
                  {t("Beløb i kroner", "Amount in kroner")}
                  <input
                    name="amount"
                    type="number"
                    inputMode="numeric"
                    min={1}
                    max={100000}
                    step={1}
                    required
                    placeholder="750"
                  />
                </label>
              )}
              {giftCard && (
                <>
                  <label className="span-2">
                    {t("Hvem er gavekortet til?", "Who is the gift card for?")}
                    <input name="recipient" required placeholder={t("Modtagerens navn", "Recipient's name")} />
                  </label>
                  <label className="span-2">
                    {t("En kort hilsen", "A short greeting")}
                    <textarea
                      name="greeting"
                      maxLength={500}
                      rows={3}
                      placeholder={t("Valgfri hilsen på kortet", "Optional note on the card")}
                    />
                  </label>
                </>
              )}
              <p className="form-note">
                {t(
                  "Vi gemmer oplysningerne, så vi kan svare på din forespørgsel. Vi bruger dem ikke til nyhedsbrev.",
                  "We keep these details so we can answer your enquiry. We do not use them for a newsletter.",
                )}
              </p>
              {error && (
                <p className="form-error" role="alert">
                  {t(
                    "Beskeden kunne ikke sendes. Prøv igen, eller skriv til hej@valbyguitarskole.dk.",
                    "The message could not be sent. Try again, or write to hej@valbyguitarskole.dk.",
                  )}
                </p>
              )}
              <button className="button" type="submit" disabled={sending}>
                {sending
                  ? t("Sender…", "Sending…")
                  : giftCard
                    ? t("Bestil gavekort", "Order gift card")
                    : t("Send forespørgsel", "Send enquiry")}{" "}
                {!sending && <Arrow />}
              </button>
            </form>
          </>
        )}
      </div>
    </div>
  )
}
