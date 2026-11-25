import { Link } from "react-router";
import ArrowForwardRounded from "@mui/icons-material/ArrowForwardRounded";
import { PublicHeader, PublicFooter } from "~/components/public/public-shell";
import { IndustryGrid } from "~/components/public/industry-grid";
import { IndustryMotionShowcase } from "~/components/public/workflow-motion";
import p from "./landing.module.css";
import s from "./industries.module.css";

export function meta() {
  return [
    { title: "AI & automation for your industry — Creative Connects" },
    {
      name: "description",
      content:
        "Explore AI replies and automation for education, healthcare, events, IT services, recruitment, fintech, spas, restaurants, real estate, gyms and ecommerce.",
    },
    { tagName: "link", rel: "canonical", href: "https://creativeconnects.web.id/industries" },
  ];
}
export default function Industries() {
  return (
    <div className={`${p.page} ${s.page}`}>
      <a className={p.skipLink} href="#main">
        Skip to content
      </a>
      <PublicHeader />
      <main id="main">
        <section className={`${p.container} ${s.directoryHero} ${s.directoryMotionHero}`}>
          <div>
            <h1>
              Different businesses.
              <br />
              <em>A more helpful conversation.</em>
            </h1>
            <p>
              See how AI replies and automation can help your team answer everyday questions, guide enquiries and keep
              the next step moving.
            </p>
            <a href="#sectors" className={p.textButton}>
              Find your industry <ArrowForwardRounded />
            </a>
          </div>
          <IndustryMotionShowcase />
        </section>
        <section className={`${p.container} ${s.directorySection}`} id="sectors" aria-labelledby="sector-title">
          <div className={s.sectionIntro}>
            <h2 id="sector-title">Find your everyday use case.</h2>
            <p>Choose a sector to explore sample conversations and the features behind them.</p>
          </div>
          <IndustryGrid />
        </section>
        <section className={`${p.container} ${s.directoryClose}`}>
          <h2>
            One connected workspace.
            <br />
            Built around your conversations.
          </h2>
          <p>
            AI assistance, chat menus and human handoff work alongside contacts, campaigns and commerce. Your team
            configures the channels and workflows it needs.
          </p>
          <Link to="/login" className={p.primaryButton}>
            Sign in to your workspace <ArrowForwardRounded />
          </Link>
        </section>
      </main>
      <PublicFooter />
    </div>
  );
}
