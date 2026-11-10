import { studioImage } from "@/data/images"
import { useText } from "@/context/ContentContext"
import "./About.scss"

export function About() {
  const { content, tx } = useText()
  const about = content.about

  return (
    <section className="about section" id="om">
      <div className="about-photo">
        <img
          src={studioImage}
          alt={tx(about.imageAlt)}
        />
        <div className="local-badge">
          {tx(about.since)}
          <br />
          <strong>{about.year}</strong>
          <br />
          {tx(about.place)}
        </div>
      </div>
      <div className="about-copy">
        <p className="eyebrow">{tx(about.eyebrow)}</p>
        <h2>
          {tx(about.titleLine1)}
          <br />
          <em>{tx(about.titleLine2)}</em>
        </h2>
        <p className="about-lead">{tx(about.lead)}</p>
        <div className="about-points">
          {about.points.map((point, index) => (
            <div key={point.title.da}>
              <span>{String(index + 1).padStart(2, "0")}</span>
              <p>
                <strong>{tx(point.title)}</strong>
                {tx(point.text)}
              </p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
