# 47 - Plug-and-Play Operator Dashboard Self-Service Onboarding and Discoverability UX Standard

Source: https://docs.google.com/document/d/1KFC2Iah1WcHepCbuF0Gcm1eCqMvMiOFSRm0ADXvKGHk/edit?usp=drivesdk

Source modified: 2026-10-12T19:32:38.463Z

---

ONION SERVICE DISCOVERY
PLUG-AND-PLAY OPERATOR DASHBOARD, SELF-SERVICE ONBOARDING AND DISCOVERABILITY UX STANDARD


Status
Binding operator-experience standard. The reference implementation MUST make ordinary publish-only participation simple without weakening security semantics.


1. Product Principle
The normal operator experience should feel approximately like:


Step 1 — copy/install Onion Service Discovery;
Step 2 — edit one clear PHP configuration file or run init once;
Step 3 — open the operator's own Onion Service in Tor Browser;
Step 4 — open /onion-service-discovery/status/;
Step 5 — see exactly what is ready, what is missing and whether selected external Discovery Nodes have observed the Service;
Step 6 — once publish is enabled and external inclusion evidence succeeds, see “Discoverable” with sampled evidence.


The implementation may be internally modular and security-hardened. Simplicity is a deployment/user-experience goal, not a requirement for monolithic code.


2. Common-Case Configuration
A normal publish-only operator SHOULD be able to configure the product from one PHP configuration file outside the public document root.


Conceptual example:


return [
    'service' => [
        'onion' => 'exampleexampleexampleexampleexampleexampleexampleexample.onion',
        'name' => 'Example Service',
        'description' => 'Example public Onion Service',
        'categories' => ['technology'],
        'languages' => ['de', 'en'],
    ],
    'roles' => [
        'publish' => true,
        'mirror' => false,
        'discover' => false,
        'sync' => false,
    ],
    'storage' => [
        'backend' => 'filesystem',
    ],
    'bootstrap' => [
        'announce' => true,
    ],
    'status_page' => [
        'enabled' => true,
    ],
];


Exact key names are frozen during implementation.


3. No Secret in the Common Config File
The common PHP configuration MUST NOT contain:
- Tor Hidden-Service private key;
- Service Identity private key in the preferred offline-signing profile;
- plaintext operator password;
- release signing key.


It may contain public keys/fingerprints and paths to signed records.


4. Publish-Only Minimal Mode
An operator who only wants to be discoverable does NOT need to run a full mirror/discovery node.


Minimal mode needs:
- valid Onion Service;
- valid signed Service Manifest;
- Well-Known Manifest route;
- Tor-capable outbound bootstrap/announcement check if automatic announcement is enabled;
- optional minimal local state/storage for status history.


Mirror, Discover and Sync remain OFF unless explicitly enabled.


5. Installation Profiles
PROFILE A — PUBLISH ONLY
Simplest mode. Be discoverable.


PROFILE B — PUBLISH + MIRROR
Be discoverable and serve index data.


PROFILE C — FULL NODE
Publish + Mirror + Discover + Sync.


PROFILE D — DATA NODE ONLY
Mirror/Discover/Sync without publishing the host Service.


Dashboard changes its wording/actions according to enabled roles.


6. Reference Routes
Human routes:
GET /onion-service-discovery/
Web Local Search.


GET /onion-service-discovery/status/
Safe read-only status for this installation/Service.


GET /onion-service-discovery/operator/
Optional privileged operator interface; disabled by default unless explicitly configured.


Machine routes remain under versioned Node API paths.


7. Public Status Page Goal
The safe read-only status page SHOULD work without login because it exposes only intentionally public or non-sensitive aggregate state.


It MUST NOT expose:
- private keys;
- filesystem paths;
- config secrets;
- exact internal error traces;
- admin tokens;
- non-public peer credentials;
- raw quarantine payloads;
- internal IP addresses;
- PHP/environment dumps.


