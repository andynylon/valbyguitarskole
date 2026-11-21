import { BookingProvider } from "@/context/BookingContext"
import { ContentProvider, DocumentMeta } from "@/context/ContentContext"
import { LanguageProvider } from "@/context/LanguageContext"
import { NoticeProvider } from "@/context/NoticeContext"
import { About } from "@/components/About/About"
import { Audience } from "@/components/Audience/Audience"
import { BookingModal } from "@/components/BookingModal/BookingModal"
import { Campaign } from "@/components/Campaign/Campaign"
import { Contact } from "@/components/Contact/Contact"
import { Footer } from "@/components/Footer/Footer"
import { Header } from "@/components/Header/Header"
import { Hero } from "@/components/Hero/Hero"
import { Marquee } from "@/components/Marquee/Marquee"
import { Pricing } from "@/components/Pricing/Pricing"
import { Prompts } from "@/components/Prompts/Prompts"
import { Teachers } from "@/components/Teachers/Teachers"

export default function App() {
  return (
    <LanguageProvider>
      <ContentProvider>
      <DocumentMeta />
      <BookingProvider>
        <NoticeProvider>
          <main id="top">
            <Header />
            <Hero />
            <Marquee />
            <Audience />
            <Pricing />
            <Teachers />
            <About />
            <Contact />
            <Footer />
            <BookingModal />
            <Campaign />
            <Prompts />
          </main>
        </NoticeProvider>
      </BookingProvider>
      </ContentProvider>
    </LanguageProvider>
  )
}
