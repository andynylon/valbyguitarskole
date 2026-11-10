import { heroImage } from "@/data/images"
import { useBooking } from "@/context/BookingContext"
import { useText } from "@/context/ContentContext"
import { Arrow } from "@/components/icons/Arrow"
import "./Hero.scss"

export function Hero() {
  const { content, tx } = useText()
  const { openBooking } = useBooking()
  const hero = content.hero

  return (
    <section className="hero">
      <div className="hero-copy">
        <p className="eyebrow">{tx(hero.eyebrow)}</p>
        <h1>
          {tx(hero.titleLine1)}
          <br />
          <em>{tx(hero.titleLine2)}</em>
        </h1>
        <p className="hero-intro">{tx(hero.intro)}</p>
        <div className="hero-actions">
          <button className="button" onClick={() => openBooking(tx(content.contact.bookingName))}>
            {tx(hero.book)} <Arrow />
          </button>
          <a href="#priser" className="text-link">
            {tx(hero.prices)} <Arrow />
          </a>
        </div>
      </div>
      <div className="hero-visual">
        <div className="sun-shape" />
        <div className="photo-frame">
          <img
            src={heroImage}
            alt={tx(hero.imageAlt)}
          />
          <span className="photo-credit">{tx(hero.photoCredit)}</span>
        </div>
        <div className="hand-note">
          <span>♪</span>
          {tx(hero.noteLine1)}
          <br />
          {tx(hero.noteLine2)}
        </div>
      </div>
    </section>
  )
}
