import { BookingProvider } from "@/context/BookingContext"
import { LanguageProvider } from "@/context/LanguageContext"
import { About } from "@/components/About/About"
import { Audience } from "@/components/Audience/Audience"
import { BookingModal } from "@/components/BookingModal/BookingModal"
import { Contact } from "@/components/Contact/Contact"
import { Footer } from "@/components/Footer/Footer"
import { Header } from "@/components/Header/Header"
import { Hero } from "@/components/Hero/Hero"
import { Marquee } from "@/components/Marquee/Marquee"
import { Pricing } from "@/components/Pricing/Pricing"

export default function App() {
  return (
    <LanguageProvider>
      <BookingProvider>
        <main id="top">
          <Header />
          <Hero />
          <Marquee />
          <Audience />
          <Pricing />
          <About />
          <Contact />
          <Footer />
          <BookingModal />
        </main>
      </BookingProvider>
    </LanguageProvider>
  )
}
