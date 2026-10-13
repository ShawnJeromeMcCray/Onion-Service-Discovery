# 20 - Project Governance Protocol Change and Security Decision Policy

Source: https://docs.google.com/document/d/1e9oZYaeJIP8SzlMrXk7f5G6YVvmFSQucQd3lvZx3aj8/edit?usp=drivesdk

Source modified: 2026-10-12T18:13:15.823Z

---

ONION SERVICE DISCOVERY
PROJECT GOVERNANCE, PROTOCOL CHANGE AND SECURITY DECISION POLICY


1. Purpose
A decentralized protocol can still fail through centralized maintainership, undocumented decisions or rushed releases. This policy governs how technical decisions become normative and how compatibility/security changes are approved.


2. Governance Principles
- protocol before implementation convenience;
- transparent decisions;
- security/privacy review for boundary changes;
- no maintainer may silently redefine identity semantics;
- implementations can compete, Protocol conformance remains testable;
- project owner/maintainers are not runtime root authorities.


3. Document Classes
Normative:
Protocol specification
limits/encoding
crypto construction
schemas
test vectors
security invariants


Product normative:
architecture
privacy
anti-fraud
component specs
production gates


Operational:
deployment
release
incident
runbooks


Advisory:
examples
tutorials
research proposals.


Every repository document labels class/status.


4. Decision Types
Type A implementation detail:
no wire/security/privacy impact.
Normal reviewed change.


Type B architecture:
module/storage/operations change.
ADR required.


Type C security/privacy boundary:
new input, permission, logging, key usage, network destination.
ADR + Threat Model delta + human review.


Type D protocol compatible extension:
Protocol Change Proposal + vectors + conformance update.


Type E breaking protocol:
new protocol version and migration/interoperability plan.


5. ADR Rules
Accepted ADR is immutable historical record.
If changed:
new ADR supersedes old.
Do not edit old decision into pretending it was always different.


6. Protocol Change Proposal
Required before changing:
signed field
canonicalization
hash/signature
key encoding
state semantics
wire schema
endpoint semantics
limits that change acceptance
rotation/migration rules
sync conflict behavior.


7. Proposal Lifecycle
DRAFT
REVIEW
SECURITY_REVIEW
IMPLEMENTATION_EXPERIMENT optional
ACCEPTED
REJECTED
SUPERSEDED


Only ACCEPTED proposal changes normative protocol.


8. Protocol Versioning
protocol_version integer.
Breaking semantic changes => new version.
Old implementations reject unsupported version safely.
Maintainers cannot label a breaking change “bugfix” to avoid bump.


9. Software Versioning
Separate.
Server/client release version independent from protocol.
Support matrix lists:
software version
supported protocol versions
storage schema/backend format
browser storage schema.


10. Backward Compatibility
Every accepted protocol change states:
old node behavior
old client behavior
new node behavior
mixed network behavior
migration
downgrade
rollback.


11. Deprecation
No surprise removal.
Document:
feature/version deprecated
first warning release
replacement
planned removal
security exceptions.


12. Security Invariant Change
Changing/removing invariant requires:
explicit proposal
threat analysis
security reviewer
rationale why previous guarantee can change
user-facing disclosure if guarantee weakens.


13. Human Review Roles
Minimum logical roles:
Protocol reviewer
Security reviewer
Client/browser reviewer
Operations/release reviewer


One person may fill multiple roles in a small project, but report records which hat/review was performed.


14. AI Agent Authority
AI coding agent may:
implement
test
draft ADRs/proposals
identify issues
prepare release candidate.


It may NOT invent:
human approval
external audit
legal approval
Tor Project endorsement
production sign-off.


15. Human Gate
Documents use NEEDS-HUMAN-REVIEW rather than pretending approval.


16. Security Exceptions
Exception:
unique ID
scope
risk
owner
expiry
mitigation
human approval.


Expired exception blocks release.


17. Emergency Change
Critical active issue may justify accelerated fix.
Still requires:
incident ID
minimal patch
regression test
post-hoc ADR/protocol analysis before normal development resumes.
Emergency cannot silently redefine wire identity unless necessary; interoperability impact communicated.


18. Disclosure
Security vulnerability handling follows coordinated disclosure policy appropriate to open source.
Do not publish exploit-sensitive details before remediation if that materially harms users/operators.
Post-remediation transparency expected.


19. Maintainer Compromise
Release process must not rely on one unreviewed workstation/account where infrastructure permits.
Use:
MFA
protected release credentials
review
signed/provenance artifacts
credential rotation.
Document actual controls, not aspirations.


20. Bus Factor
Protocol/spec/test vectors stored in ordinary portable repository formats.
No runtime server controlled solely by founder.
Build reproducible from documented sources.
Successor maintainers can operate.


