import { Fragment } from "react"
import { useText } from "@/context/ContentContext"
import "./Marquee.scss"

export function Marquee() {
  const { content, tx } = useText()
  const items = content.marquee.items.map((item) => tx(item))

  return (
    <div
      className="marquee"
      aria-label={tx(content.marquee.label)}
    >
      <div className="marquee-track">
        {[0, 1].map((group) => (
          <div className="marquee-group" aria-hidden={group === 1} key={group}>
            {items.map((item) => (
              <Fragment key={item}>
                <span>{item}</span>
                <i>✦</i>
              </Fragment>
            ))}
          </div>
        ))}
      </div>
    </div>
  )
}
