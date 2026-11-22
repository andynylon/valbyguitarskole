import { useEffect, useRef, useState } from "react"
import { useBooking } from "@/context/BookingContext"
import { useText } from "@/context/ContentContext"
import { useNotices } from "@/context/NoticeContext"
import { Arrow } from "@/components/icons/Arrow"
import { readCookie, writeCookie } from "@/lib/cookies"
import "./Prompts.scss"

const GIFT_COOKIE = "vg_gift"
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
  const { selectedOffer, newsletter, openBooking } = useBooking()
  const { campaignOpen, campaignSettled } = useNotices()
  const [giftState, setGiftState] = useState<string | null>(null)
  const [cookiesAccepted, setCookiesAccepted] = useState(false)
  const [preferencesRead, setPreferencesRead] = useState(false)
  const giftDialog = useRef<HTMLDivElement>(null)

  useEffect(() => {
    setGiftState(readCookie(GIFT_COOKIE))
    setCookiesAccepted(readCookie(COOKIE_NOTICE) === "1")
    setPreferencesRead(true)
  }, [])

  const blocked = Boolean(selectedOffer || newsletter || campaignOpen)
  const giftReady = useAfter(10_000, preferencesRead && !giftState && campaignSettled, blocked)
  const showGift = giftReady && !blocked && !giftState

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
  }

  function buyGift() {
    writeCookie(GIFT_COOKIE, "opened")
    setGiftState("opened")
    openBooking(tx(content.gift.bookingName))
  }

  function dismissCookies() {
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
      {preferencesRead && !cookiesAccepted && (
        <div className="prompt-dock">
          <div className="cookie-bar" role="dialog" aria-labelledby="cookie-text">
            <button
              className="cookie-close"
              type="button"
              onClick={dismissCookies}
              aria-label={tx(content.cookies.close)}
            >
              ×
            </button>
            <p id="cookie-text">{tx(content.cookies.text)}</p>
            <button className="button button-small button-light" type="button" onClick={dismissCookies}>
              {tx(content.cookies.button)}
            </button>
          </div>
        </div>
      )}
    </>
  )
}
