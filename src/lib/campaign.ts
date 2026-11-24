import { useEffect, useState } from "react"

export function isCampaignPath(path: string) {
  const clean = path.split("?")[0].replace(/\/+$/, "") || "/"
  return clean === "/jul" || clean === "/christmas"
}

export function christmasIsCurrent(until: string, now = Date.now()) {
  if (!until.trim()) return false
  const end = new Date(`${until}T23:59:59`)
  return !Number.isNaN(end.getTime()) && now <= end.getTime()
}

export function daysUntil(until: string, now = new Date()) {
  const end = new Date(`${until}T00:00:00`)
  if (Number.isNaN(end.getTime())) return 0
  const start = new Date(now)
  start.setHours(0, 0, 0, 0)
  return Math.max(0, Math.round((end.getTime() - start.getTime()) / 86_400_000))
}

export function useChristmasLive(until: string) {
  const [live, setLive] = useState(true)
  const [days, setDays] = useState<number | null>(null)

  useEffect(() => {
    setLive(christmasIsCurrent(until))
    setDays(daysUntil(until))
  }, [until])

  return { live, days }
}