8. Privileged Operator Interface
State-changing web administration is NOT required for the simple experience.


The preferred simple workflow is:
config file + CLI for changes;
browser status page for understanding state.


If privileged web administration is implemented:
- disabled by default;
- explicit strong authentication;
- POST for state changes;
- CSRF protection;
- short sessions;
- no auth token in URL;
- rate limiting;
- security review;
- separate audit log without secrets.


9. Dashboard Overall States
The dashboard MUST derive an overall state from evidence, not from one boolean.


Primary states:
NOT_CONFIGURED
CONFIGURED_NOT_PUBLISHED
READY_LOCALLY
CONNECTING
ANNOUNCED_PENDING_VERIFICATION
EXTERNALLY_OBSERVED
DISCOVERABLE
DISCOVERABLE_DEGRADED
WITHDRAWN
IDENTITY_CONFLICT
ERROR


10. Meaning of DISCOVERABLE
The UI MUST NOT claim global discoverability merely because local configuration is valid.


DISCOVERABLE requires evidence that at least one selected external Discovery Node:
- directly verified the Service Manifest or reports a valid direct observation; and
- includes the current Service revision in its active/searchable export or equivalent current index state.


Wording should be:
“Discoverable on 2 of 3 checked Discovery Nodes”
not:
“Visible everywhere”.


11. Status Pipeline
The dashboard shows a clear pipeline:


A. Installation
B. Configuration
C. Identity
D. Manifest
E. Onion self-verification
F. Bootstrap connectivity
G. Announcement
H. External direct observation
I. Searchable/exported state
J. Redundancy/freshness


Each step has:
PASS
PENDING
WARN
FAIL
NOT_APPLICABLE


12. Installation Check
Show:
PHP/runtime supported;
required crypto/HTTP capabilities;
selected storage backend ready;
filesystem permissions;
scheduler requirement depending enabled roles.


Operator sees:
“Installation ready”
or exact remediation.


13. Configuration Check
Validate:
canonical Onion address;
metadata bounds;
 languages/categories;
roles;
storage;
bootstrap;
routes.


Unknown/invalid config produces a precise human message.


14. Identity Check
Show:
Logical Service Identifier;
current Service Identity key/fingerprint + key epoch;
signing profile:
OFFLINE
PROTECTED_FILE
EXTERNAL/HARDWARE
or NONE/INVALID.


Never show private key.


15. Manifest Check
Show:
manifest path;
revision;
content hash;
signature valid yes/no;
publish enabled yes/no;
generated/installed state;
schema/Protocol version.


Action:
“View public Manifest”.


16. Onion Self-Verification
The Node performs the same controlled verification other Nodes would perform:
- canonical Onion;
- Tor SOCKS;
- fixed Well-Known path;
- no redirect;
- size/schema;
- signature;
- Onion binding.


Status:
“Your Manifest is reachable through your Onion Service”
or exact failure:
404
Tor unavailable
signature invalid
wrong Onion
redirect
oversized
etc.


17. Bootstrap Connectivity
Show aggregate safe state:
0 reachable bootstrap sources -> not connected;
1+ reachable -> bootstrap connected;
learned peer count;
last successful contact.


Do not equate peer reachability with discoverability.


18. Automatic Self-Announcement
When:
publish=true;
manifest is valid;
self-verification succeeds;
bootstrap.announce=true;


the reference implementation SHOULD automatically announce the canonical Onion address to a small bounded set of configured/learned Discovery Nodes.


This is candidate announcement only, not remote trusted StorageBackend write authority.


19. Candidate Announcement Payload
Keep minimal:
Protocol version;
canonical Onion address;
current Manifest hash/revision where useful;
optional Service Identity public key/fingerprint for correlation.


Remote Node MUST independently fetch and verify the Service over Tor before creating Direct Observation.


