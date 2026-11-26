import s from "./brand-logo.module.css";

export function BrandLogo({ className = "", compact = false }: { className?: string; compact?: boolean }) {
  return (
    <span className={`${s.logo} ${compact ? s.compact : ""} ${className}`} role="img" aria-label="Creative Connects">
      <img
        src={compact ? "/favicon.svg?v=cc-20261126" : "/brand/creative-connects-icon.svg"}
        width={compact ? 192 : 320}
        height={compact ? 192 : 170}
        alt=""
        aria-hidden="true"
      />
      <span className={s.wordmark} aria-hidden="true">Creative<span>Connects</span></span>
    </span>
  );
}
