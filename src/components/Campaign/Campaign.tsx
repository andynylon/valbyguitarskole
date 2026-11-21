import { useEffect, useRef, useState } from "react"
import { useBooking } from "@/context/BookingContext"
import { useText } from "@/context/ContentContext"
import { useNotices } from "@/context/NoticeContext"
import { Arrow } from "@/components/icons/Arrow"
import "./Campaign.scss"

const STORAGE_KEY = "vg-campaign-black-friday-2026-half"

function campaignIsCurrent(active: string, until: string) {
  if (active.trim().toLowerCase() !== "ja") return false
  if (!until.trim()) return true
  const end = new Date(`${until}T23:59:59`)
  return !Number.isNaN(end.getTime()) && Date.now() <= end.getTime()
}

function rememberDismissal() {
  try {
    localStorage.setItem(STORAGE_KEY, "1")
  } catch {
    // Private browsing can block storage. The overlay still closes for this visit.
  }
}

function alreadyDismissed() {
  try {
    return localStorage.getItem(STORAGE_KEY) === "1"
  } catch {
    return false
  }
}

export function Campaign() {
  const { content, tx } = useText()
  const { selectedOffer, newsletter, openBooking } = useBooking()
  const { setCampaignOpen } = useNotices()
  const campaign = content.campaign
  const current = campaignIsCurrent(campaign.active, campaign.until)
  const [visible, setVisible] = useState(false)
  const skipped = useRef(false)
  const dialog = useRef<HTMLDivElement>(null)

  useEffect(() => {
    setCampaignOpen(visible)
    return () => setCampaignOpen(false)
  }, [visible, setCampaignOpen])

  useEffect(() => {
    if (selectedOffer || newsletter) skipped.current = true
  }, [selectedOffer, newsletter])

  useEffect(() => {
    if (!current || alreadyDismissed() || skipped.current || selectedOffer || newsletter) return
    const timer = window.setTimeout(() => setVisible(true), 800)
    return () => window.clearTimeout(timer)
  }, [current, selectedOffer, newsletter])

  useEffect(() => {
    if (!visible) return
    const previous = document.body.style.overflow
    document.body.style.overflow = "hidden"
    dialog.current?.focus()
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        rememberDismissal()
        setVisible(false)
      }
    }
    window.addEventListener("keydown", onKey)
    return () => {
      if (!document.querySelector(".modal")) document.body.style.overflow = previous
      window.removeEventListener("keydown", onKey)
    }
  }, [visible])

  if (!visible) return null

  const close = () => {
    rememberDismissal()
    setVisible(false)
  }

  const book = () => {
    rememberDismissal()
    setVisible(false)
    openBooking(tx(campaign.bookingName))
  }

  return (
    <div
      className="campaign-backdrop"
      role="presentation"
      onMouseDown={(event) => event.target === event.currentTarget && close()}
    >
      <div
        className="campaign"
        role="dialog"
        aria-modal="true"
        aria-labelledby="campaign-title"
        tabIndex={-1}
        ref={dialog}
      >
        <button className="campaign-close" onClick={close} aria-label={tx(campaign.close)}>
          ×
        </button>
        <div className="campaign-offer">
          <p className="eyebrow">{tx(campaign.eyebrow)}</p>
          <p className="campaign-price">
            {tx(campaign.price)}
            <span>{tx(campaign.was)}</span>
          </p>
        </div>
        <div className="campaign-body">
          <h2 id="campaign-title">
            {tx(campaign.titleLine1)}
            <br />
            <em>{tx(campaign.titleLine2)}</em>
          </h2>
          <p>{tx(campaign.text)}</p>
          <button className="button" onClick={book}>
            {tx(campaign.button)} <Arrow />
          </button>
          <button className="campaign-dismiss" onClick={close}>
            {tx(campaign.dismiss)}
          </button>
        </div>
      </div>
    </div>
  )
}