20. Announcement Security
Announcement endpoint:
- finite request size;
- strict Onion parser;
- rate limits;
- duplicate collapse;
- source quotas;
- work budget;
- no arbitrary URLs;
- no metadata trusted from announcement;
- no arbitrary peer write semantics.


21. Announcement Privacy
The Service operator is intentionally declaring a public Onion Service discoverable.


Announcement carries no user search data.


22. Announcement Retry
Retry only with bounded exponential backoff.


Reannounce:
- initial publication;
- new Manifest revision;
- explicit operator action;
- after prolonged absence of external observation according to bounded policy.


Do not continuously spam peers.


23. Announcement Dashboard
Show:
last announcement time;
number of selected Nodes attempted;
accepted/queued count;
rate-limited count;
unreachable count;
next retry if applicable.


Do not expose internal remote error bodies unescaped.


24. External Direct Observation
The operator dashboard checks selected Nodes for evidence of current Service state.


Possible status:
NO_EXTERNAL_OBSERVATION
OBSERVED_STALE_REVISION
OBSERVED_CURRENT_REVISION
CONFLICT_REPORTED


25. Searchability Check
Dashboard uses the canonical stable lookup GET /onion-service-discovery/v1/services/by-id/{logical_service_id} (and inclusion proof/snapshot evidence where available) to determine whether the operator's current revision appears in that Node's active export/searchable state. The current Onion address may be displayed/cross-checked but is not the persistent identity lookup key.


This is an operator self-check, not a user's search query.


26. Discoverability Evidence Card
Example:


DISCOVERABILITY
Current revision: 7
Self-check: PASS
Announcement: PASS
External observations: 3
Checked Discovery Nodes: 4
Searchable on current revision: 3
Last confirmed: 12 minutes ago


Overall:
DISCOVERABLE


27. Redundancy
One external Node can establish sampled discoverability.
Multiple distinct selected Node identities/source groups can improve propagation availability and censorship resilience; actual operator diversity is credited only when it is known/documented.


Dashboard MAY say:
“Good redundancy: current revision observed by 4 configured sources.”


It MUST NOT say:
“4 Nodes prove this Service is trustworthy.”


28. Propagation Progress
After first publish:
0%/spinner-style fake global percentage is forbidden.


Use discrete truthful events:
Configured
Published
Self-verified
Announced
First external observation
First external searchable confirmation
Additional sources observed


29. Time-to-Discover
Dashboard MAY measure:
time from current Manifest publication to first external observation/searchable confirmation.


This is local operational telemetry only.
Do not send it centrally.


30. One Clear Main Status
Top of page should answer:
“Can this Service currently be found through Onion Service Discovery?”


Possible human answers:
NO — Publishing is disabled.
NOT YET — Manifest is valid, waiting for external verification.
YES — Current revision is searchable on 3 checked Discovery Nodes.
DEGRADED — Previously discoverable, but checked sources are stale/unreachable.
PROBLEM — Identity conflict detected.


31. Plain-Language Remediation
Every non-green state gives next action.


Examples:
“Tor is not reachable through the configured SOCKS endpoint.”
“Your Well-Known Manifest returns 404.”
“Publishing is disabled in config.”
“No bootstrap Node is reachable.”
“Announcement was accepted; verification is still pending.”
“Checked Nodes still expose revision 6, but your current revision is 7.”


32. No False Panic
Ordinary propagation delay is not a security warning.


Use:
PENDING/INFO
not RED/CRITICAL.


33. Identity Conflict
A real fork/key/migration conflict is high severity.


Dashboard shows:
“Conflicting signed identity state detected.”
and links to technical evidence.


34. Role Overview
Display toggles as read-only status:
Publish: ON/OFF
Mirror: ON/OFF
Discover: ON/OFF
Sync: ON/OFF


Explain each in one sentence.


35. Publish Status
If publish OFF:
“Your Service is not intentionally advertised by this installation.”


If publish ON:
“Your signed public metadata is eligible for decentralized discovery.”


