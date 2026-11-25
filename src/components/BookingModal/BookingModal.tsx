import { useEffect, useState, type FormEvent } from "react"
import { useBooking } from "@/context/BookingContext"
import { useText } from "@/context/ContentContext"
import { useLanguage } from "@/context/LanguageContext"
import { giftPackageLabel, giftPackageOptions } from "@/data/giftCourses"
import { Arrow } from "@/components/icons/Arrow"
import { Check } from "@/components/icons/Check"
import "./BookingModal.scss"

export function BookingModal() {
  const { t, language } = useLanguage()
  const { content, tx } = useText()
  const { selectedOffer, giftOffer, newsletter, submitted, closeBooking, markSubmitted } = useBooking()
  const [sending, setSending] = useState(false)
  const [error, setError] = useState(false)
  const [studentChoice, setStudentChoice] = useState("")
  const [instrumentChoice, setInstrumentChoice] = useState("")
  const [classSize, setClassSize] = useState("1")
  const [ageChoice, setAgeChoice] = useState("")

  const groupNames = content.pricing.group.bookingName
  const groupOffer = selectedOffer === groupNames.da || selectedOffer === groupNames.en

  useEffect(() => {
    setStudentChoice("")
    setInstrumentChoice("")
    setClassSize(groupOffer ? "2" : "1")
    setAgeChoice("")
    setError(false)
  }, [selectedOffer, newsletter, groupOffer])

  useEffect(() => {
    if (!giftOffer) return
    setStudentChoice(giftPackageLabel(content, language, giftOffer))
  }, [giftOffer, language, content])

  if (!selectedOffer && !newsletter) return null

  const giftCard = selectedOffer === t("Gavekort", "Gift card")
  const newsletterCopy = content.newsletter
  const campaignOffer =
    selectedOffer === content.campaign.bookingName.da ||
    selectedOffer === content.campaign.bookingName.en

  async function sendEnquiry(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    setSending(true)
    setError(false)

    const student = String(form.get("student") ?? "")
    const chosenClass = groupOffer ? Number(form.get("classSize") ?? 2) : 1

    try {
      const response = await fetch("/api/booking.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({
          kind: newsletter ? "newsletter" : "enquiry",
          offer: selectedOffer ?? "",
          language,
          name: form.get("name"),
          email: form.get("email"),
          phone: form.get("phone"),
          student,
          classSize: chosenClass === 2 || chosenClass === 3 ? chosenClass : 1,
          instrument: form.get("instrument"),
          rental: form.get("rental") === "yes",
          newsletter: newsletter || form.get("newsletter") === "yes",
          moms: !newsletter && ageChoice === "yes",
          recipient: giftCard ? form.get("recipient") : "",
          greeting: giftCard ? form.get("greeting") : "",
          message: form.get("message"),
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
              {newsletter
                ? tx(newsletterCopy.eyebrow)
                : giftCard
                  ? t("Bestillingen er modtaget", "The order is received")
                  : t("Tak for din besked", "Thank you for your message")}
            </p>
            <h2>
              {newsletter
                ? tx(newsletterCopy.successTitle)
                : giftCard
                  ? t("Tjek din mail for betalingen.", "Check your email for payment.")
                  : t("Vi glæder os til at spille med dig.", "We look forward to playing with you.")}
            </h2>
            <p>
              {newsletter
                ? tx(newsletterCopy.successText)
                : giftCard
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
            <p className="eyebrow">
              {newsletter
                ? tx(newsletterCopy.eyebrow)
                : campaignOffer
                  ? tx(content.campaign.eyebrow)
                  : t("Næste skridt", "Next step")}
            </p>
            <h2 id="modal-title">
              {newsletter
                ? tx(newsletterCopy.title)
                : campaignOffer
                  ? tx(content.campaign.formTitle)
                  : giftCard
                    ? t("Bestil et gavekort", "Order a gift card")
                    : `${t("Book", "Book")} ${selectedOffer?.toLowerCase()}`}
            </h2>
            <p className="modal-intro">
              {newsletter
                ? tx(newsletterCopy.intro)
                : campaignOffer
                  ? tx(content.campaign.formIntro)
                  : giftCard
                    ? t(
                        "Vælg et forløb, en modtager og en kort hilsen. Du får en mail med betalingsoplysninger. Gavekortet sendes, når betalingen er gået ind.",
                        "Choose a course, a recipient and a short greeting. You will get an email with payment details. The gift card is sent once the payment has arrived.",
                      )
                    : groupOffer
                      ? tx(content.pricing.group.formIntro)
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
              {!newsletter && (
                <>
                  <label>
                    {t("Telefon", "Phone")}
                    <input type="tel" name="phone" required placeholder="+45 12 34 56 78" />
                  </label>
                  {groupOffer ? (
                    <label>
                      {t("Undervisning", "Lessons")}
                      <select
                        name="classSize"
                        value={classSize}
                        required
                        onChange={(event) => setClassSize(event.target.value)}
                      >
                        <option value="2">{t("Hold på to · 45 min.", "Group of two · 45 min.")}</option>
                        <option value="3">{t("Hold på tre · 60 min.", "Group of three · 60 min.")}</option>
                      </select>
                    </label>
                  ) : (
                    <input type="hidden" name="classSize" value="1" />
                  )}
                  <label>
                    {giftCard ? t("Forløb", "Course") : t("Hvem skal spille?", "Who will be playing?")}
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
                        giftPackageOptions(content, language).map((option) => (
                          <option key={option} value={option}>
                            {option}
                          </option>
                        ))
                      ) : (
                        <>
                          <option>{t("Mig selv", "Myself")}</option>
                          <option>{t("Mit barn", "My child")}</option>
                          <option>{t("En anden", "Someone else")}</option>
                        </>
                      )}
                    </select>
                  </label>
                  <fieldset className="moms-choice">
                    <legend>
                      {giftCard
                        ? t(
                            "Eleven, der skal bruge lektionerne, er 30 år eller derover",
                            "The student who will use the lessons is 30 or older",
                          )
                        : t("Jeg er 30 år eller derover", "I am 30 or older")}
                    </legend>
                    <div className="moms-options">
                      <label className="newsletter-choice">
                        <input
                          type="radio"
                          name="age30"
                          value="yes"
                          required
                          checked={ageChoice === "yes"}
                          onChange={() => setAgeChoice("yes")}
                        />
                        <span>{t("Ja", "Yes")}</span>
                      </label>
                      <label className="newsletter-choice">
                        <input
                          type="radio"
                          name="age30"
                          value="no"
                          required
                          checked={ageChoice === "no"}
                          onChange={() => setAgeChoice("no")}
                        />
                        <span>{t("Nej", "No")}</span>
                      </label>
                    </div>
                    {ageChoice === "yes" && (
                      <p className="moms-note">{momsNote(giftCard ? studentChoice : (selectedOffer ?? ""), t)}</p>
                    )}
                  </fieldset>
                  <label>
                    {tx(content.instruments.label)}
                    <select
                      name="instrument"
                      value={instrumentChoice}
                      required
                      onChange={(event) => setInstrumentChoice(event.target.value)}
                    >
                      <option value="" disabled>
                        {t("Vælg her", "Choose here")}
                      </option>
                      {content.instruments.options.map((option) => (
                        <option key={option.da} value={tx(option)}>
                          {tx(option)}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label className="newsletter-choice">
                    <input type="checkbox" name="rental" value="yes" />
                    <span>{tx(content.instruments.rental)}</span>
                  </label>
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
                  <label className="newsletter-choice">
                    <input type="checkbox" name="newsletter" value="yes" />
                    <span>{tx(newsletterCopy.checkbox)}</span>
                  </label>
                </>
              )}
              <label className="span-2">
                {t("Noget andet", "Anything else")}
                <textarea
                  name="message"
                  maxLength={1000}
                  rows={3}
                  placeholder={t(
                    "Spørgsmål, kommentarer eller andet",
                    "Questions, comments or anything else",
                  )}
                />
              </label>
              <p className="form-note">
                {newsletter
                  ? tx(newsletterCopy.note)
                  : t(
                      "Vi gemmer oplysningerne, så vi kan svare på din forespørgsel. Nyhedsbrevet sender vi kun, hvis du selv krydser af.",
                      "We keep these details so we can answer your enquiry. We only send the newsletter if you tick the box.",
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
                  : newsletter
                    ? tx(newsletterCopy.submit)
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

function momsNote(offer: string, t: (danish: string, english: string) => string) {
  const price = momsPriceLine(offer)
  if (!price) {
    return t(
      "25% moms lægges oven i prisen. Det er fradragsberettiget.",
      "25% VAT is added to the price. It is tax deductible.",
    )
  }

  return t(
    `25% moms lægges oven i prisen (${price.da}). Det er fradragsberettiget.`,
    `25% VAT is added to the price (${price.en}). It is tax deductible.`,
  )
}

function momsPriceLine(offer: string) {
  const prices = [
    {
      test: (value: string) => /halv pris|half price/i.test(value),
      da: "150 kr. → 187,50 kr.",
      en: "DKK 150 → DKK 187.50",
    },
    {
      test: (value: string) => /halvt år|six months|\b20\b/i.test(value),
      da: "5.750 kr. → 7.187,50 kr.",
      en: "DKK 5,750 → DKK 7,187.50",
    },
    {
      test: (value: string) => /\b10\b/.test(value),
      da: "2.850 kr. → 3.562,50 kr.",
      en: "DKK 2,850 → DKK 3,562.50",
    },
    {
      test: (value: string) => /\b5\b/.test(value),
      da: "1.500 kr. → 1.875 kr.",
      en: "DKK 1,500 → DKK 1,875",
    },
    {
      test: (value: string) => /prøvetime|trial|\b1\b/i.test(value),
      da: "300 kr. → 375 kr.",
      en: "DKK 300 → DKK 375",
    },
  ]

  return prices.find((item) => item.test(offer)) ?? null
}
