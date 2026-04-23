---
name: Academic Compliance System
colors:
  surface: '#fcf8fa'
  surface-dim: '#dcd9db'
  surface-bright: '#fcf8fa'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f6f3f5'
  surface-container: '#f0edef'
  surface-container-high: '#eae7e9'
  surface-container-highest: '#e4e2e4'
  on-surface: '#1b1b1d'
  on-surface-variant: '#45464d'
  inverse-surface: '#303032'
  inverse-on-surface: '#f3f0f2'
  outline: '#76777d'
  outline-variant: '#c6c6cd'
  surface-tint: '#565e74'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#131b2e'
  on-primary-container: '#7c839b'
  inverse-primary: '#bec6e0'
  secondary: '#0040e0'
  on-secondary: '#ffffff'
  secondary-container: '#2e5bff'
  on-secondary-container: '#efefff'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#002113'
  on-tertiary-container: '#009668'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dae2fd'
  primary-fixed-dim: '#bec6e0'
  on-primary-fixed: '#131b2e'
  on-primary-fixed-variant: '#3f465c'
  secondary-fixed: '#dde1ff'
  secondary-fixed-dim: '#b8c3ff'
  on-secondary-fixed: '#001356'
  on-secondary-fixed-variant: '#0035be'
  tertiary-fixed: '#6ffbbe'
  tertiary-fixed-dim: '#4edea3'
  on-tertiary-fixed: '#002113'
  on-tertiary-fixed-variant: '#005236'
  background: '#fcf8fa'
  on-background: '#1b1b1d'
  surface-variant: '#e4e2e4'
typography:
  h1:
    fontFamily: Sora
    fontSize: 32px
    fontWeight: '600'
    lineHeight: '1.2'
  h2:
    fontFamily: Sora
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.3'
  h3:
    fontFamily: Sora
    fontSize: 20px
    fontWeight: '500'
    lineHeight: '1.4'
  body-lg:
    fontFamily: Source Sans 3
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Source Sans 3
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.5'
  body-sm:
    fontFamily: Source Sans 3
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1.4'
  label-caps:
    fontFamily: Sora
    fontSize: 12px
    fontWeight: '700'
    lineHeight: '1'
    letterSpacing: 0.05em
  data-tabular:
    fontFamily: Source Sans 3
    fontSize: 14px
    fontWeight: '600'
    lineHeight: '1'
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  sidebar_width: 280px
  top_bar_height: 64px
  base_unit: 8px
  container_padding: 32px
  gutter: 24px
  stack_sm: 12px
  stack_md: 24px
---

## Brand & Style

The visual identity of this design system is rooted in the "Modern Academic" movement—a fusion of corporate reliability and institutional precision. It is designed to facilitate high-stakes operational workflows where clarity and compliance are paramount. The aesthetic prioritizes information density without sacrificing scannability, utilizing a structured hierarchy that mirrors the rigorous nature of academic administration.

The style is defined as **Corporate Modern**, characterized by a neutral foundation that allows role-based color accents to serve as functional wayfinding tools. The UI should evoke a sense of calm authority, ensuring users feel supported by a stable, high-performance environment. Visual embellishments are minimized in favor of architectural clarity, using subtle depth to separate global navigation from active workspaces.

## Colors

The color strategy employs a "Role-Based Chromatic Logic" to provide instant contextual awareness. While the system's foundation is built on Navy (#0F172A) for administrative stability, the interface dynamically adjusts its accent profile based on the authenticated user:

- **Global Foundations:** A neutral palette of Slate and Cool Grays provides the canvas, ensuring high contrast for text and maintaining WCAG 2.1 Level AA compliance.
- **Student Persona:** Cobalt (#2E5BFF) highlights, suggesting a modern, tech-forward learning experience.
- **Teacher Persona:** Deep Blue (#1A237E) accents, conveying traditional academic authority and depth.
- **Community Supervisor Persona:** Emerald (#10B981) tones, representing growth and external coordination.
- **Super Admin Persona:** The Navy foundation is paired with Amber (#F59E0B) highlights to signal critical system alerts and high-level management functions.

## Typography

This design system utilizes a dual-typeface strategy to balance character with utility. **Sora** is reserved for headings and primary identifiers; its geometric construction provides a modern, distinctive personality that stands out in an academic context. 

**Source Sans 3** is the workhorse for all body copy, data tables, and administrative forms. It was chosen for its exceptional legibility in high-density layouts and its professional, neutral tone. To assist with scannability in an operations-heavy environment:
- Use **Sora** in semi-bold weights for page titles to ground the user.
- Use **Source Sans 3** with slightly increased tracking for small labels to ensure readability on low-resolution institutional displays.
- Tabular data should use the specific `data-tabular` token to ensure vertical alignment in compliance reports.

## Layout & Spacing

The layout follows a strict functional grid designed for desktop-first academic operations. 

- **Sidebar:** A fixed 280px left sidebar houses the primary navigation. It uses a dark-themed aesthetic (Navy) to recede from the main content area, focusing the user's eye on the workspace.
- **Identity Bar:** A 64px top bar provides persistent breadcrumbs, search, and role-specific identity indicators.
- **Content Area:** A fluid workspace that utilizes an 8px rhythmic grid. Information density is kept high by using 24px gutters, allowing for multi-column data views without visual clutter. 
- **Information Blocks:** Content is grouped into logical modules with 32px of internal padding to maintain "breathability" within the high-density environment.

## Elevation & Depth

Depth in this design system is used to signify "interactivity" and "importance" rather than realism. It employs three specific methods:

1.  **Tonal Tiering:** The background uses a subtle off-white (#F8FAFC), while primary workspace cards use pure white (#FFFFFF). This creates a natural hierarchy without the need for heavy shadows.
2.  **Soft Shadows:** Interactive elements like active cards or dropdown menus utilize highly diffused, low-opacity shadows (Color: Navy, Alpha: 4-6%, Blur: 12px). This creates a "lifted" effect that is sophisticated and non-distracting.
3.  **Subtle Borders:** To maintain precision, all containers are defined by 1px solid borders in a light gray-blue. This reinforces the "academic" grid and helps users differentiate between adjacent data points in dense reports.

## Shapes

The shape language is "Soft" (0.25rem / 4px base), reflecting a balance between the rigidity of compliance systems and the friendliness of modern education tools.

- **Buttons & Inputs:** Use the 4px base radius to feel precise and "clickable."
- **Cards & Modals:** Use `rounded-lg` (8px) to soften the large surface areas of the dashboard and provide a modern container feel.
- **Contextual Elements:** Elements like search bars or primary action buttons may occasionally use a higher radius to draw attention, but the core system remains architectural and squared.

## Components

Components are designed for clarity, high contrast, and operational speed.

- **Role-Based Sidebar:** The 280px sidebar items change their "active" state indicator color based on the user's role (e.g., a Cobalt bar for Students, Emerald for Supervisors).
- **Compliance Chips:** Status indicators for "Compliant," "Pending," or "Non-Compliant" use a high-contrast background with bold Sora labels. They must include an icon to ensure accessibility for color-blind users.
- **Data Tables:** These are the heart of the system. They feature sticky headers, zebra-striping on hover, and high-contrast borders. Typography is kept to `body-sm` to maximize visible rows.
- **Action Cards:** Summary tiles at the top of dashboards use role-specific accent borders (2px top border) to categorize information quickly.
- **Input Fields:** Use Source Sans 3 for input text. Focus states must use a 2px ring in the user's role-based accent color to provide clear visual feedback.
- **Identity Bar:** Features a "Role Badge" next to the user's name, utilizing the specific role color palette to remind the user of their current access level.