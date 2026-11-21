import { useEffect, useRef, useState } from "react"
import { useBooking } from "@/context/BookingContext"
import { useText } from "@/context/ContentContext"
import { useNotices } from "@/context/NoticeContext"
import { Arrow } from "@/components/icons/Arrow"
import { readCookie, writeCookie } from "@/lib/cookies"
import "./Prompts.scss"

const GIFT_COOKIE = "vg_gift"
const NEWSLETTER_COOKIE = "vg_newsletter"
const COOKIE_NOTICE = "vg_cookies"

function useAfter(delay: number, active: boolean, blocked: boolean) {
  const [ready, setReady] = useState(false)
  const started = useRef<number | null>(null)

  useEffect(() => {
    if (!active || ready) return
    if (started.current === null) started.current = Date.now()
    if (blocked) return
    const remaining = Math.max(0, delay - (Date.now() - started.current))
    const timer = window.setTimeout(() => setReady(true), remaining)
    return () => window.clearTimeout(timer)
  }, [active, blocked, delay, ready])

  return ready
}

export function Prompts() {
  const { content, tx } = useText()
  const { selectedOffer, newsletter, openBooking, openNewsletter } = useBooking()
  const { campaignOpen } = useNotices()
  const [giftState, setGiftState] = useState<string | null>(null)
  const [newsletterDone, setNewsletterDone] = useState(false)
  const [cookiesAccepted, setCookiesAccepted] = useState(false)
  const [preferencesRead, setPreferencesRead] = useState(false)
  const [giftClosedThisVisit, setGiftClosedThisVisit] = useState(false)
  const [teaserClosed, setTeaserClosed] = useState(false)
  const arrivedRejected = useRef(false)

  useEffect(() => {
    const gift = readCookie(GIFT_COOKIE)
    arrivedRejected.current = gift === "rejected"
    setGiftState(gift)
    setNewsletterDone(readCookie(NEWSLETTER_COOKIE) === "done")
    setCookiesAccepted(readCookie(COOKIE_NOTICE) === "1")
    setPreferencesRead(true)
  }, [])
  const returnOpened = useRef(false)
  const sawNewsletter = useRef(false)
  const giftDialog = useRef<HTMLDivElement>(null)

  const blocked = Boolean(selectedOffer || newsletter || campaignOpen)
  const giftReady = useAfter(10_000, preferencesRead && !giftState, blocked)
  const showGift = giftReady && !blocked && !giftState
  const teaserReady = useAfter(
    10_000,
    giftClosedThisVisit && !newsletterDone && !teaserClosed,
    blocked || showGift,
  )
  const showTeaser = teaserReady && !blocked && !showGift && !teaserClosed && !newsletterDone
  const newsletterReady = useAfter(
    5_000,
    preferencesRead && arrivedRejected.current && !newsletterDone,
    blocked || showGift,
  )

  useEffect(() => {
    if (!newsletterReady || blocked || newsletterDone || returnOpened.current) return
    returnOpened.current = true
    openNewsletter()
  }, [newsletterReady, blocked, newsletterDone, openNewsletter])

  useEffect(() => {
    if (newsletter) sawNewsletter.current = true
    if (!newsletter && sawNewsletter.current) {
      sawNewsletter.current = false
      writeCookie(NEWSLETTER_COOKIE, "done")
      setNewsletterDone(true)
    }
  }, [newsletter])

  useEffect(() => {
    if (!showGift) return
    const previous = document.body.style.overflow
    document.body.style.overflow = "hidden"
    giftDialog.current?.focus()
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") rejectGift()
    }
    window.addEventListener("keydown", onKey)
    return () => {
      if (!document.querySelector(".modal, .campaign")) document.body.style.overflow = previous
      window.removeEventListener("keydown", onKey)
    }
  }, [showGift])

  function rejectGift() {
    writeCookie(GIFT_COOKIE, "rejected")
    setGiftState("rejected")
    setGiftClosedThisVisit(true)
  }

  function buyGift() {
    writeCookie(GIFT_COOKIE, "opened")
    setGiftState("opened")
    setGiftClosedThisVisit(true)
    openBooking(tx(content.gift.bookingName))
  }

  function acceptCookies() {
    writeCookie(COOKIE_NOTICE, "1")
    setCookiesAccepted(true)
  }

  return (
    <>
      {showGift && (
        <div
          className="prompt-backdrop"
          role="presentation"
          onMouseDown={(event) => event.target === event.currentTarget && rejectGift()}
        >
          <div
            className="gift-prompt"
            role="dialog"
            aria-modal="true"
            aria-labelledby="gift-prompt-title"
            tabIndex={-1}
            ref={giftDialog}
          >
            <button className="gift-prompt-close" type="button" onClick={rejectGift} aria-label={tx(content.gift.close)}>
              ×
            </button>
            <div className="gift-prompt-head">
              <p className="eyebrow">{tx(content.gift.eyebrow)}</p>
            </div>
            <div className="gift-prompt-body">
              <h2 id="gift-prompt-title">{tx(content.gift.title)}</h2>
              <p>{tx(content.gift.text)}</p>
              <button className="button" type="button" onClick={buyGift}>
                {tx(content.gift.button)} <Arrow />
              </button>
              <button className="prompt-dismiss" type="button" onClick={rejectGift}>
                {tx(content.gift.dismiss)}
              </button>
            </div>
          </div>
        </div>
      )}
      {(showTeaser || !cookiesAccepted) && (
        <div className="prompt-dock">
          {showTeaser && (
            <aside className="newsletter-teaser">
              <button
                className="teaser-close"
                type="button"
                onClick={() => setTeaserClosed(true)}
                aria-label={tx(content.newsletter.teaserClose)}
              >
                ×
              </button>
              <p className="eyebrow">{tx(content.newsletter.eyebrow)}</p>
              <h2>{tx(content.newsletter.title)}</h2>
              <p>{tx(content.newsletter.teaser)}</p>
              <button className="button button-small" type="button" onClick={() => {
                setTeaserClosed(true)
                openNewsletter()
              }}>
                {tx(content.newsletter.button)} <Arrow />
              </button>
            </aside>
          )}
          {preferencesRead && !cookiesAccepted && (
            <div className="cookie-bar" role="dialog" aria-labelledby="cookie-text">
              <p id="cookie-text">{tx(content.cookies.text)}</p>
              <button className="button button-small button-light" type="button" onClick={acceptCookies}>
                {tx(content.cookies.button)}
              </button>
            </div>
          )}
        </div>
      )}
    </>
  )
}
