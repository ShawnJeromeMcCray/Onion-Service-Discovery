# 31 - Accessibility Security UX and Human-Factors Validation Standard

Source: https://docs.google.com/document/d/1p4czVA3MQXFxkOjSCejdA6xam_U7S78NmkDu41b3Smw/edit?usp=drivesdk

Source modified: 2026-10-12T19:19:16.163Z

---

ONION SERVICE DISCOVERY
ACCESSIBILITY, SECURITY UX AND HUMAN-FACTORS VALIDATION STANDARD


1. Purpose
Security can fail even when cryptography is correct if users misread labels, cannot perceive warnings, or cannot operate the interface accessibly. Onion Service Discovery therefore treats accessibility and security comprehension as release requirements.


2. Accessibility Baseline
Reference client targets WCAG 2.2 Level AA for applicable extension UI and project web documentation.


This is an engineering conformance target, not a claim that W3C certifies the product.


3. Scope
Search UI
results
service detail
identity/fork/migration warnings
settings
node/source management
diagnostics
first-run/onboarding
operator web documentation
error states
update/storage migration notices.


4. Core Accessibility Requirements
- keyboard operable;
- visible focus;
- logical focus order;
- accessible names/roles/states;
- no information conveyed only by color;
- sufficient contrast;
- text resize/zoom support;
- responsive layout;
- screen-reader-compatible status announcements;
- touch target sizes appropriate to WCAG 2.2 target-size guidance where applicable;
- no flashing/unsafe animation;
- motion reduced where user preference indicates.


5. Security Warning Accessibility
Every identity/security warning includes:
icon or semantic marker
clear heading
plain-language explanation
specific affected identity
recommended safe next action
technical details expandable.


Color alone never distinguishes safe/changed/forked.


6. Warning Severity Vocabulary
INFORMATION
ATTENTION
HIGH-RISK IDENTITY CHANGE
PROTOCOL/INTEGRITY ERROR


Avoid generic scary red states for ordinary offline/stale conditions.


7. Human-Factors Goal
Users should correctly answer:
- Does “signature valid” mean this service is safe? No.
- Is a same-name service necessarily the same identity? No.
- What does a saved identity pin protect against?
- What is a verified migration?
- Why can an offline service remain in search?
- What does a fork mean?


8. Security Copy Principles
Concrete evidence language.
Avoid:
safe
trusted
legit
official
verified
without qualification.


Prefer:
“Manifest signature valid”
“Manifest directly checked at this Onion address”
“Same logical Service identity as your saved pin”
“Different logical Service identity than your saved pin”
“Conflicting signed revisions detected”.


9. Progressive Disclosure
Default result cards show limited comprehensible evidence.
Detail view exposes:
full Onion
full fingerprint
revision/hash
observation timestamps
rotation/migration chain
observer sources
technical error state.


Security-critical warning must not be hidden only behind expert details.


10. Onion Address Readability
Provide:
full copyable address
monospace/legible presentation
safe wrapping
optional grouped visual presentation that does not change copied canonical value.


Never truncate in identity-confirmation dialog without a path to full value.


11. Fingerprint UX
Display human-comparable groups.
Allow copy full fingerprint.
Explain:
fingerprint identifies Onion Service Discovery Service Identity key, not Tor private key or human organization.


12. Unicode Confusable UX
When name contains suspicious mixed scripts/confusables/bidi controls:
show neutral warning.
Provide normalized/skeleton comparison only as advisory.
Never rewrite the signed name and imply that rewritten string is canonical identity.


13. Pinning UX Study
Test whether users understand:
“Save identity” vs “Favorite”.


Recommended:
Favorite = convenience.
Remember/Pin Identity = security continuity.
If combined, label clearly.


14. Migration UX Study
Create prototypes for:
valid migration
identity continuity only
old endpoint confirmed
new endpoint confirmed
invalid/unproven address change.


Measure whether users correctly distinguish “moved” from “different identity”.


15. Fork UX Study
Goal:
users should not interpret Fork as merely “temporary server error”.
Explain:
two conflicting signed states exist for same revision/continuity point.
Recommended action:
do not rely on changed identity until resolved; inspect details.


16. Staleness UX
Separate:
Recently observed
Temporarily unreachable
Long time since directly observed
Withdrawn.


Do not color ordinary Tor downtime like compromise.


17. Moderation UX
Display moderation separately:
“Identity checks”
“Community/source warnings”.


A phishing report does not make signature invalid.
A valid signature does not clear phishing report.


18. Accessibility Test Matrix
Automated where useful:
semantic lint
contrast
keyboard smoke
ARIA checks.


Manual:
screen reader
200–400% zoom
keyboard-only
touch target review
forced-colors/high-contrast mode where supported
reduced motion.


19. Screen Reader
Test at least one mainstream screen reader on target platform where practical.
Security state changes use appropriate live-region/announcement behavior without overwhelming.