36. Mirror Status
Show:
records stored;
current snapshot state;
last successful index build;
storage health.


Only when role enabled.


37. Discover Status
Show:
candidate queue;
last direct verification;
rate limits/work budget health.


Only when role enabled.


38. Sync Status
Show:
active/known peers;
last successful sync;
next scheduled run;
last error summary.


Only when role enabled.


39. Storage Status
Show selected backend:
Filesystem
SQLite
PostgreSQL
MySQL/MariaDB
or other conformant adapter.


Show:
HEALTHY
READ_ONLY
DEGRADED
ERROR


No storage-engine complexity in the main card unless something needs attention.


40. Key Status
Show:
Logical Service Identifier;
current Service Identity key/fingerprint + key epoch;
signing profile;
backup last verified if operator records that locally;
Node Identity fingerprint when Node role enabled.


Never display secrets.


41. Search UI Integration
When the operator visits their own /onion-service-discovery/ Web Local Search, the interface MAY show a non-intrusive “This host” status link:
“This Onion Service: Discoverable”
leading to /status/.


This status is derived from public/safe evidence only.


42. Mobile First
/status/ must be fully usable in Tor Browser Android:
single-column;
large touch targets;
no hover requirement;
copyable Onion/fingerprint;
progress cards;
accessible text labels.


43. JavaScript
The status page SHOULD have a useful server-rendered HTML baseline.


JavaScript MAY enhance live refresh, but essential status must not require it.


44. Refresh
Safe manual:
“Check again”.


Optional background refresh:
low frequency;
no infinite polling;
stops when tab hidden where practical;
no third-party requests.


45. No External Analytics
No analytics, telemetry or third-party assets on status/operator pages.


46. Config Changes
Simple mode:
operator edits config.php and reloads.


CLI:
config:validate
status
doctor
manifest:verify
network:announce
network:discoverability-check


47. Config Reload
Reference implementation SHOULD detect config changes safely per request/process lifecycle without requiring exotic daemon restart where possible.


Invalid new config does not destroy prior signed evidence.


48. One-Command Self-Test
CLI:
onion-service-discovery status --check
or equivalent.


Performs same non-destructive checks as dashboard.


49. One-Command Announce
CLI:
onion-service-discovery network:announce


Useful for manual recovery/testing.
Automatic bounded announce remains available.


50. Discoverability Check CLI
CLI:
onion-service-discovery network:discoverability-check


Returns machine-readable evidence:
self verification;
announcement;
external observations;
searchable sources;
freshness;
conflicts.


51. Machine-Readable Status
GET/CLI local operator health MAY expose JSON equivalent for monitoring.


Public machine API must not include sensitive diagnostics.


52. Safe Public Status Fields
Allowed:
software version;
Protocol version;
roles;
public Logical Service Identifier;
current public Service Identity key/fingerprint + key epoch;
current public Manifest revision/hash;
public Onion;
aggregate peer/bootstrap health;
external observation counts;
searchability counts;
last public observation age;
storage backend type/health summary.


53. Sensitive Fields
Operator-only:
full peer list if policy chooses;
internal filesystem details;
scheduler internals;
storage paths;
rate-limit counters;
diagnostic traces.


54. First Installation Experience
If no config:
status page shows:
“Onion Service Discovery is installed but not configured.”


Provide exact file path only if page is operator-authenticated/local; public page gives generic docs instruction.


55. First Publish Experience
After operator sets publish=true:
dashboard guides:
Validate config
Generate/install signed Manifest
Self-check
Announce
Wait for external direct verification
Confirm sampled searchability


No recurring manual registration.


56. Offline Service Signing Integration
Preferred high-assurance publish workflow:
Step 1 — edit metadata;
Step 2 — generate unsigned canonical candidate;
Step 3 — sign with offline/admin SignerProvider;
Step 4 — install signed Manifest;
Step 5 — status page detects new revision;
Step 6 — self-check;
Step 7 — automatic announcement.


