import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react"

type BookingOptions = {
  giftOffer?: string
}

type BookingContextValue = {
  selectedOffer: string | null
  giftOffer: string | null
  newsletter: boolean
  submitted: boolean
  openBooking: (offer: string, options?: BookingOptions) => void
  openNewsletter: () => void
  closeBooking: () => void
  markSubmitted: () => void
}

const BookingContext = createContext<BookingContextValue | null>(null)

export function BookingProvider({ children }: { children: ReactNode }) {
  const [selectedOffer, setSelectedOffer] = useState<string | null>(null)
  const [giftOffer, setGiftOffer] = useState<string | null>(null)
  const [newsletter, setNewsletter] = useState(false)
  const [submitted, setSubmitted] = useState(false)

  useEffect(() => {
    document.body.style.overflow = selectedOffer || newsletter ? "hidden" : ""
    return () => {
      document.body.style.overflow = ""
    }
  }, [selectedOffer, newsletter])

  const openBooking = useCallback((offer: string, options?: BookingOptions) => {
    setSubmitted(false)
    setNewsletter(false)
    setGiftOffer(options?.giftOffer ?? null)
    setSelectedOffer(offer)
  }, [])

  const openNewsletter = useCallback(() => {
    setSubmitted(false)
    setSelectedOffer(null)
    setGiftOffer(null)
    setNewsletter(true)
  }, [])

  const closeBooking = useCallback(() => {
    setSelectedOffer(null)
    setGiftOffer(null)
    setNewsletter(false)
    setSubmitted(false)
  }, [])

  const markSubmitted = useCallback(() => {
    setSubmitted(true)
  }, [])

  const value = useMemo(
    () => ({
      selectedOffer,
      giftOffer,
      newsletter,
      submitted,
      openBooking,
      openNewsletter,
      closeBooking,
      markSubmitted,
    }),
    [selectedOffer, giftOffer, newsletter, submitted, openBooking, openNewsletter, closeBooking, markSubmitted],
  )

  return (
    <BookingContext.Provider value={value}>{children}</BookingContext.Provider>
  )
}

export function useBooking() {
  const context = useContext(BookingContext)
  if (!context) {
    throw new Error("useBooking must be used within BookingProvider")
  }
  return context
}
