import { useEffect, useRef, useState } from "react";
import ChatBubbleOutlineRounded from "@mui/icons-material/ChatBubbleOutlineRounded";
import AutoAwesomeRounded from "@mui/icons-material/AutoAwesomeRounded";
import SupportAgentRounded from "@mui/icons-material/SupportAgentRounded";
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
  return (
    <WorkflowGraphic
      key={`${industry.slug}-${scenario.label}`}
      label={`${industry.name} illustrative workflow`}
      title={compact ? "An enquiry, connected." : industry.name}
      question={compact ? undefined : scenario.question}
      steps={scenario.steps}
      compact={compact}
    />
  );
}

export function WorkflowGraphic({
  label,
  title,
  question,
  steps,
  compact = false,
}: {
  label: string;
  title: string;
  question?: string;
  steps: readonly [string, string, string];
  compact?: boolean;
}) {
  const figure = useRef<HTMLElement>(null);
  const [reduced, setReduced] = useState(true);
  const [inView, setInView] = useState(false);
  const [visible, setVisible] = useState(true);
  const [phase, setPhase] = useState(0);
  const stage = reduced ? 2 : phase;
  const running = !reduced && inView && visible;

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
    if (!observer) setInView(true);
    if (figure.current) observer?.observe(figure.current);
    return () => {
      document.removeEventListener("visibilitychange", update);
      observer?.disconnect();
    };
  }, []);
  useEffect(() => {
    if (!running) return;
    const timer = setInterval(() => setPhase((current) => (current + 1) % 3), 1400);
    return () => clearInterval(timer);
  }, [running]);

  return (
    <figure
      ref={figure}
      className={`${s.workflowMotion} ${compact ? s.motionCompact : ""}`}
      data-running={running}
      data-reduced-motion={reduced}
      aria-label={label}
    >
      <div className={s.motionHeader}>
        <strong>{title}</strong>
      </div>
      {question && <p className={s.motionQuestion}>{question}</p>}
      <svg
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
          {steps.map((step, index) => (
            <span key={step} data-active={stage === index} />
          ))}
        </div>
        <span>{steps[stage]}</span>
        <small>Illustrative workflow</small>
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
