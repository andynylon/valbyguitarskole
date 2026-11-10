import { useText } from "@/context/ContentContext"
import { useBooking } from "@/context/BookingContext"
import { Arrow } from "@/components/icons/Arrow"
import { Check } from "@/components/icons/Check"
import "./Pricing.scss"

export function Pricing() {
  const { content, tx } = useText()
  const { openBooking } = useBooking()
  const pricing = content.pricing

  return (
    <section className="pricing section" id="priser">
      <div className="pricing-top">
        <div className="section-heading">
          <p className="eyebrow">{tx(pricing.eyebrow)}</p>
          <h2>
            {tx(pricing.titleLine1)}
            <br />
            <em>{tx(pricing.titleLine2)}</em>
          </h2>
        </div>
        <p>{tx(pricing.intro)}</p>
      </div>
      <div className="offer-grid">
        {pricing.offers.map((offer) => (
          <article className={`offer-card ${offer.tone}`} key={offer.name.da}>
            {offer.featured && <span className="popular">{tx(pricing.popular)}</span>}
            <p className="offer-name">{tx(offer.name)}</p>
            <h3>{tx(offer.price)}</h3>
            <p className="offer-text">{tx(offer.text)}</p>
            <p className="offer-note">
              <Check /> {tx(offer.note)}
            </p>
            <button onClick={() => openBooking(tx(offer.name))}>
              {tx(pricing.choose)} {tx(offer.name).toLowerCase()} <Arrow />
            </button>
          </article>
        ))}
      </div>
      <div className="gift-card">
        <div className="gift-icon" aria-hidden="true">
          <span>♪</span>
        </div>
        <div>
          <p className="eyebrow">{tx(content.gift.eyebrow)}</p>
          <h3>{tx(content.gift.title)}</h3>
          <p>{tx(content.gift.text)}</p>
        </div>
        <button
          className="button button-light"
          onClick={() => openBooking(tx(content.gift.bookingName))}
        >
          {tx(content.gift.button)} <Arrow />
        </button>
      </div>
    </section>
  )
}
