import { Fragment } from "react"
import { useLanguage } from "@/context/LanguageContext"
import "./Marquee.scss"

export function Marquee() {
  const { t } = useLanguage()
  const items = [
    t("Akustisk", "Acoustic"),
    t("Elektrisk", "Electric"),
    t("Begynder", "Beginner"),
    t("Øvet", "Advanced"),
    t("Professionel", "Professional"),
    t("Børn & voksne", "Children & adults"),
  ]

  return (
    <div
      className="marquee"
      aria-label={t("Undervisning for alle niveauer", "Lessons for all levels")}
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
