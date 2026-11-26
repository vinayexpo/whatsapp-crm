# Creative Connects branding

The user supplied a CreativeConnects logo with interlocking Cs, circuit terminals, a chat bubble and voice arcs, and authorized a theme-colored adaptation throughout the application. Preserve the existing teal palette: #00785F, #00A884 and dark ink #1B2B29.

`public/brand/creative-connects-icon.svg` is a clean native vector adaptation of the supplied symbol. `app/components/brand-logo/brand-logo.tsx` combines it with a Manrope wordmark; compact sidebar lockups use the simplified CC/chat icon. The small source tagline is omitted for readable navigation. No trailing dot appears after the name.

Shared branding covers public header/footer, login, initial setup, privacy/terms pages, company workspace navigation and superadmin navigation. Channel-specific WhatsApp and Instagram icons continue identifying those channels.

The favicon is the CC/chat symbol only. The same identity supplies ICO, Apple touch and standard/maskable PWA PNG icons. Maskable variants leave additional safe-area padding. Versioned icon URLs and the service-worker cache revision refresh earlier branding.

Two built-in image-generation variants were inspected and rejected for insufficient small-size edge quality; no generated raster is used. Shipped vector geometry references the user-supplied logo. `public/brand/source.json` records this provenance.

Verification: 62 frontend tests, typecheck and production build passed. Local browser checks covered eight logo placements at 390, 1440 and 1728 pixels, with loaded images, complete wordmarks and no horizontal overflow or browser errors. Authenticated navigation used local mock API responses to inspect branding only. Better Design measured public, sign-in, workspace and superadmin logo regions at all three widths; final results contain no critical or serious findings after removing the sign-in action's elevation. Screenshots and logs are under .impeccable/review/logo-*. Production verification is recorded separately from these local checks.
