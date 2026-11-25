import { useState, type KeyboardEvent } from "react";
import { Link, Navigate, useLocation, useParams } from "react-router";
import ArrowForwardRounded from "@mui/icons-material/ArrowForwardRounded";
import ArrowBackRounded from "@mui/icons-material/ArrowBackRounded";
import AutoAwesomeRounded from "@mui/icons-material/AutoAwesomeRounded";
import WhatsApp from "@mui/icons-material/WhatsApp";
import CheckRounded from "@mui/icons-material/CheckRounded";
import AddRounded from "@mui/icons-material/AddRounded";
import { PublicHeader, PublicFooter } from "~/components/public/public-shell";
import { IndustryGrid } from "~/components/public/industry-grid";
import { WorkflowMotion } from "~/components/public/workflow-motion";
import { findIndustry, industries, type Industry } from "~/data/industries";
import type { Route } from "./+types/industry";
import p from "./landing.module.css";
import s from "./industries.module.css";

export function meta({ params }: Route.MetaArgs) {
  const industry = findIndustry(params.sector);
  if (!industry) return [{ title: "Industry not found — Creative Connects" }, { name: "robots", content: "noindex" }];
  return [
    { title: `AI & automation for ${industry.name.toLowerCase()} — Creative Connects` },
    { name: "description", content: industry.description },
    { property: "og:title", content: industry.headline },
    { property: "og:description", content: industry.description },
    { tagName: "link", rel: "canonical", href: `https://creativeconnects.web.id/industries/${industry.slug}` },
  ];
}

