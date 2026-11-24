---
name: Creative Connects
description: Existing teal workspace identity with scoped public landing patterns.
colors:
  primary: "#00A884"
  primary-light: "#3FC9A8"
  primary-dark: "#00785F"
  secondary: "#7C4DFF"
  secondary-light: "#A578FF"
  secondary-dark: "#5A2FD4"
  background: "#F4F7F6"
  paper: "#FFFFFF"
  ink: "#1B2B29"
  muted: "#5C6F6B"
  divider: "#E3EBE9"
  success: "#2FB673"
  warning: "#F2A93B"
  error: "#E5484D"
  info: "#3B82C4"
  landing-paper: "#F7FAF8"
  landing-line: "#DCE7E3"
  landing-hover: "#00624D"
typography:
  display:
    fontFamily: '"Space Grotesk", "Manrope", sans-serif'
    fontWeight: 700
  body:
    fontFamily: '"Manrope", "Helvetica", "Arial", sans-serif'
  button:
    fontFamily: '"Manrope", "Helvetica", "Arial", sans-serif'
    fontWeight: 600
  landing-display:
    fontFamily: '"Space Grotesk", "Manrope", sans-serif'
    fontSize: "clamp(2.8rem, 4.35vw, 4.65rem)"
    lineHeight: 1.075
    letterSpacing: "-0.04em"
  landing-body:
    fontFamily: '"Manrope", sans-serif'
    fontSize: "1rem"
    lineHeight: 1.65
rounded:
  control: "8px"
  default: "10px"
  landing-preview: "12px"
  landing-window: "14px"
spacing:
  small: "8px"
  medium: "16px"
  large: "24px"
  section-gap: "40px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.paper}"
    rounded: "{rounded.control}"
    typography: "{typography.button}"
  landing-button-primary:
    backgroundColor: "{colors.primary-dark}"
    textColor: "{colors.paper}"
    rounded: "{rounded.control}"
    padding: "12px 22px"
  landing-button-primary-hover:
    backgroundColor: "{colors.landing-hover}"
  landing-preview:
    backgroundColor: "{colors.landing-paper}"
    rounded: "{rounded.landing-preview}"
---

# Design System: Creative Connects

## Overview

The incumbent Material UI workspace uses teal actions, pale neutral surfaces, rounded controls, and a Manrope / Space Grotesk pairing. Preserve this identity when extending the product.

Source authority is `app/mui-theme.ts`; `app/root.tsx` loads both font families. Tokens prefixed `landing-` describe additions in `app/routes/landing.module.css`, scoped to the public landing page rather than replacements for the application theme.

## Colors

Primary teal identifies actions and selected states. Purple is the incumbent secondary accent. Ink, muted text, paper, and dividers support the light workspace; semantic colors retain their existing status meanings. The landing page uses primary-dark for white-text actions and its own pale paper and divider tokens.

## Typography

Space Grotesk headings use bold weight; Manrope carries body copy, controls, and navigation. Application buttons retain sentence case. The landing display clamp is desktop-specific: below 600px it becomes `clamp(2.2rem, 8.7vw, 3.2rem)` with line height `1.11` and normal wrapping. Do not apply that display scale to workspace screens.

## Layout

The authenticated application retains its existing navigation shell and dense working views. The public landing uses a centered container capped at 1328px, with 56px desktop side gutters and 20px gutters below 600px. Its grids progressively stack at 1199px, 900px, and 600px; large-display adjustments begin at 1600px. These are landing breakpoints, not a new application grid.

## Elevation & Depth

The MUI theme removes AppBar shadow and Paper background images. Landing previews primarily use borders and tonal surfaces; its expanded mobile navigation has one soft offset shadow. Preserve component-specific MUI behavior rather than imposing landing depth globally.

## Shapes

The application uses gently rounded surfaces and smaller button corners. Landing preview windows use slightly larger corners, while chat bubbles, tags, and avatars keep their distinct interface roles.

## Components

Use existing Material UI components and icons for workspace additions. Preserve theme button and chip weights. Landing CTAs use darker teal, a compact upward hover shift, and a visible focus outline. Landing tabs expose selected states and arrow-key navigation; FAQs use native disclosure elements. Motion is disabled under reduced-motion preferences. These public-page interactions do not redefine the application component library.

## Do's and Don'ts

- Do inherit `app/mui-theme.ts` for authenticated product screens.
- Do preserve Manrope, Space Grotesk, and the existing Material icon language.
- Do keep landing tokens and composition scoped to the public page.
- Don't replace application tokens with landing-specific surfaces or display sizes.
- Don't invent a new visual identity when extending this product.
