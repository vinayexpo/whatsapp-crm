import { Link } from "react-router";
import ArrowForwardRounded from "@mui/icons-material/ArrowForwardRounded";
import { industries, type Industry } from "~/data/industries";
import s from "~/routes/industries.module.css";

export function IndustryGrid({ items = industries }: { items?: Industry[] }) {
  return (
    <div className={s.industryGrid}>
      {items.map((industry) => (
        <Link to={`/industries/${industry.slug}`} key={industry.slug} className={s.industryLink}>
          <span className={s.cardIndex} aria-hidden="true">
            0{industries.indexOf(industry) + 1}
          </span>
          <div>
            <h3>{industry.name}</h3>
            <p>{industry.summary}</p>
            <span className={s.explore}>
              Explore {industry.name.toLowerCase()} <ArrowForwardRounded />
            </span>
          </div>
        </Link>
      ))}
    </div>
  );
}
