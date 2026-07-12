# 02 — Self-hosted bilingual fonts

**What to build:** Text renders in the E-JUST type families, self-hosted with no CDN. English uses
IBM Plex Sans and Arabic uses IBM Plex Sans Arabic, and the family switches automatically with the
language, with Arabic getting a taller leading. Only the needed subsets and weights load, text
shows immediately in a fallback while the webfonts arrive (no invisible text), and the two hot
weights of the active language are preloaded. Numeric data uses tabular figures so digits align.

**Blocked by:** 01 — Token-driven theme foundation (needs the theme factory to set `fontFamily`).

**Status:** ready-for-agent

- [ ] English content renders in IBM Plex Sans and Arabic content in IBM Plex Sans Arabic; switching language switches the family with no reload.
- [ ] Fonts are self-hosted — no network request to any font CDN; only the latin (Sans) and arabic (Arabic) subsets at weights 400/500/600/700 are shipped.
- [ ] Text is visible in a fallback during load (`font-display: swap`) — no flash of invisible text; the two hot weights of the active language are preloaded.
- [ ] Arabic uses the taller line-heights from the type scale; numeric tables use tabular figures.
- [ ] Gates green.