21. Seed Governance
Built-in Seed list can create soft influence.
Rules:
multiple independent operators where practical
public inclusion criteria
public removal criteria
no fee for trust status
seed only bootstrap, not authority
changes reviewed/release-noted.


22. Seed Inclusion
Candidate seed should:
support protocol
be reliably operated
publish valid Node descriptor
accept public read sync
not require account
meet reasonable security baseline.


No guarantee of permanent inclusion.


23. Seed Removal
Reasons:
long-term outage
protocol invalidity
malicious behavior/equivocation evidence
operator request
security risk.


Removal from software does not blacklist node globally.


24. Observer Sets
If future curated observers:
separate configuration/product layer;
not protocol truth;
public selection criteria;
user can choose alternatives.


25. Moderation Governance
Reference client may ship no mandatory global moderation source.
If defaults ever shipped:
clearly separate
source identity
update process
appeal/correction policy
local disable
no effect on crypto validity.


26. Categories
Category taxonomy governance must avoid hidden policy ambiguity.
Changes versioned.
Service self-selects; category is metadata, not endorsement.


27. Naming
Project public name fixed:
Onion Service Discovery.
Renaming requires owner/human decision and migration docs; no random AI rebrand.


28. License
Human owner selects license.
AI drafts comparison but does not assume permission to change license.
Dependency licenses reviewed.


29. Contribution Policy
Contributions require:
tests
docs
security impact declaration
protocol impact declaration.
Protocol changes use proposal process.


30. Review Checklist
Every PR/change:
Does wire format change?
Does trust boundary change?
Does new network input appear?
New permission?
New dependency?
New persistent field?
New log?
New key usage?
New external standard?
If yes, route to required review.


31. Conformance Authority
Test vectors and normative schemas are maintained with protocol.
A reference implementation bug does not automatically redefine protocol.
If spec and implementation disagree:
determine intended normative behavior through issue/ADR/proposal and update one deliberately.


32. Multiple Implementations
Goal is interoperability.
Do not encode PHP-specific behavior into protocol.
Conformance suite must be runnable independently.


33. Release Support
Define supported release branches:
current stable
possibly previous security branch
EOL policy.
Do not promise indefinite patches.


34. Protocol Support Horizon
Before dropping Protocol v1 support:
usage/interoperability impact
migration path
notice
security rationale
new-version readiness.


35. Data Migration Governance
storage schema/backend format changes are software concern.
Wire/history meaning must not change silently during StorageBackend/schema migration.
Migration checksum and review.


36. Configuration Change Governance
Changing default from discover=false to true, telemetry off to on, etc. is security/privacy-relevant and requires human review/release note.


37. Limit Changes
Increasing limits can be DoS change.
Decreasing limits can break interop.
Use Protocol Change Proposal if acceptance semantics changes.


38. Ranking Changes
Local ranking is client behavior, but major anti-spam/trust UX changes:
document
test
release note.
Ranking must not covertly become global trust authority.


39. Privacy Claim Changes
Any new data collection:
Privacy ADR
data inventory
UI/documentation
human approval.


40. Legal/Policy
Technical project documentation may discuss operator responsibilities but does not provide legal advice.
Requests to implement jurisdiction-specific mandatory behavior require explicit human/legal review before becoming default.


41. Research Features
Experimental:
feature flag
not enabled by default
separate namespace/version if wire risk
not advertised as production guarantee.


42. Forking Project
Open protocol must remain documented enough that others can implement/fork.
No secret backend dependency.


43. Trademark/Project Identity
If trademark policy later established, separate from protocol interoperability.
Third-party conformant implementations should be able to state protocol compatibility without being mistaken for official reference project, subject to human legal policy.


44. Change Log
Every user/security/protocol relevant change entered.
Security fix may be summarized until disclosure safe.


45. Decision Register
Maintain docs/adr/index.md:
ADR
title
status
date
affected spec
security review
superseded by.


46. Protocol Proposal Register
docs/protocol/proposals/index.md:
proposal ID
version
status
implementations
vectors
review.


47. Release Gate Authority
Definition-of-Done matrix is mandatory.
Maintainer cannot waive Critical/High issue informally; requires Security Exception with human sign-off.


48. External Audit Findings
Every finding:
ID
severity
status
fix
regression test
release affected
accepted risk reviewer.
Do not delete embarrassing findings from audit record.


49. Governance Self-Audit
Before major release:
all accepted ADRs reflected?
open proposals?
expired exceptions?
seed changes?
permission changes?
license/dependency changes?
unsupported runtimes?
unresolved audit findings?


50. Final Rule
The governance system exists to stop “the code currently works” from becoming a substitute for protocol correctness, privacy, security or interoperability.
