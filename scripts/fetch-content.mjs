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
    publish(JSON.stringify(data, null, 2) + "\n")
    console.log(`Fetched content updated at ${data.updated}`)
  } catch (error) {
    if (required) throw error
    console.warn(`${error.message}. Using the content saved in the repository.`)
    publish(fs.readFileSync(contentPath, "utf8"))
  }
}
