import fs from "node:fs"
import path from "node:path"

const root = process.cwd()
const contentPath = path.join(root, "src/data/content.json")
const defaultPath = path.join(root, "public/api/default-content.json")
const url = process.env.CONTENT_URL
const required = process.env.GITHUB_EVENT_NAME === "repository_dispatch"

function isContent(value) {
  return (
    value &&
    typeof value.updated === "string" &&
    typeof value.hero?.titleLine1?.da === "string" &&
    Array.isArray(value.pricing?.offers) &&
    value.pricing.offers.length > 0
  )
}

function offerTone(item) {
  if (!item || typeof item !== "object" || Array.isArray(item)) return null
  return typeof item.tone === "string" && item.tone !== "" ? item.tone : null
}

function isOfferList(current, fallback) {
  return (
    fallback.length > 0 &&
    fallback.every((item) => offerTone(item) !== null) &&
    current.every((item) => offerTone(item) !== null)
  )
}

function fillOfferList(current, fallback) {
  const byTone = new Map()
  for (const item of current) {
    const tone = offerTone(item)
    if (tone) byTone.set(tone, item)
  }

  const used = new Set()
  const merged = []
  for (const fallbackItem of fallback) {
    const tone = offerTone(fallbackItem)
    if (!tone) continue
    used.add(tone)
    const existing = byTone.get(tone)
    merged.push(existing ? fillMissing(existing, fallbackItem) : fallbackItem)
  }
  for (const item of current) {
    const tone = offerTone(item)
    if (tone && !used.has(tone)) merged.push(item)
  }
  return merged
}

function fillMissing(current, fallback) {
  if (Array.isArray(current) && Array.isArray(fallback)) {
    if (isOfferList(current, fallback)) return fillOfferList(current, fallback)
    return current.map((item, index) => {
      const fallbackItem = fallback[index]
      if (
        item &&
        fallbackItem &&
        typeof item === "object" &&
        typeof fallbackItem === "object" &&
        !Array.isArray(item) &&
        !Array.isArray(fallbackItem)
      ) {
        return fillMissing(item, fallbackItem)
      }
      return item
    })
  }
  if (!fallback || typeof fallback !== "object" || Array.isArray(fallback)) return current
  if (!current || typeof current !== "object" || Array.isArray(current)) return current
  const next = { ...current }
  for (const [key, value] of Object.entries(fallback)) {
    if (!(key in next)) next[key] = value
    else next[key] = fillMissing(next[key], value)
  }
  return next
}

function publish(json) {
  fs.writeFileSync(contentPath, json)
  fs.mkdirSync(path.dirname(defaultPath), { recursive: true })
  fs.writeFileSync(defaultPath, json)
}

if (!url) {
  if (required) throw new Error("CONTENT_URL is required for a CMS rebuild")
  publish(fs.readFileSync(contentPath, "utf8"))
  console.log("No CONTENT_URL set. Using the content saved in the repository.")
} else {
  try {
    const response = await fetch(url, { headers: { Accept: "application/json" } })
    if (!response.ok) throw new Error(`Content request failed (${response.status})`)
    const data = await response.json()
    if (!isContent(data)) throw new Error("Content response did not match the page")
    const bundled = JSON.parse(fs.readFileSync(contentPath, "utf8"))
    const merged = fillMissing(data, bundled)
    publish(JSON.stringify(merged, null, 2) + "\n")
    console.log(`Fetched content updated at ${data.updated}`)
  } catch (error) {
    if (required) throw error
    console.warn(`${error.message}. Using the content saved in the repository.`)
    publish(fs.readFileSync(contentPath, "utf8"))
  }
}
