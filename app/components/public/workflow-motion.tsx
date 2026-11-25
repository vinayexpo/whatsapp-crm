import { useEffect, useRef, useState } from "react";
import ChatBubbleOutlineRounded from "@mui/icons-material/ChatBubbleOutlineRounded";
import AutoAwesomeRounded from "@mui/icons-material/AutoAwesomeRounded";
import SupportAgentRounded from "@mui/icons-material/SupportAgentRounded";
import PauseRounded from "@mui/icons-material/PauseRounded";
import PlayArrowRounded from "@mui/icons-material/PlayArrowRounded";
import ReplayRounded from "@mui/icons-material/ReplayRounded";
import { industries, type Industry, type IndustryScenario } from "~/data/industries";
import s from "~/routes/industries.module.css";

export function WorkflowMotion({
  industry,
  scenario = industry.scenarios[0],
  compact = false,
}: {
  industry: Industry;
  scenario?: IndustryScenario;
  compact?: boolean;
}) {
  const figure = useRef<HTMLElement>(null);
  const [paused, setPaused] = useState(false);
  const [reduced, setReduced] = useState(true);
  const [inView, setInView] = useState(true);
  const [visible, setVisible] = useState(true);
  const [stage, setStage] = useState(0);
  const [replay, setReplay] = useState(0);
  const running = !paused && !reduced && inView && visible;

  useEffect(() => {
    const media = window.matchMedia?.("(prefers-reduced-motion: reduce)");
    if (!media) return;
    const update = () => setReduced(media.matches);
    update();
    media.addEventListener("change", update);
    return () => media.removeEventListener("change", update);
  }, []);
  useEffect(() => {
    const update = () => setVisible(!document.hidden);
    update();
    document.addEventListener("visibilitychange", update);
    const observer =
      typeof IntersectionObserver === "undefined"
        ? null
        : new IntersectionObserver(([entry]) => setInView(entry.isIntersecting), { threshold: 0.1 });
    if (figure.current) observer?.observe(figure.current);
    return () => {
      document.removeEventListener("visibilitychange", update);
      observer?.disconnect();
    };
  }, []);
  useEffect(() => {
    setStage(0);
  }, [industry.slug, scenario.label, replay]);
  useEffect(() => {
    if (!running) return;
    const timer = setInterval(() => setStage((current) => (current + 1) % 3), 2400);
    return () => clearInterval(timer);
  }, [running, industry.slug, scenario.label, replay]);

  return (
    <figure
      ref={figure}
      className={`${s.workflowMotion} ${compact ? s.motionCompact : ""}`}
      data-running={running}
      data-reduced-motion={reduced}
      aria-label={`${industry.name} illustrative workflow`}
    >
      <div className={s.motionHeader}>
        <strong>{compact ? "An enquiry, connected." : industry.name}</strong>
        <div className={s.motionControls}>
          <button
            type="button"
            aria-label={
              reduced ? "Show next workflow step" : paused ? "Play workflow animation" : "Pause workflow animation"
            }
            onClick={() => (reduced ? setStage((current) => (current + 1) % 3) : setPaused(!paused))}
            title={reduced ? "Next step" : paused ? "Play animation" : "Pause animation"}
          >
            {reduced ? <PlayArrowRounded /> : paused ? <PlayArrowRounded /> : <PauseRounded />}
          </button>
          <button
            type="button"
            aria-label="Replay workflow preview"
            title="Replay preview"
            onClick={() => {
              setReplay((current) => current + 1);
              setPaused(false);
            }}
          >
            <ReplayRounded />
          </button>
        </div>
      </div>
      {!compact && <p className={s.motionQuestion}>{scenario.question}</p>}
      <svg
        key={`${industry.slug}-${scenario.label}-${replay}`}
        viewBox="0 0 400 110"
        className={s.motionSvg}
        aria-hidden="true"
      >
        <path className={s.motionConnection} d="M112 46H148M252 46H288" />
        <circle className={s.packetFirst} cx="112" cy="46" r="4" />
        <circle className={s.packetSecond} cx="252" cy="46" r="4" />
        <g data-active={stage === 0}>
          <rect x="8" y="8" width="104" height="80" rx="10" />
          <ChatBubbleOutlineRounded x="48" y="22" width="24" height="24" />
          <text x="60" y="70" textAnchor="middle">
            Enquiry
          </text>
        </g>
        <g data-active={stage === 1}>
          <rect x="148" y="8" width="104" height="80" rx="10" />
          <AutoAwesomeRounded x="188" y="22" width="24" height="24" />
          <text x="200" y="70" textAnchor="middle">
            AI assistant
          </text>
        </g>
        <g data-active={stage === 2}>
          <rect x="288" y="8" width="104" height="80" rx="10" />
          <SupportAgentRounded x="328" y="22" width="24" height="24" />
          <text x="340" y="70" textAnchor="middle">
            Your team
          </text>
        </g>
      </svg>
      <figcaption className={s.motionCaption}>
        <div aria-hidden="true" className={s.motionSteps}>
          {scenario.steps.map((step, index) => (
            <span key={step} data-active={stage === index} />
          ))}
        </div>
        <span>{scenario.steps[stage]}</span>
        <small>Illustrative workflow · {reduced ? "Step-through preview" : paused ? "Paused" : "Motion preview"}</small>
      </figcaption>
    </figure>
  );
}

export function IndustryMotionShowcase() {
  const [slug, setSlug] = useState(industries[0].slug);
  const industry = industries.find((item) => item.slug === slug)!;
  return (
    <div className={s.motionShowcase}>
      <label htmlFor="industry-preview">Preview an industry</label>
      <select id="industry-preview" value={slug} onChange={(event) => setSlug(event.target.value)}>
        {industries.map((item) => (
          <option key={item.slug} value={item.slug}>
            {item.name}
          </option>
        ))}
      </select>
      <WorkflowMotion industry={industry} />
    </div>
  );
}