20. Mobile/Small Window
Web Local Search and operator/status pages must remain usable on compact/mobile viewports.
Long Onion/fingerprint content cannot overflow controls.


21. Localization
Reference security semantics in English glossary.
Translations:
professional/reviewed where possible;
security labels have translation notes;
do not translate fingerprints/protocol identifiers;
support RTL layout safely.


22. Translation QA
For each locale:
security-copy back review;
layout stress with longer text;
RTL if relevant;
plural/date formatting;
no mistranslation of “identity”, “signature”, “migration”, “verified”.


23. Plain Language
Provide short explanation first, exact technical evidence second.
Avoid assuming users know:
Ed25519
hash
revision chain
Sybil
TOFU.


24. Expert Mode
Optional detail mode can expose raw evidence.
It does not unlock weaker security behavior.


25. Error Recovery UX
Error message answers:
what happened?
what remains safe/usable?
what did NOT happen?
what can user do?
can retry wait?
is local index still available?


26. Permission UX
When a privacy-relevant Web Client capability or server-search fallback is offered:
state exact node/source access.
No broad “allow everything for best experience” language.
Denial supported.


27. First-Run
Teach three facts:
search is local;
technical identity is not content safety;
the project intentionally avoids requiring extra Tor Browser extensions; explain instead the serving-origin trust boundary and JavaScript/no-JavaScript privacy difference.


Keep concise but link detail.


28. User Research Protocol
Before stable client:
conduct structured comprehension sessions with representative participants.


Minimum scenarios:
same-name impersonation
pin mismatch
valid migration
fork
stale/offline
moderation warning.


Record aggregate comprehension, not unnecessary personal data.


29. Research Privacy
Do not collect real Onion browsing history.
Use synthetic test services.
Obtain consent for usability study.
Store minimal anonymized findings.
Delete raw recordings according to study policy.


30. Comprehension Threshold
Set measurable acceptance before stable:
for critical warnings, strong majority of participants should identify correct safer action and not infer false guarantee.
Exact threshold is Human UX decision and documented before test.


31. Heuristic Review
Independent reviewer assesses:
consistency
visibility of state
error prevention
recovery
recognition vs recall
accessibility
security habituation risk.


32. Warning Fatigue Budget
Avoid warning on every normal state.
Reserve high-severity presentation for:
pin mismatch
fork
unproven identity change
critical integrity failure.


33. Dark Pattern Prohibition
No:
prechecked publishing opt-in;
permission nag loops;
hidden telemetry consent;
scary wording to force enabling network sources;
misleading green safety badges.


34. Accessibility Claim
If project states WCAG 2.2 AA conformance, maintain an Accessibility Conformance Report or equivalent evidence with:
scope
version
testing methods
known exceptions.
Do not claim before assessed.


35. Current Standard Baseline
At planning time, WCAG 2.2 is a W3C Recommendation and also recognized as ISO/IEC 40500:2025.
Refresh current official status before release documentation.


36. UX Fixture Library
Maintain screenshots/component states for:
normal
stale
withdrawn
pin
pin mismatch
rotation
migration
fork
invalid data
offline nodes
empty index.


Used for visual regression/accessibility review.


37. Copy Registry
docs/ux/security-copy-registry.md:
copy ID
state
English reference
meaning
must-not-imply
translations
tests.


38. UI Evidence Traceability
Every trust/security badge:
source state
normative definition
copy
tooltip/help
accessible name
test.


39. Accessibility CI Gate
Build fails for:
missing accessible labels detected by tests
forbidden remote assets
obvious contrast/semantic lint failures where automated.
Manual items remain RC checklist.


40. Acceptance
PASS requires:
WCAG 2.2 AA applicability matrix;
manual accessibility review;
security-copy registry;
human comprehension study for critical identity flows;
no color-only warnings;
translation semantic controls;
documented Web-serving-origin and local-vs-server-search privacy caveat


41. OPERATOR STATUS UX
The status dashboard is a first-class accessibility target.


Top-of-page question:
“Can this Service currently be found through Onion Service Discovery?”


Answers use text + icon, never color only:
Not configured
Publishing disabled
Waiting for verification
Discoverable on checked Nodes
Degraded
Identity conflict


42. PROGRESS WITHOUT FAKE PRECISION
Do not show a fake global percentage.


Use truthful milestones:
Configured
Manifest valid
Self-verified
Announced
Externally observed
Searchable on checked Nodes


43. MOBILE OPERATOR EXPERIENCE
/status/ MUST work well in Tor Browser Android:
single-column layout;
large touch targets;
long Onion/fingerprint wrapping/copy;
no hover-only controls;
clear remediation.


44. WARNING SEVERITY
Propagation delay is informational.
Tor outage is operational.
Identity fork/key conflict is security-critical.


The visual hierarchy MUST keep those meanings distinct.
