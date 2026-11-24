import { Fragment } from "react"
import { useText } from "@/context/ContentContext"
import "./Marquee.scss"

type Phrase = { da: string; en: string }

export function Marquee({ items, label }: { items?: Phrase[]; label?: Phrase }) {
  const { content, tx } = useText()
  const phrases = (items ?? content.marquee.items).map((item) => tx(item))

  return (
    <div className="marquee" aria-label={tx(label ?? content.marquee.label)}>
      <div className="marquee-track">
        {[0, 1].map((group) => (
          <div className="marquee-group" aria-hidden={group === 1} key={group}>
            {phrases.map((item) => (
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
