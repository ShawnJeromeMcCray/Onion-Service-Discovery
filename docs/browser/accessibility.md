# Browser client accessibility

What the reference browser client does for keyboard, screen-reader and
low-vision use, how it is tested, and what has not been checked yet. The
security texts themselves are listed in the
[security copy registry](../ux/security-copy-registry.md).

## Implemented

- **Without JavaScript** (for example Tor Browser's Safest level) the page shows
  only an explanation, the offline-validator hint and, if the host offers it, the
  server-search link. The application controls stay hidden instead of showing
  buttons that do nothing.
- **Announcements**: one polite live region receives a short summary when a
  load ends ("Loading finished: 2 of 3 sources verified, 1 not loaded") and the
  result count after the reader changes the search. A separate alert region is
  used only when a pinned identity newly enters a high-risk state (another key
  on the pinned Onion, a fork). The page no longer re-announces every update.
  While a source loads, its card shows how many records have been checked; that
  line is updated in place and deliberately not announced.
- **Focus**: views are rebuilt on every worker update, but keyboard focus returns
  to the same control (for example from "Pin this identity" to "Remove pin" of
  the same Service), opened details stay open and the result page is kept.
  When a control disappears, focus moves to the heading of its section.
- **Names**: repeated controls carry the affected identity in their accessible
  name, for example "Pin this identity: Synthetic Library" or "Remove local pin:
  Synthetic Library"; the visible text stays part of the name.
- **Severity without colour**: every notice has a marker and, for problems, the
  word "Note:" or "Warning:"; ordinary outages are never styled like an identity
  conflict. Text-field boundaries have at least 3:1 contrast against their panel
  (WCAG 1.4.11); text colours have at least 4.5:1.
- **Identity safety**: a result with the same self-declared name as a pinned
  identity but another key is flagged, the pinned identity leads its group of
  similar entries, and invisible or direction-changing characters in names,
  descriptions, pin labels and imported pins are reported. The identity key and
  the onion address can be copied with buttons.
- **Look-alike names** (decision D-024): names are compared by Unicode 17
  confusable skeletons, so a Cyrillic "а", "rn" for "m" or a capital I for "l"
  cannot pass as a pinned name: such a result gets the same kind of warning as
  an identical name under another key. Entries whose names look like each other
  get a written note on each, so do pins with look-alike labels, and the pin
  import preview warns before a label imitates a pin you hold. The notes are
  text with the usual marker and label, read in order after the name; names are
  never rewritten, hidden or reordered, so what a screen reader announces is
  exactly the signed name.

## Tested automatically

`npm run test:browser` (Chromium, controlled server) checks the JavaScript-off
view, focus return after pinning and unpinning, open details across updates,
the load summary and result-count announcements, accessible names of all
visible controls and the text-field contrast ratio, and the look-alike notes
and warnings with a validly signed look-alike Service imported as a local file.
`npm test` covers the look-alike checks (including real pairs from Unicode's
confusables.txt), pin states and group ordering.

## Not yet checked

A manual screen-reader pass (Orca with Tor Browser), forced-colours and 200 %
zoom reviews, Tor Browser Android, and the comprehension study of spec 31
section 30 are open. Their results belong in this file once done.
