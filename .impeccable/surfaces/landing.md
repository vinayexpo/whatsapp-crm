# Public landing page

- Route: `/`; source: `app/routes/landing.tsx` and `app/routes/landing.module.css`.
- Confirmed request: an interactive public front page covering existing Creative Connects features, in the existing theme. User refinement: "Focus more on AI and automation".
- Direction: code-led, product-led demonstration within the incumbent teal, Manrope, Space Grotesk, and Material icon system. No approved raster comp.
- First viewport: AI headline, channel context, sign-in CTA, and an interactive AI demonstration. Mobile stacks copy above the demonstration.
- Visitor path: AI replies, voice visualization and human handoff; example workflow switcher; five product tabs; capability index; FAQs; sign-in close.
- Behavior: sample reply buttons, keyboard-operable tabs, workflow choices, continuous automatic workflow and voice motion, mobile menu, native FAQ disclosure, reduced-motion support. No play, pause, replay, or step-through controls appear on the landing or industry previews.
- Truth: demonstrations are labeled sample or illustrative. Voice walkthrough has no audio and places no calls. Real capabilities depend on account roles and integrations. CTAs lead to `/login`; authenticated workspace is `/dashboard`. No public signup or published pricing is implied.
- Historical initial render evidence: `.impeccable/review/desktop.png` (1440), `mobile.png` (390), `wide.png` (1728), and `mobile-hero.png`.
- Historical initial finish review: `ship` at the supplied source/render scope; no material fixes. Formal concept artifacts and QUALITY BAR comparison inputs were absent and outside that approval scope. Builder reported interaction checks, typecheck, build, 29 frontend tests, and rendered spacing checks with zero critical or serious findings; the reviewer did not rerun those checks. This evidence predates the current motion revision.
- Durable product truth remains in `PRODUCT.md`; global visual authority is documented in `DESIGN.md`.
- Industry extension: the shared public header/footer link to `/industries`, and a landing section links to all fifteen canonical sector use-case pages, including Automobile, Travel & Tourism, Government, and Marketing Agencies. AI-first examples, accessible workflow motion, legacy aliases, capability boundaries, and review evidence are documented in `industries.md`; this extends the existing public theme.

## Current continuous-motion behavior

The landing hero uses the same illustrative Enquiry → AI assistant → Your team graphic as the industry previews, keyed by selected mode and sample. It loops continuously while visible: 1.4-second dash cycles, 4.2-second packet cycles, and stage changes every 1.4 seconds. A different mode or sample starts a fresh run; selecting the current choice does not restart it. Offscreen and hidden-document checks suspend the graphic, and returning to view resumes the loop. Reduced motion displays the static final stage.

The voice panel automatically loops its waveform in alternating 0.8-second iterations with staggered delays. Reduced motion leaves the waveform static. The visible transcript and explicit no-audio/no-calls statement remain available. Voice motion is illustrative and has no playback controls. Existing public colors, typography, shell, and components remain the visual authority.

Current final evidence is `.impeccable/review/motion-{landing,industries,education,government}-{390,1440,1728}.png` (twelve page/width combinations), with `motion-before-*` baselines kept separately. Final Better Design spacing checks returned zero critical or serious findings across all twelve combinations after the alignment fix. The detector retained an incumbent-font warning and palette advisories. Comprehension and review checks were completed. A fresh reviewer returned `ship` with no material findings within the supplied captures, source, and logs; it did not perform an independent runtime check.

`.impeccable/review/motion-browser.log` records the absence of playback controls across all fifteen industry pages, finite workflow motion, reduced-motion behavior, settled voice motion, no browser errors (`errors: []`), and zero horizontal overflow. `motion-landing-phone.log` records passing landing voice and handoff interactions in a touch browser using Slow 3G with cache disabled; `motion-phone.log` records passing industry interactions under the same conditions. All 62 frontend tests, typecheck, and production build exited successfully. These checks establish local verification; deployment of this revision has not yet been recorded.


## Continuous motion refinement

The user subsequently requested continuous loops rather than motion that settles. Workflow graphics now repeat their stage and packet sequences indefinitely while visible; the landing voice waveform also repeats indefinitely. Playback buttons remain absent. Reduced-motion preferences keep the graphics static. Earlier finite-motion verification above describes the previous revision only.

Current loop verification: 62 frontend tests passed. The production build passed. Browser checks confirmed all three graphic CSS animations remain infinite and moving after nine seconds on the landing, directory and sector template; the voice waveform loops, no playback controls appear, and reduced motion stays static. Better Design measured the graphic at 390, 1440 and 1728 pixels on all three surfaces with zero critical or serious spacing findings. Evidence: .impeccable/review/loop-browser.log, loop-tests-final.log and loop-spacing-*.json.
