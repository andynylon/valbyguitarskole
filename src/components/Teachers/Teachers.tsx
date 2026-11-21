import { useEffect, useRef, useState } from "react"
import { Arrow } from "@/components/icons/Arrow"
import { useText } from "@/context/ContentContext"
import { useLanguage } from "@/context/LanguageContext"
import "./Teachers.scss"

type Teacher = ReturnType<typeof useText>["content"]["teachers"]["people"][number]

export function Teachers() {
  const { content, tx } = useText()
  const { t } = useLanguage()
  const teachers = content.teachers
  const scrollerRef = useRef<HTMLDivElement>(null)
  const [atStart, setAtStart] = useState(true)
  const [atEnd, setAtEnd] = useState(false)

  useEffect(() => {
    const scroller = scrollerRef.current
    if (!scroller) return

    const update = () => {
      setAtStart(scroller.scrollLeft <= 2)
      setAtEnd(scroller.scrollLeft + scroller.clientWidth >= scroller.scrollWidth - 2)
    }

    update()
    scroller.addEventListener("scroll", update, { passive: true })
    window.addEventListener("resize", update)
    return () => {
      scroller.removeEventListener("scroll", update)
      window.removeEventListener("resize", update)
    }
  }, [teachers.people.length])

  function scrollByCard(direction: -1 | 1) {
    const scroller = scrollerRef.current
    const card = scroller?.querySelector("article")
    if (!scroller || !card) return
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches
    const gap = 24
    scroller.scrollBy({
      left: direction * (card.getBoundingClientRect().width + gap),
      behavior: reduceMotion ? "auto" : "smooth",
    })
  }

  return (
    <section className="teachers section" id="undervisere">
      <div className="section-heading">
        <p className="eyebrow">{tx(teachers.eyebrow)}</p>
        <h2>
          {tx(teachers.titleLine1)}
          <br />
          <em>{tx(teachers.titleLine2)}</em>
        </h2>
        <p className="teachers-intro">{tx(teachers.intro)}</p>
      </div>
      <div className="teachers-nav">
        <button
          type="button"
          className="is-prev"
          aria-label={t("Forrige underviser", "Previous teacher")}
          onClick={() => scrollByCard(-1)}
          disabled={atStart}
        >
          <Arrow />
        </button>
        <button
          type="button"
          aria-label={t("Næste underviser", "Next teacher")}
          onClick={() => scrollByCard(1)}
          disabled={atEnd}
        >
          <Arrow />
        </button>
      </div>
      <div
        className="teachers-track"
        ref={scrollerRef}
        tabIndex={0}
        aria-label={t("Undervisere", "Teachers")}
      >
        {teachers.people.map((person, index) => (
          <article key={person.name}>
            <TeacherPortrait person={person} index={index} />
            <p className="teacher-instrument">{tx(person.instrument)}</p>
            <h3>{person.name}</h3>
            <p className="teacher-text">{tx(person.text)}</p>
            <dl>
              <div>
                <dt>{tx(teachers.educationLabel)}</dt>
                <dd>{tx(person.education)}</dd>
              </div>
              <div>
                <dt>{tx(teachers.teachingLabel)}</dt>
                <dd>{tx(person.teaching)}</dd>
              </div>
              <div>
                <dt>{tx(teachers.liveLabel)}</dt>
                <dd>{tx(person.artists)}</dd>
              </div>
            </dl>
          </article>
        ))}
      </div>
      <p className="teachers-note">{tx(teachers.note)}</p>
    </section>
  )
}

function TeacherPortrait({ person, index }: { person: Teacher; index: number }) {
  const { t } = useLanguage()
  const videoRef = useRef<HTMLVideoElement>(null)
  const [withSound, setWithSound] = useState(false)
  const video = person.video.trim()
  const image = person.image.trim()

  useEffect(() => {
    const element = videoRef.current
    if (!element) return
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      element.pause()
      return
    }
    element.muted = true
    void element.play().catch(() => {})
  }, [video])

  function playWithSound() {
    const element = videoRef.current
    if (!element) return
    element.loop = false
    element.muted = false
    element.currentTime = 0
    void element.play()
    setWithSound(true)
  }

  return (
    <div className="teacher-photo">
      {video ? (
        <video
          ref={videoRef}
          src={video}
          poster={image || undefined}
          muted
          loop
          playsInline
          autoPlay
          controls={withSound}
          preload="metadata"
          aria-label={person.name}
        />
      ) : image ? (
        <img src={image} alt={person.name} />
      ) : (
        <span aria-hidden="true">{String(index + 1).padStart(2, "0")}</span>
      )}
      {video && !withSound && (
        <button className="teacher-sound" type="button" onClick={playWithSound}>
          <span aria-hidden="true">▶</span>
          {t("Afspil med lyd", "Play with sound")}
        </button>
      )}
    </div>
  )
}
