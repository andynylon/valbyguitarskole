import { useText } from "@/context/ContentContext"
import { useBooking } from "@/context/BookingContext"
import { useChristmasLive } from "@/lib/campaign"
import { Arrow } from "@/components/icons/Arrow"
import { Check } from "@/components/icons/Check"
import "./Pricing.scss"

type PricingHeading = {
  eyebrow: string
  titleLine1: string
  titleLine2: string
  intro: string
}

export function Pricing({
  hideGift = false,
  heading,
}: {
  hideGift?: boolean
  heading?: PricingHeading
}) {
  const { content, tx } = useText()
  const { openBooking } = useBooking()
  const { live } = useChristmasLive(content.christmas.until)
  const pricing = content.pricing
  const eyebrow = heading?.eyebrow ?? tx(pricing.eyebrow)
  const titleLine1 = heading?.titleLine1 ?? tx(pricing.titleLine1)
  const titleLine2 = heading?.titleLine2 ?? tx(pricing.titleLine2)
  const intro = heading?.intro ?? tx(pricing.intro)

  return (
    <section className="pricing section" id="priser">
      <div className="pricing-top">
        <div className="section-heading">
          <p className="eyebrow">{eyebrow}</p>
          <h2>
            {titleLine1}
            <br />
            <em>{titleLine2}</em>
          </h2>
        </div>
        <p>{intro}</p>
      </div>
      <div className="offer-grid">
        {pricing.offers.map((offer) => (
          <article className={`offer-card ${offer.tone}`} key={offer.name.da}>
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
      {!hideGift && (
        <div className="gift-card">
          <div className="gift-icon" aria-hidden="true">
            <span>♪</span>
          </div>
          <div>
            <p className="eyebrow">{tx(content.gift.eyebrow)}</p>
            <h3>{tx(content.gift.title)}</h3>
            <p>{tx(content.gift.text)}</p>
            {live && (
              <a className="gift-campaign-link" href="/jul">
                {tx(content.christmas.homeLink)} <Arrow />
              </a>
            )}
          </div>
          <button
            className="button button-light"
            onClick={() => openBooking(tx(content.gift.bookingName))}
          >
            {tx(content.gift.button)} <Arrow />
          </button>
        </div>
      )}
    </section>
  )
}
