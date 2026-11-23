import { createContext, useContext, useMemo, useState, type ReactNode } from "react"

type NoticeContextValue = {
  campaignOpen: boolean
  setCampaignOpen: (open: boolean) => void
  campaignSettled: boolean
  setCampaignSettled: (settled: boolean) => void
}

const NoticeContext = createContext<NoticeContextValue | null>(null)

export function NoticeProvider({ children }: { children: ReactNode }) {
  const [campaignOpen, setCampaignOpen] = useState(false)
  const [campaignSettled, setCampaignSettled] = useState(false)
  const value = useMemo(
    () => ({ campaignOpen, setCampaignOpen, campaignSettled, setCampaignSettled }),
    [campaignOpen, campaignSettled],
  )

  return <NoticeContext.Provider value={value}>{children}</NoticeContext.Provider>
}

export function useNotices() {
  const context = useContext(NoticeContext)
  if (!context) throw new Error("useNotices must be used within NoticeProvider")
  return context
}
