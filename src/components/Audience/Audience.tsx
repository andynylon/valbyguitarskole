import { useText } from "@/context/ContentContext"
import "./Audience.scss"

function ChordIcon() {
  return (
    <div className="line-icon chord-icon">
      <i />
      <i />
      <i />
      <i />
    </div>
  )
}

function BoltIcon() {
  return <div className="line-icon bolt-icon">ϟ</div>
}

function WaveIcon() {
  return <div className="line-icon wave-icon">≈</div>
}

export function Audience() {
  const { content, tx } = useText()
  const icons = [<ChordIcon />, <BoltIcon />, <WaveIcon />, <WaveIcon />]
  const audience = content.audience

  return (
    <section className="audience section" id="undervisning">
      <div className="section-heading">
        <p className="eyebrow">{tx(audience.eyebrow)}</p>
        <h2>
          {tx(audience.titleLine1)}
          <br />
          <em>{tx(audience.titleLine2)}</em>
        </h2>
      </div>
      <div className="audience-grid">
        {audience.groups.map((group, index) => (
          <article key={group.title.da}>
            <span className="number">{String(index + 1).padStart(2, "0")}</span>
            {icons[index]}
            <h3>{tx(group.title)}</h3>
            <p>{tx(group.text)}</p>
          </article>
        ))}
      </div>
      <p className="center-note">
        {tx(audience.noteBefore)}
        <strong>{tx(audience.noteStrong)}</strong>
        {tx(audience.noteAfter)}
      </p>
    </section>
  )
}
