import { studioImage } from "@/data/images"
import { useLanguage } from "@/context/LanguageContext"
import "./About.scss"

export function About() {
  const { t } = useLanguage()

  const points = [
    {
      number: "01",
      title: t("Fagligt stærkt", "Expert teaching"),
      text: t(
        "Højtuddannede musikere med solid undervisningserfaring.",
        "Highly educated musicians with extensive teaching experience.",
      ),
    },
    {
      number: "02",
      title: t("Nærværende", "Attentive"),
      text: t("Én elev, én underviser, fuldt fokus.", "One student, one teacher, complete focus."),
    },
    {
      number: "03",
      title: t("Lokalt", "Local"),
      text: t(
        "Tæt på Valby Station og byens hverdagsliv.",
        "Close to Valby Station and the rhythm of local life.",
      ),
    },
  ]

  return (
    <section className="about section" id="om">
      <div className="about-photo">
        <img
          src={studioImage}
          alt={t("Hyggeligt musiklokale med guitarer", "Welcoming music room with guitars")}
        />
        <div className="local-badge">
          {t("Siden", "Since")}
          <br />
          <strong>2018</strong>
          <br />
          {t("i Valby", "in Valby")}
        </div>
      </div>
      <div className="about-copy">
        <p className="eyebrow">
          {t("En lokalt forankret guitarskole", "A locally rooted guitar school")}
        </p>
        <h2>
          {t("Musik vokser bedst", "Music grows best")}
          <br />
          <em>{t("i trygge rammer", "in a safe space")}</em>
        </h2>
        <p className="about-lead">
          {t(
            "Du undervises af erfarne, højtuddannede musikere med stærke kunstneriske og pædagogiske kompetencer. Her er der både plads til spilleglæde og høje ambitioner.",
            "You will be taught by experienced, highly educated musicians with strong artistic and teaching skills. There is room for both the joy of playing and high ambitions.",
          )}
        </p>
        <div className="about-points">
          {points.map((point) => (
            <div key={point.number}>
              <span>{point.number}</span>
              <p>
                <strong>{point.title}</strong>
                {point.text}
              </p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