Web server never needs Service private key.


57. Simpler Protected-Signer Profile
If operator deliberately uses online SignerProvider:
dashboard may offer “Generate next Manifest” only after privileged authentication and security-profile disclosure.


It MUST NOT silently move an offline key onto the server.


58. Withdrawal UX
When publish changes OFF through a valid signed revision:
dashboard says:
“Withdrawn from active discovery.”
Also:
“Other independently controlled Nodes may retain historical public evidence; global deletion cannot be guaranteed. Distinct Node keys alone do not prove independent control.”


59. Migration UX
When Onion address migration occurs:
dashboard shows old -> new;
evidence levels;
which external Nodes have observed new state;
old-address status where available.


60. Update UX
Status page warns if:
running software unsupported/outdated according to trusted release metadata.


Do not perform unverified auto-update.


61. Status Semantics Must Be Testable
Each displayed state maps to:
input facts;
derivation function;
test fixture;
security meaning.


No arbitrary hand-written UI state.


62. Synthetic UX Fixtures
Required stories:
new install
publish disabled
manifest invalid
Tor offline
no bootstrap
announcement pending
first external observation
discoverable on one Node
discoverable on several Nodes
stale external revision
identity conflict
withdrawal
migration
storage degraded.


63. Plug-and-Play Packaging
Reference release SHOULD provide a ready-to-deploy archive/package with:
public entrypoint/assets;
src/vendor according to packaging policy;
config.example.php;
CLI;
schemas;
migrations/storage setup;
documentation.


Operator should not assemble source files manually.


64. Existing Onion Service Integration
Deployment guide provides minimal nginx and Apache examples.


Goal:
adding Onion Service Discovery does not require rebuilding Tor or replacing the existing website.


65. URL Namespace Isolation
All project application routes live under /onion-service-discovery/; the Protocol Manifest remains at the separate standardized path /.well-known/onion-service-discovery.json.


Avoid collisions with host application routes.


66. Failure Isolation
If Onion Service Discovery fails:
the operator's main Onion website SHOULD continue serving where practical.


Reference integration should isolate PHP routes/config so a Discovery failure does not take down unrelated site content.


67. Read-Only Degraded Mode
If storage/sync fails but safe public status/search cache exists:
show DEGRADED;
serve safe previously verified data where policy permits;
do not corrupt current state.


68. Security Defaults
- publish OFF until explicitly enabled;
- mirror/discover/sync OFF unless selected;
- privileged web admin OFF;
- public status safe/minimal;
- query logging OFF;
- no remote assets;
- no Tor private key access.


69. “Connected” Definition
Avoid vague green “Connected”.


Show separate facts:
Tor transport: reachable
Bootstrap sources: 3/4 reachable
Known peers: 12
Last sync: 8 min
Announcement: accepted by 3 Nodes
External current observations: 2
Searchable current revision: 2 checked Nodes


70. “Network Member” Definition
A Service is not assigned to a central network authority.


Preferred language:
“Participating in Onion Service Discovery”
“Connected to Discovery Nodes”
“Externally observed”
“Discoverable on checked Nodes”


Avoid:
“registered centrally”
“approved by network”
“certified member”.


71. Operator Confidence Goal
Within one page an operator should understand:
- Is the software installed correctly?
- Is publishing intentionally enabled?
- Is my Manifest cryptographically valid?
- Can my own Onion serve it correctly?
- Can I reach the Discovery network?
- Have external Nodes verified me?
- Is my current revision searchable on checked Nodes?
- Is anything degraded/conflicting?
- What do I do next?


72. Acceptance
Plug-and-play operator experience PASS requires:
- common publish-only setup through one clear config file plus signed Manifest;
- no SQL requirement;
- no browser extension;
- mobile-friendly status page;
- server-rendered baseline;
- truthful multi-stage discoverability state;
- automatic bounded self-announcement;
- external observation/searchability confirmation;
- no secret exposure;
- offline-signing workflow supported;
- no recurring registration;
- synthetic UX stories and end-to-end tests;
- fresh operator can reach DISCOVERABLE without undocumented steps


