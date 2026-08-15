# 15 — Print stylesheet for Reports

**What to build:** A Department Head reads a Report on screen and takes it into a meeting on paper.
Today printing any page in this application produces the app bar, the side nav, the theme toggle
and a chart cut in half across a page break.

This ticket adds a print stylesheet so a Report prints as a document: title, the parameters it was
run with, the chart, and its data table. **No PDF dependency and no new endpoint** — Phase 6 owns
Browsershot, and only optionally, so this is CSS and nothing else.

- **Chrome is suppressed** — app bar, side nav, notification bell, theme toggle, filter controls and
  the export button have no meaning on paper.
- **The parameters print.** A printed Report with no record of which Program, Semester or Cycle
  produced it is worse than useless in a meeting, because it looks authoritative and cannot be
  checked. This is the detail most easily missed.
- **The chart's data table prints beneath it**, not instead of it. The "View as table" disclosure
  built in ticket 05 already holds the exact series; printing both means a reader can check the
  picture against the numbers, and it degrades gracefully if the chart renders poorly on a given
  printer.
- **Print in the reader's language and direction.** An Arabic Report prints RTL; this is the same
  `direction` mechanism the theme provider already flips and must not be lost in the print rules.
- **Colour is not assumed.** §14 already forbids colour as the only signal; on a monochrome printer
  that stops being a guideline and becomes the situation, so axis and legend text must survive.
- **Page breaks respect blocks** — a chart, a table row group and a heading with its content are not
  split.

**Blocked by:** 08 — Analytics section + Application Pipeline Report + drill-through.

**Status:** ready-for-agent

- [ ] Printing a Report suppresses app bar, side nav, bell, theme toggle, filter controls and
      export button
- [ ] The Report's title and the parameters it was run with appear in the printed output
- [ ] The chart prints together with its data table, not instead of it
- [ ] Arabic Reports print right-to-left with the correct font
- [ ] Output is legible in monochrome; axis and legend text survive and no meaning rests on colour
- [ ] Charts, table row groups and headings are not split across page breaks
- [ ] No PDF library, headless browser or new endpoint is introduced
- [ ] Verified by hand across at least two Reports, in both languages, and recorded in the ticket's
      close-out comment
- [ ] `npm run gate` green