function SectorPage({ industry }: { industry: Industry }) {
  const [active, setActive] = useState(0);
  const scenario = industry.scenarios[active];
  function moveTab(event: KeyboardEvent<HTMLButtonElement>, index: number) {
    const next =
      event.key === "ArrowRight"
        ? (index + 1) % 3
        : event.key === "ArrowLeft"
          ? (index + 2) % 3
          : event.key === "Home"
            ? 0
            : event.key === "End"
              ? 2
              : null;
    if (next === null) return;
    event.preventDefault();
    setActive(next);
    document.getElementById(`sector-tab-${next}`)?.focus();
  }
  return (
    <>
      <section className={`${p.container} ${s.sectorHero}`} aria-labelledby="sector-title">
        <div className={s.heroCopy}>
          <nav aria-label="Breadcrumb" className={s.breadcrumb}>
            <Link to="/industries">Industries</Link>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{industry.name}</span>
          </nav>
          <h1 id="sector-title">{industry.headline}</h1>
          <p>{industry.description}</p>
          <div className={s.heroActions}>
            <Link to="/login" className={p.primaryButton}>
              Sign in <ArrowForwardRounded />
            </Link>
            <a href="#use-cases" className={p.textButton}>
              Explore use cases
            </a>
          </div>
          <span className={s.audience}>{industry.audience}</span>
        </div>
        <div className={s.demo} aria-label={`${industry.name} interactive conversation demo`}>
          <div className={s.demoBar}>
            <span>
              <AutoAwesomeRounded /> A conversation in action
            </span>
            <span>Sample demo</span>
          </div>
          <div role="tablist" aria-label="Choose a sample conversation" className={s.scenarioTabs}>
            {industry.scenarios.map((item, index) => (
              <button
                role="tab"
                id={`sector-tab-${index}`}
                aria-controls="sector-panel"
                aria-selected={index === active}
                tabIndex={index === active ? 0 : -1}
                key={item.label}
                onClick={() => setActive(index)}
                onKeyDown={(e) => moveTab(e, index)}
              >
                {item.label}
              </button>
            ))}
          </div>
          <div id="sector-panel" role="tabpanel" aria-labelledby={`sector-tab-${active}`} tabIndex={0}>
            <div className={s.conversationHeader}>
              <WhatsApp />
              <div>
                <strong>{industry.contact}</strong>
                <span>Illustrative WhatsApp conversation</span>
              </div>
            </div>
            <div className={s.messages} aria-live="polite" aria-atomic="true">
              <div className={s.question}>
                {scenario.question}
                <span>Customer</span>
              </div>
              <div key={active} className={`${s.answer} ${s.messageTransition}`}>
                <span>
                  <AutoAwesomeRounded /> AI assistant
                </span>
                {scenario.answer}
              </div>
            </div>
            <WorkflowMotion industry={industry} scenario={scenario} compact />
            <div className={s.demoFlow}>
              <span>EXAMPLE WORKFLOW</span>
              <ol>
                {scenario.steps.map((step) => (
                  <li key={step}>
                    <CheckRounded />
                    <span>{step}</span>
                  </li>
                ))}
              </ol>
            </div>
          </div>
          <p className={s.demoNote}>Interactive examples. No messages sent or appointments booked.</p>
        </div>
      </section>
      <section className={`${p.container} ${s.useCases}`} id="use-cases" aria-labelledby="use-case-title">
        <div className={s.sectionIntro}>
          <h2 id="use-case-title">Put AI to work for your team.</h2>
          <p>Start with the conversations you already have.</p>
        </div>
        <div className={s.useCaseList}>
          {industry.scenarios.map((item, index) => (
            <article key={item.label}>
              <span className={s.caseNumber}>0{index + 1}</span>
              <div>
                <h3>{item.label}</h3>
                <p>{item.detail}</p>
                <span className={s.featureTag}>{item.feature}</span>
              </div>
              <ArrowForwardRounded aria-hidden="true" />
            </article>
          ))}
        </div>
      </section>
      <section className={s.setupBand} aria-labelledby="setup-title">
        <div className={`${p.container} ${s.setupGrid}`}>
          <div>
            <h2 id="setup-title">
              Your information.
              <br />
              Your team. Your flow.
            </h2>
            <p>Connect your business channels and configure the assistant around your operation.</p>
            <Link to="/#automation" className={p.textButton}>
              See the automation tools <ArrowForwardRounded />
            </Link>
          </div>
          <ol>
            {industry.setup.map((step, index) => (
              <li key={step}>
                <span>0{index + 1}</span>
                {step}
              </li>
            ))}
          </ol>
        </div>
      </section>
      <section className={`${p.container} ${s.sectorFaq}`} aria-labelledby="sector-faq-title">
        <h2 id="sector-faq-title">Before you get started.</h2>
        <div className={p.faqList}>
          <details>
            <summary>
              {industry.faq[0]}
              <AddRounded />
            </summary>
            <p>{industry.faq[1]}</p>
          </details>
          <details>
            <summary>
              What needs to be configured?
              <AddRounded />
            </summary>
            <p>
              {industry.boundary} WhatsApp features require the relevant connected business account, permissions and
              channel setup.
            </p>
          </details>
          <details>
            <summary>
              Can our team take over from AI?
              <AddRounded />
            </summary>
            <p>
              Yes. Your {industry.team.toLowerCase()} can use the shared inbox to continue conversations with the
              message history and contact context. Campaign audiences and sends must meet the connected channel's
              consent and template requirements.
            </p>
          </details>
        </div>
      </section>
      <section className={`${p.container} ${s.related}`} aria-labelledby="related-title">
        <div className={s.sectionIntro}>
          <h2 id="related-title">More ways to connect.</h2>
          <Link to="/industries" className={p.textButton}>
            All industries <ArrowForwardRounded />
          </Link>
        </div>
        <IndustryGrid items={industries.filter((i) => i.slug !== industry.slug).slice(0, 3)} />
      </section>
      <section className={`${p.container} ${p.finalSection}`}>
        <div className={p.finalCta}>
          <div>
            <h2>
              Make your next conversation
              <br />a more useful one.
            </h2>
            <p>Bring AI, automation and your team together.</p>
          </div>
          <Link to="/login" className={p.lightButton}>
            Sign in to Creative Connects <ArrowForwardRounded />
          </Link>
        </div>
      </section>
    </>
  );
}

export default function IndustryPage() {
  const { sector } = useParams();
  const { hash } = useLocation();
  const industry = findIndustry(sector);
  if (industry && sector !== industry.slug) return <Navigate replace to={`/industries/${industry.slug}${hash}`} />;
  return (
    <div className={`${p.page} ${s.page}`}>
      <a className={p.skipLink} href="#main">
        Skip to content
      </a>
      <PublicHeader />
      <main id="main">
        {industry ? (
          <SectorPage key={industry.slug} industry={industry} />
        ) : (
          <section className={`${p.container} ${s.directoryHero}`}>
            <h1>Find the right conversation for your business.</h1>
            <p>Explore the industries we have examples for.</p>
            <Link to="/industries" className={p.primaryButton}>
              <ArrowBackRounded /> Browse industries
            </Link>
          </section>
        )}
      </main>
      <PublicFooter />
    </div>
  );
}