73. INDEX-INCLUSION PROOF AS THE PRIMARY DISCOVERABILITY CHECK
The dashboard MUST prefer evidence from the actual active index a Node distributes over a simple status assertion.


For the operator's own current Service state, compare the exact tuple:
- Logical Service Identifier;
current Service Identity key/fingerprint + key epoch;
- canonical Onion;
- Manifest revision;
- Manifest hash;
- Protocol version.


74. NOT TEXT SEARCH
Do not determine operator discoverability by entering the Service name into the ordinary search box.


Search ranking, filters and pagination are not evidence.


75. PER-NODE INDEX STATUS
For every checked Discovery Node, show:
- checkpoint sequence/root;
- checkpoint signature valid yes/no;
- exact current record present yes/no;
- exact revision/hash;
- inclusion proof status where supported;
- freshness.


76. HUMAN STATUS EXAMPLE
“Current revision 17 is included in the signed active index of 3 of 4 checked Discovery Nodes.”


If compact proof supported:
“Index inclusion proof verified.”


77. STALE SOURCE
If the Node includes revision 16 while the operator's current signed revision is 17:
show:
“Node has an older revision.”


It MUST NOT count toward current discoverability.


78. NODE OMISSION
If a Node omits the Service:
show:
“Not present in this Node's current index.”


Other valid Nodes remain counted.
Do not infer global absence.


79. EQUIVOCATION WARNING
If a Node signs incompatible checkpoints for the same sequence/state:
show a source-integrity warning:
“Conflicting signed index checkpoints detected for this Discovery Node.”


80. PROOF STRENGTH
Dashboard details distinguish:
- record found in signed complete snapshot;
- compact Merkle inclusion proof verified;
- Node only reports observation but current index membership not confirmed.


81. DISCOVERABLE DERIVATION UPDATE
DISCOVERABLE requires at least one checked Node where the operator locally verifies exact membership of the current tuple in the Node's active signed index state.


An API boolean “listed=true” alone is insufficient.


82. REDUNDANT DISCOVERABILITY
A separate UX label may indicate redundancy:
“Current revision confirmed in 3 independent configured sources.”


This is propagation resilience, not trust consensus.


83. EVIDENCE DOWNLOAD
Advanced details MAY allow exporting the public evidence bundle:
- Service tuple;
- signed Node checkpoint;
- index record;
- inclusion proof if present.


No secrets.


84. END-TO-END OPERATOR TEST
Fresh operator acceptance MUST prove:
publish -> external verify -> Node index generation -> signed checkpoint -> exact local membership verification -> dashboard DISCOVERABLE.




85. SYBIL-SAFE DASHBOARD WORDING
The dashboard MUST NOT translate Node count into trust.


Show:
“Current revision included on 4 distinct checked Node identities.”


Only use “independent operators” where the operator's local source configuration explicitly groups/identifies distinct operators and the basis is documented.


86. SOURCE CONCENTRATION
Status/doctor SHOULD show:
selected source groups;
distinct Node identities;
known/declared related-source grouping;
whether all sources were learned through one source.


Warn:
“Your current source set may be concentrated.”


Do not claim hidden ownership detection.


87. NO TRUST SCORE FROM CROWD SIZE
Forbidden:
“Trusted by 500 Nodes.”
“99% community verified.”
“Majority approved.”


Allowed:
“Observed by 500 distinct Node identities” in technical details, with a note that identities may be controlled by the same operator.


88. SEARCH SPAM UX
If many near-identical Services dominate a query, the local Web Client MAY collapse them under:
“Similar entries (N)”


Every distinct Service remains inspectable.
Similarity is not proof of common ownership or fraud.
