import { useText } from "@/context/ContentContext"
import { Logo } from "@/components/Logo/Logo"
import "./Footer.scss"

export function Footer() {
  const { content, tx } = useText()
  const footer = content.footer
  const phoneHref = `tel:${footer.phone.replace(/[^\d+]/g, "")}`

  return (
    <footer>
      <div className="footer-brand">
        <Logo onDark />
        <p>
          {tx(footer.tagline1)}
          <br />
          {tx(footer.tagline2)}
        </p>
      </div>
      <div>
        <p className="footer-label">{tx(footer.findUs)}</p>
        <p>
          {footer.address1}
          <br />
          {footer.address2}
        </p>
      </div>
      <div>
        <p className="footer-label">{tx(footer.contact)}</p>
        <a href={`mailto:${footer.email}`}>{footer.email}</a>
        <a href={phoneHref}>{footer.phone}</a>
      </div>
      <div>
        <p className="footer-label">{tx(footer.follow)}</p>
        <a href="#instagram">Instagram</a>
        <a href="#facebook">Facebook</a>
      </div>
      <p className="copyright">© 2025 Valby Guitarskole</p>
    </footer>
  )
}
