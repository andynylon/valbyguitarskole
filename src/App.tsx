import { BookingProvider } from "@/context/BookingContext"
import { ContentProvider, DocumentMeta } from "@/context/ContentContext"
import { LanguageProvider } from "@/context/LanguageContext"
import { NoticeProvider } from "@/context/NoticeContext"
import { About } from "@/components/About/About"
import { Audience } from "@/components/Audience/Audience"
import { BookingModal } from "@/components/BookingModal/BookingModal"
import { Campaign } from "@/components/Campaign/Campaign"
import { ChristmasPage } from "@/components/Christmas/Christmas"
import { Contact } from "@/components/Contact/Contact"
import { Footer } from "@/components/Footer/Footer"
import { Header } from "@/components/Header/Header"
import { Hero } from "@/components/Hero/Hero"
import { Marquee } from "@/components/Marquee/Marquee"
import { Pricing } from "@/components/Pricing/Pricing"
import { Prompts } from "@/components/Prompts/Prompts"
import { Teachers } from "@/components/Teachers/Teachers"
import { isCampaignPath } from "@/lib/campaign"

function HomePage() {
  return (
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
  )
}

export default function App({ path }: { path?: string }) {
  const route = path ?? (typeof window !== "undefined" ? window.location.pathname : "/")
  const campaign = isCampaignPath(route)

  return (
    <LanguageProvider>
      <ContentProvider>
        <DocumentMeta campaign={campaign} />
        <BookingProvider>
          <NoticeProvider>{campaign ? <ChristmasPage /> : <HomePage />}</NoticeProvider>
        </BookingProvider>
      </ContentProvider>
    </LanguageProvider>
  )
}
