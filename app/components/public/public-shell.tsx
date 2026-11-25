import { useState } from "react";
import { Link, useLocation } from "react-router";
import WhatsApp from "@mui/icons-material/WhatsApp";
import ArrowOutwardRounded from "@mui/icons-material/ArrowOutwardRounded";
import MenuRounded from "@mui/icons-material/MenuRounded";
import CloseRounded from "@mui/icons-material/CloseRounded";
import s from "~/routes/landing.module.css";

export function Brand() {
  return (
    <span className={s.brand}>
      <span className={s.brandMark}>
        <WhatsApp />
      </span>
      <span>
        creative<span className={s.brandLight}>connects</span>
        <span className={s.brandDot}>.</span>
      </span>
    </span>
  );
}

export function PublicHeader() {
  const [menuOpen, setMenuOpen] = useState(false);
  const { pathname } = useLocation();
  return (
    <header
      className={s.header}
      onKeyDown={(e) => {
        if (e.key === "Escape" && menuOpen) {
          setMenuOpen(false);
          document.getElementById("landing-menu-toggle")?.focus();
        }
      }}
    >
      <div className={s.navWrap}>
        <Link to="/" aria-label="Creative Connects home" onClick={() => setMenuOpen(false)}>
          <Brand />
        </Link>
        <nav aria-label="Main navigation" className={`${s.nav} ${menuOpen ? s.navOpen : ""}`} id="landing-navigation">
          {[
            ["Product", "/#product"],
            ["Automation", "/#automation"],
            ["Industries", "/industries"],
            ["Platform", "/#platform"],
            ["FAQs", "/#faqs"],
          ].map(([label, url]) => (
            <Link
              key={url}
              to={url}
              aria-current={url === "/industries" && pathname.startsWith(url) ? "page" : undefined}
              onClick={() => setMenuOpen(false)}
            >
              {label}
            </Link>
          ))}
        </nav>
        <Link className={s.navCta} to="/login">
          Sign in <ArrowOutwardRounded />
        </Link>
        <button
          className={s.menuToggle}
          id="landing-menu-toggle"
          aria-label={menuOpen ? "Close navigation" : "Open navigation"}
          aria-expanded={menuOpen}
          aria-controls="landing-navigation"
          onClick={() => setMenuOpen(!menuOpen)}
        >
          {menuOpen ? <CloseRounded /> : <MenuRounded />}
        </button>
      </div>
    </header>
  );
}

export function PublicFooter() {
  return (
    <footer className={`${s.footer} ${s.container}`}>
      <div>
        <Link to="/" aria-label="Creative Connects home">
          <Brand />
        </Link>
        <p>
          Conversations, AI and automation.
          <br />
          Working together.
        </p>
      </div>
      <nav aria-label="Footer navigation">
        <Link to="/#product">Product</Link>
        <Link to="/#automation">Automation</Link>
        <Link to="/industries">Industries</Link>
        <Link to="/privacy-policy">Privacy policy</Link>
        <Link to="/terms">Terms of service</Link>
      </nav>
      <span>© {new Date().getFullYear()} Creative Connects</span>
    </footer>
  );
}
