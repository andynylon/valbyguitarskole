import { useBooking } from "@/context/BookingContext"
import { useText } from "@/context/ContentContext"
import { Arrow } from "@/components/icons/Arrow"
import "./Contact.scss"

export function Contact() {
  const { content, tx } = useText()
  const { openBooking, openNewsletter } = useBooking()
  const contact = content.contact

  return (
    <section className="contact section" id="kontakt">
      <div>
        <p className="eyebrow">{tx(contact.eyebrow)}</p>
        <h2>
          {tx(contact.titleLine1)}
          <br />
          <em>{tx(contact.titleLine2)}</em>
        </h2>
      </div>
      <div className="contact-side">
        <p>{tx(contact.text)}</p>
        <div className="contact-actions">
          <button
            className="button button-light"
            onClick={() => openBooking(tx(contact.bookingName))}
          >
            {tx(contact.button)} <Arrow />
          </button>
          <button className="button button-ghost" onClick={openNewsletter}>
            {tx(content.newsletter.button)}
          </button>
        </div>
      </div>
    </section>
  )
}
