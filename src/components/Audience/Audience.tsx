import { useLanguage } from "@/context/LanguageContext"
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
  const { t } = useLanguage()

  const groups = [
    {
      number: "01",
      icon: <ChordIcon />,
      title: t("Begyndere", "Beginners"),
      text: t(
        "Vi tager det helt fra bunden. Du lærer akkorder, rytme og dine første sange — uden unødigt teori-tungt stof.",
        "We start with the fundamentals. Learn chords, rhythm and your first songs — without unnecessary, theory-heavy material.",
      ),
    },
    {
      number: "02",
      icon: <BoltIcon />,
      title: t("Øvede", "Advanced players"),
      text: t(
        "Få nye værktøjer, bryd gamle vaner og få din teknik og musikalitet til at hænge bedre sammen.",
        "Gain new tools, break old habits and bring your technique and musicality together.",
      ),
    },
    {
      number: "03",
      icon: <WaveIcon />,
      title: t("Erfarne & professionelle", "Experienced & professional"),
      text: t(
        "Fordyb dig i avanceret teknik, improvisation, klang og musikalsk fortolkning med kvalificeret sparring.",
        "Explore advanced technique, improvisation, tone and musical interpretation with expert guidance.",
      ),
    },
    {
      number: "04",
      icon: <WaveIcon />,
      title: t("Børn & unge", "Children & young people"),
      text: t(
        "Legende undervisning med hurtige succesoplevelser, favoritnumre og plads til både nysgerrighed og ambitioner.",
        "Playful lessons with early wins, favourite songs and room for both curiosity and ambition.",
      ),
    },
  ]

  return (
    <section className="audience section" id="undervisning">
      <div className="section-heading">
        <p className="eyebrow">
          {t("Plads til alle slags guitarister", "Room for every kind of guitarist")}
        </p>
        <h2>
          {t("Undervisning, der", "Lessons made")}
          <br />
          <em>{t("passer til dig", "for you")}</em>
        </h2>
      </div>
      <div className="audience-grid">
        {groups.map((group) => (
          <article key={group.number}>
            <span className="number">{group.number}</span>
            {group.icon}
            <h3>{group.title}</h3>
            <p>{group.text}</p>
          </article>
        ))}
      </div>
      <p className="center-note">
        {t("Ingen standardplaner. Vi starter med den musik, ", "No standard plans. We begin with the music ")}
        <strong>{t("du", "you")}</strong>
        {t(" holder af.", " love.")}
      </p>
    </section>
  )
}
