import fs from "node:fs"
import path from "node:path"

const root = process.cwd()
const templatePath = path.join(root, "dist/index.html")
const serverEntry = path.join(root, "dist/server/entry-server.js")
const content = JSON.parse(fs.readFileSync(path.join(root, "src/data/content.json"), "utf8"))

const { render } = await import(serverEntry)
const shell = fs.readFileSync(templatePath, "utf8")
const marker = '<div id="root"></div>'
if (!shell.includes(marker)) {
  throw new Error("Could not find the root element in dist/index.html")
}

const homeHtml = render("/")
if (!homeHtml.includes('id="top"')) {
  throw new Error("Prerender did not include the page")
}
fs.writeFileSync(templatePath, apply(shell, homeHtml, content.meta.title.da, content.meta.description.da))

const christmasHtml = render("/jul")
if (!christmasHtml.includes("Giv musikken")) {
  throw new Error("Prerender did not include the Christmas page")
}
const christmasDir = path.join(root, "dist/jul")
fs.mkdirSync(christmasDir, { recursive: true })
fs.writeFileSync(
  path.join(christmasDir, "index.html"),
  apply(shell, christmasHtml, content.christmas.metaTitle.da, content.christmas.metaDescription.da),
)

function apply(template, appHtml, title, description) {
  let html = template.replace(marker, `<div id="root">${appHtml}</div>`)
  html = replaceTag(html, "title", title)
  html = replaceMeta(html, "name", "description", description)
  html = replaceMeta(html, "property", "og:title", title)
  html = replaceMeta(html, "property", "og:description", description)
  return replaceJsonLd(html, content)
}

function escapeText(value) {
  return value.replace(/&/g, "&amp;").replace(/</g, "&lt;")
}

function escapeAttr(value) {
  return escapeText(value).replace(/"/g, "&quot;")
}

function replaceTag(html, tag, value) {
  return html.replace(new RegExp(`<${tag}>[\\s\\S]*?</${tag}>`), `<${tag}>${escapeText(value)}</${tag}>`)
}

function replaceMeta(html, attribute, key, value) {
  const pattern = new RegExp(`(<meta ${attribute}="${key}" content=")[^"]*(")`)
  return html.replace(pattern, `$1${escapeAttr(value)}$2`)
}

function replaceJsonLd(html, site) {
  const address = String(site.footer.address2)
  const postal = address.match(/^(\d{4})\s+(.+)$/)
  return html.replace(/<script type="application\/ld\+json">[\s\S]*?<\/script>/, () => {
    const data = {
      "@context": "https://schema.org",
      "@type": "MusicSchool",
      name: "Valby Guitarskole",
      url: "https://valbyguitarskole.dk/",
      email: site.footer.email,
      telephone: String(site.footer.phone).replace(/[^\d+]/g, ""),
      address: {
        "@type": "PostalAddress",
        streetAddress: site.footer.address1,
        postalCode: postal?.[1] ?? "",
        addressLocality: postal?.[2] ?? address,
        addressCountry: "DK",
      },
      areaServed: "Valby",
    }
    return `<script type="application/ld+json">${JSON.stringify(data)}</script>`
  })
}
fs.rmSync(path.join(root, "dist/server"), { recursive: true, force: true })
