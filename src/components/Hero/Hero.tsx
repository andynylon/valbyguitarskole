import { useEffect, useRef, useState } from "react"
import { useBooking } from "@/context/BookingContext"
import { useText } from "@/context/ContentContext"
import { Arrow } from "@/components/icons/Arrow"
import "./Hero.scss"

export function Hero() {
  const { content, tx } = useText()
  const { openBooking } = useBooking()
  const hero = content.hero
  const videoRef = useRef<HTMLVideoElement>(null)
  const [withSound, setWithSound] = useState(false)

  useEffect(() => {
    const video = videoRef.current
    if (!video) return
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches
    if (reduceMotion) {
      video.pause()
      return
    }
    video.muted = true
    void video.play().catch(() => {})
  }, [hero.video])

  function playWithSound() {
    const video = videoRef.current
    if (!video) return
    video.loop = false
    video.muted = false
    video.currentTime = 0
    void video.play()
    setWithSound(true)
  }

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
          <div className="welcome-video">
            <video
              ref={videoRef}
              src={hero.video}
              muted
              loop
              playsInline
              autoPlay
              controls={withSound}
              preload="metadata"
              aria-label={tx(hero.imageAlt)}
            />
            {!withSound && (
              <button className="welcome-play" type="button" onClick={playWithSound}>
                <span aria-hidden="true">▶</span>
                {tx(hero.play)}
              </button>
            )}
          </div>
          <span className="photo-credit">{tx(hero.photoCredit)}</span>
        </div>
      </div>
    </section>
  )
}
