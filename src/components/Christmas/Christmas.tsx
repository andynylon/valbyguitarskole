import { useEffect, useRef, useState } from "react"
import { useBooking } from "@/context/BookingContext"
import { useText } from "@/context/ContentContext"
import { giftCoursePrice, giftCourses } from "@/data/giftCourses"
import { useChristmasLive } from "@/lib/campaign"
import { Arrow } from "@/components/icons/Arrow"
import { BookingModal } from "@/components/BookingModal/BookingModal"
import { Contact } from "@/components/Contact/Contact"
import { Footer } from "@/components/Footer/Footer"
import { Header } from "@/components/Header/Header"
import { Marquee } from "@/components/Marquee/Marquee"
import { Pricing } from "@/components/Pricing/Pricing"
import { Prompts } from "@/components/Prompts/Prompts"
import "./Christmas.scss"

export function ChristmasPage() {
  const { content, tx, language } = useText()
  const { openBooking } = useBooking()
  const christmas = content.christmas
  const { live, days } = useChristmasLive(christmas.until)
  const featured = giftCourses.find((course) => course.offer === "10 lektioner") ?? giftCourses[2]
  const videoRef = useRef<HTMLVideoElement>(null)
  const [withSound, setWithSound] = useState(false)
  const packages = giftCourses.map((course) => {
    const copy = christmas.packageCopy.find((item) => item.offer === course.offer)
    const lesson = content.pricing.offers.find((item) => item.name.da === course.offer)
    return {
      ...course,
      tone: lesson?.tone ?? "cream",
      note: lesson?.note,
      text: copy?.text,
    }
  })

  const count =
    days === null
      ? ""
      : days === 0
        ? tx(christmas.lastDay)
        : `${days} ${tx(days === 1 ? christmas.day : christmas.days)}`

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
  }, [christmas.video])

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
    <main id="top">
      <Header campaign />
      <section className="xmas-hero">
        <div className="xmas-hero-copy">
          <p className="eyebrow">{tx(live ? christmas.eyebrow : christmas.endedEyebrow)}</p>
          <h1>
            {tx(christmas.titleLine1)}
            <br />
            <em>{tx(christmas.titleLine2)}</em>
          </h1>
          <p className="xmas-intro">{tx(christmas.intro)}</p>
          <p className="xmas-deadline">{tx(live ? christmas.deadline : christmas.endedNote)}</p>
          {live && count && <p className="xmas-count">{count}</p>}
          <div className="xmas-actions">
            <a className="button" href="#gavekort">
              {tx(christmas.button)} <Arrow />
            </a>
            <a className="text-link" href="#priser">
              {tx(christmas.lessonsLink)} <Arrow />
            </a>
          </div>
        </div>
        <div className="xmas-hero-visual">
          <div className="xmas-sun" />
          <div className="xmas-frame">
            <div className="xmas-video">
              <video
                ref={videoRef}
                src={christmas.video}
                muted
                loop
                playsInline
                autoPlay
                controls={withSound}
                preload="metadata"
                aria-label={tx(christmas.imageAlt)}
              />
              {!withSound && (
                <button className="xmas-play" type="button" onClick={playWithSound}>
                  <span aria-hidden="true">▶</span>
                  {tx(christmas.play)}
                </button>
              )}
            </div>
            <span className="xmas-credit">{tx(christmas.photoCredit)}</span>
          </div>
          <article className="xmas-voucher">
            <p>{tx(christmas.cardKicker)}</p>
            <h2>{tx(christmas.cardTitle)}</h2>
            <p className="xmas-voucher-line">{tx(christmas.cardLine)}</p>
            <p className="xmas-voucher-amount">{giftCoursePrice(content, language, featured.offer)}</p>
            <p className="xmas-voucher-for">{featured[language]}</p>
          </article>
        </div>
      </section>
      <Marquee items={christmas.marquee} label={christmas.marqueeLabel} />
      <section className="xmas-packages section" id="gavekort">
        <div className="xmas-packages-top">
          <div className="section-heading">
            <p className="eyebrow">{tx(christmas.packagesEyebrow)}</p>
            <h2>
              {tx(christmas.packagesTitle1)}
              <br />
              <em>{tx(christmas.packagesTitle2)}</em>
            </h2>
          </div>
          <p>{tx(christmas.packagesIntro)}</p>
        </div>
        <div className="xmas-grid">
          {packages.map((course) => (
            <article className={`xmas-package ${course.tone}`} key={course.offer}>
              {course.offer === "10 lektioner" && <p className="xmas-badge">{tx(christmas.featured)}</p>}
              <p className="xmas-package-name">{course[language]}</p>
              <h3>{giftCoursePrice(content, language, course.offer)}</h3>
              {course.text && <p className="xmas-package-text">{tx(course.text)}</p>}
              {course.note && <p className="xmas-package-note">{tx(course.note)}</p>}
              <button
                type="button"
                onClick={() => openBooking(tx(content.gift.bookingName), { giftOffer: course.offer })}
              >
                {tx(christmas.give)} <Arrow />
              </button>
            </article>
          ))}
        </div>
      </section>
      <section className="xmas-steps section">
        <div className="section-heading">
          <p className="eyebrow">{tx(christmas.stepsEyebrow)}</p>
          <h2>
            {tx(christmas.stepsTitle1)}
            <br />
            <em>{tx(christmas.stepsTitle2)}</em>
          </h2>
        </div>
        <ol>
          {christmas.steps.map((step, index) => (
            <li key={step.title.da}>
              <span>{String(index + 1).padStart(2, "0")}</span>
              <h3>{tx(step.title)}</h3>
              <p>{tx(step.text)}</p>
            </li>
          ))}
        </ol>
      </section>
      <Pricing
        hideGift
        heading={{
          eyebrow: tx(christmas.lessonsEyebrow),
          titleLine1: tx(christmas.lessonsTitle1),
          titleLine2: tx(christmas.lessonsTitle2),
          intro: tx(christmas.lessonsIntro),
        }}
      />
      <Contact />
      <Footer />
      <BookingModal />
      <Prompts hideGift />
    </main>
  )
}
