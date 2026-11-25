# Public industry use cases

- Routes: `/industries` and `/industries/:sector`; supported slugs are `schools`, `clinics`, `restaurants`, `real-estate`, `salons`, `gyms`, and `ecommerce`.
- Confirmed direction: sector-specific use cases that lead with AI replies and automation, extending the existing Creative Connects public website. This is an ordinary, code-led extension of the incumbent visual world; no new identity, raster assets, or approved raster comp.
- Source authority: `app/routes/industries.tsx`, `app/routes/industry.tsx`, `app/data/industries.ts`, `app/routes/industries.module.css`, and shared `app/components/public/public-shell.tsx` / `industry-grid.tsx`. Existing public tokens and controls come from `landing.module.css`; global visual authority remains `DESIGN.md`. `PRODUCT.md`, `DESIGN.md`, and `.impeccable/design.json` are preserved.

## Layout and visual continuity

The directory and sector pages share the public header, mobile navigation, brand, sign-in link, footer, and skip link with the landing page. The directory introduces the messaging use cases and offers a numbered, bordered grid of seven sectors. Sector pages place an audience-specific headline and sign-in action beside a sample conversation, followed by three explained use cases, a three-step setup section, native FAQs, related sectors, and a closing sign-in CTA.

Preserve the existing teal actions, pale neutral background, dark ink, Manrope body, Space Grotesk headings, and Material icon language. Teal emphasizes useful actions and selected samples. Conversation bubbles and a softly rounded demonstration window provide the visual example; use-case rows and sector links use restrained borders. Do not add repeated heading kickers: the final source removes the review finding's redundant labels and relies on headings to establish hierarchy. The sector divider (`#DCE7E3`) matches the established public line color; no global design drift was identified in this source scope.

The directory grid changes from three columns to two below 1200px and one below 600px. Sector heroes stack below 900px, with copy before the demonstration. Setup and FAQ layouts become single columns; phone actions stack, numbered use-case rows simplify, and the sample tabs remain visible. Landing sector links use four columns, becoming two below 900px. Layout inherits the public container and responsive gutters; headline sizes and demonstration spacing adapt without introducing a separate application theme.

## Interaction and routing

- Each sector has three labelled sample tabs. Click, ArrowLeft, ArrowRight, Home, and End select a scenario and move focus; only the selected tab enters the tab order. The selected tab identifies its panel, and the changing conversation is announced politely. Each scenario changes the customer question, illustrative AI answer, and three workflow steps. Navigating to another sector resets the sample through a sector-keyed component.
- FAQs use native `details` / `summary` disclosures. Shared mobile navigation exposes expanded state, closes after selecting a link, and supports Escape with focus restored to its toggle. Existing public focus and reduced-motion behavior remains in force.
- Directory cards, breadcrumbs, related sectors, shared navigation/footer, and the landing page's seven sector links connect the public routes. Product and automation links point back to the matching landing anchors. Sign-in actions lead to `/login`; no public registration or trial flow is invented.
- Unknown sector slugs show a helpful industry-browser fallback within the shared public shell, with `robots: noindex` and an industry-not-found title. This is a rendered fallback, not a documented HTTP 404 response.
- The directory provides title, description, and canonical metadata. Valid sector pages add sector-specific title, description, Open Graph title/description, and canonical URL under `https://creativeconnects.web.id/industries/`. `app/routes.ts` registers the directory and parameterized route; `public/sitemap.xml` includes all eight valid URLs.

## Truth and capability boundaries

The demonstration is labelled sample and illustrative, and explicitly states that it sends no messages and books no appointments. Tabs only switch local example content. Examples explain the product's configurable AI knowledge, shared inbox, chat menus, assignments, contacts, campaigns, CRM, and commerce capabilities; they do not imply that a sector-specific operational system already exists. Actual WhatsApp use requires the relevant business account, permissions, and channel setup. Campaigns depend on eligible audiences, consent, and template requirements.

- Schools: staff confirm admissions; examples do not establish student records, attendance, or fee management.
- Clinics: examples cover administrative enquiries; staff confirm appointments. No diagnosis, medical advice, clinical-record integration, or compliance certification is implied.
- Restaurants: ordering, delivery, and payment behavior depends on configured services; staff confirm dietary questions and changes.
- Real estate: agents confirm property availability, prices, and visits; no built-in listing feed or visit calendar is promised.
- Salons: staff confirm appointment requests; no stylist calendar or automatic booking is implied.
- Gyms: staff confirm tours, terms, and prices; no class booking, access-control integration, or personalized fitness advice is implied.
- Ecommerce: catalog, inventory, delivery, and payments depend on configuration; no automatic marketplace synchronization or abandoned-cart recovery is implied.

## Evidence and finish status

Source inspection covered the routes, content data, shared public components, industry stylesheet, landing links, route registration, and sitemap. Documentation render inspection included `.impeccable/review/industry-schools-1440.png`, confirming the shared shell, conversation-first hero, numbered use cases, setup, FAQs, related sectors, and closing action. Final captures are recorded under `.impeccable/review/industry-{landing,industries,schools,clinics,restaurants,real-estate,salons,gyms,ecommerce}-{390,1440,1728}.png`. An independent reviewer inspected all 27 captures, requested removal of heading kickers, and returned `ship` after scoring that fix resolved across the final captures.

Verification: 47 frontend tests passed; the 13 affected landing/sector tests passed again after the review fix. Typecheck and production build passed. All 27 final renders have zero horizontal overflow and no browser errors. Better Design measured the directory and shared school template at three widths with no critical or serious findings; sector hero and all 21 sample-panel comprehension checks passed. A 390px touch-emulated browser with Slow 3G (400ms latency, 50kB/s) and cache disabled successfully changed a clinic sample, opened navigation, visited the directory and followed a school link with no errors or overflow. Public deployment is recorded separately by GitHub Actions.
