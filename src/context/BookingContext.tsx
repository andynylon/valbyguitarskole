import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react"

type BookingContextValue = {
  selectedOffer: string | null
  newsletter: boolean
  submitted: boolean
  openBooking: (offer: string) => void
  openNewsletter: () => void
  closeBooking: () => void
  markSubmitted: () => void
}

const BookingContext = createContext<BookingContextValue | null>(null)

export function BookingProvider({ children }: { children: ReactNode }) {
  const [selectedOffer, setSelectedOffer] = useState<string | null>(null)
  const [newsletter, setNewsletter] = useState(false)
  const [submitted, setSubmitted] = useState(false)

  useEffect(() => {
    document.body.style.overflow = selectedOffer || newsletter ? "hidden" : ""
    return () => {
      document.body.style.overflow = ""
    }
  }, [selectedOffer, newsletter])

  const openBooking = useCallback((offer: string) => {
    setSubmitted(false)
    setNewsletter(false)
    setSelectedOffer(offer)
  }, [])

  const openNewsletter = useCallback(() => {
    setSubmitted(false)
    setSelectedOffer(null)
    setNewsletter(true)
  }, [])

  const closeBooking = useCallback(() => {
    setSelectedOffer(null)
    setNewsletter(false)
    setSubmitted(false)
  }, [])

  const markSubmitted = useCallback(() => {
    setSubmitted(true)
  }, [])

  const value = useMemo(
    () => ({
      selectedOffer,
      newsletter,
      submitted,
      openBooking,
      openNewsletter,
      closeBooking,
      markSubmitted,
    }),
    [selectedOffer, newsletter, submitted, openBooking, openNewsletter, closeBooking, markSubmitted],
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
