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
  submitted: boolean
  openBooking: (offer: string) => void
  closeBooking: () => void
  markSubmitted: () => void
}

const BookingContext = createContext<BookingContextValue | null>(null)

export function BookingProvider({ children }: { children: ReactNode }) {
  const [selectedOffer, setSelectedOffer] = useState<string | null>(null)
  const [submitted, setSubmitted] = useState(false)

  useEffect(() => {
    document.body.style.overflow = selectedOffer ? "hidden" : ""
    return () => {
      document.body.style.overflow = ""
    }
  }, [selectedOffer])

  const openBooking = useCallback((offer: string) => {
    setSubmitted(false)
    setSelectedOffer(offer)
  }, [])

  const closeBooking = useCallback(() => {
    setSelectedOffer(null)
    setSubmitted(false)
  }, [])

  const markSubmitted = useCallback(() => {
    setSubmitted(true)
  }, [])

  const value = useMemo(
    () => ({
      selectedOffer,
      submitted,
      openBooking,
      closeBooking,
      markSubmitted,
    }),
    [selectedOffer, submitted, openBooking, closeBooking, markSubmitted],
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
