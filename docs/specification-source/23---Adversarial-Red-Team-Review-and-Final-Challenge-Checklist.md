# 23 - Adversarial Red Team Review and Final Challenge Checklist

Source: https://docs.google.com/document/d/17A6V9fEYKNRMeS7beT7a7yMt6wbpBsjIDWzWhZzAMjE/edit?usp=drivesdk

Source modified: 2026-10-11T07:13:13.126Z

---

ONION SERVICE DISCOVERY
ADVERSARIAL RED TEAM REVIEW AND FINAL CHALLENGE CHECKLIST


Purpose
This document is written from the perspective of a hostile reviewer whose goal is to disprove every important security, privacy, decentralization and production-readiness claim before release. The coding agent must treat every unanswered item as an open risk, not as a rhetorical checklist.


A. PROJECT CLAIM CHALLENGE


[ ] Can I identify any central service whose loss prevents the network from functioning?
[ ] Can I identify any maintainer-controlled key that can redefine service identity globally?
[ ] Can I make a user’s search term leave the local client?
[ ] Can I make a normal browser visit become an automatic discovery report?
[ ] Can I force the application to read or expose a Tor Hidden-Service private key?
[ ] Can I make “verified” mean more than the implementation actually proves?
[ ] Can I create a same-name fake service that the UI makes indistinguishable from a pinned identity?
[ ] Can I exploit stale data so an old but valid identity state replaces newer state?
[ ] Can I exploit peer majority/Sybil count to override cryptographic evidence?
[ ] Can I make withdrawal or offline status erase forensic continuity evidence?


B. ONION ADDRESS CHALLENGE


[ ] Does the parser validate exact Tor v3 address length/encoding?
[ ] Does it decode and validate version 3?
[ ] Does it recompute the Tor checksum?
[ ] Does it reject scheme/path/port when field semantics require hostname only?
[ ] Does canonical case handling match Tor encoding?
[ ] Can whitespace, Unicode lookalikes or percent encoding bypass checks?
[ ] Can malformed address reach network adapter before strict validation?
[ ] Can a candidate cause localhost/IP/clearnet access?
[ ] Can redirects escape to another host?


C. JSON / PARSER CHALLENGE


[ ] Duplicate keys with different values?
[ ] Duplicate key hidden deep in nested object?
[ ] Very deep nesting?
[ ] Huge strings?
[ ] Huge arrays?
[ ] Invalid UTF-8?
[ ] BOM?
[ ] Control characters?
[ ] Null where absent expected?
[ ] Scientific notation or unsafe integer?
[ ] 2^53 boundary?
[ ] Negative revision?
[ ] Fractional revision?
[ ] Unknown fields?
[ ] Same signed payload interpreted differently PHP vs TypeScript?
[ ] Property ordering edge cases?
[ ] Unicode sorting edge cases under JCS?
[ ] Can parser normalize before signature differently between implementations?


D. SIGNATURE / HASH CHALLENGE


[ ] Can signature be reused across record types?
[ ] Are domain-separation prefixes exact and tested?
[ ] Is zero-byte/length framing unambiguous?
[ ] Is signature field excluded in exactly one documented way?
[ ] Can alternate Base64 encoding validate?
[ ] Wrong key length?
[ ] Wrong signature length?
[ ] Unknown algorithm fallback?
[ ] Can hash be computed over pretty JSON by one side and canonical JSON by another?
[ ] Can compression alter content identity?
[ ] Can a stored blob change while retaining same database key?
[ ] Is every crypto test vector cross-language exact?


E. REVISION CHAIN CHALLENGE


[ ] Revision 1 genesis rules exact?
[ ] Revision 2 references exact expected prior hash?
[ ] Can revision skip numbers if not intended?
[ ] Can lower revision replace higher?
[ ] Can equal revision/different hash be silently resolved?
[ ] Can equal revision conflict be lost through SQL UPSERT?
[ ] Can timestamp choose winner incorrectly?
[ ] Can restart/storage migration forget highest revision?
[ ] Can peer omit intermediate chain and trick client?
[ ] Is partial history handled without false continuity claim?


F. KEY ROTATION CHALLENGE


[ ] New key without old-key authorization?
[ ] Rotation record replayed?
[ ] Same old state signs two new keys?
[ ] Effective revision conflict?
[ ] Old key compromised and attacker races rotation?
[ ] New-key countersignature policy consistent?
[ ] Rotation accepted before first new-key manifest validates?
[ ] DB commits key switch but not manifest due crash?
[ ] Client and node show same continuity result?
[ ] Lost old key is correctly treated as inability to prove continuity, not “recovered”?


G. ONION MIGRATION CHALLENGE


[ ] Fake service claims to be new Onion for known identity?
[ ] Migration signed by wrong Service key?
[ ] Same state migrates to two addresses?
[ ] Old Onion unreachable — does UI overclaim?
[ ] New Onion active but wrong manifest key?
[ ] Migration after rotation works in correct order?
[ ] Migration chain A->B->C preserved?
[ ] Client pin follows only valid policy?
[ ] Old address remains historical, not active target?
[ ] Peer-only migration claim cannot become locally confirmed old-endpoint evidence?


H. NODE IDENTITY / PEER CHALLENGE


[ ] Node descriptor signed?
[ ] Same descriptor revision with different hash detected?
[ ] Node can lie — is that contained?
[ ] Many Sybil nodes can manufacture trust badge?
[ ] Peer list can explode memory?
[ ] Learned peers recurse immediately?
[ ] Peer descriptor can choose proxy/path/port?
[ ] Unknown capability weakens mandatory checks?
[ ] Peer can reset cursor to overwrite newer data?
[ ] Node compromise affects Service Identity incorrectly?


I. SYNC CHALLENGE


[ ] Partial response?
[ ] Response interrupted?
[ ] Cursor advanced before DB commit?
[ ] One invalid record corrupts whole transaction unexpectedly?
[ ] Mixed-validity policy implemented exactly?
[ ] Reapply same batch idempotent?
[ ] Snapshot root mismatch?
[ ] Chunk hash mismatch?
[ ] Chunk count bomb?
[ ] Cursor expiration recovery?
[ ] Peer continuously changes state?
[ ] Equivocating snapshots?
[ ] Stale valid snapshot vs newer local history?
[ ] Peer outage leaves local search usable?


J. SSRF / NETWORK CHALLENGE


[ ] Any code path accepts arbitrary URL?
[ ] Any metadata field becomes cURL URL directly?
[ ] DNS resolution local rather than Tor?
[ ] Tor proxy failure falls back direct?
[ ] HTTP proxy env var can override intended transport?
[ ] Redirect follows clearnet?
[ ] Redirect follows different Onion?
[ ] Request can use file://, gopher:// or custom schemes?
[ ] Arbitrary port?
[ ] Credentials in URL?
[ ] Oversized response before limit enforcement?
[ ] Compression bomb?
[ ] Timeout ineffective?
[ ] Unlimited parallel requests?
[ ] Network call recursively triggered by response?


K. ABUSE / DOS CHALLENGE


[ ] Million duplicate candidates?
[ ] Million distinct syntactically valid candidates?
[ ] Candidate source quota bypass through many Sybil peers?
[ ] Queue “unlimited” configuration?
[ ] Retry loop?
[ ] Jitter absent causing herd?
[ ] Signature verification flood?
[ ] Database fills with quarantine?
[ ] Snapshot cache fills disk?
[ ] Node advertises max+1 peers?
[ ] Related links max+1?
[ ] Keyword stuffing dominates search?
[ ] Many same-name services freeze UI?
[ ] Disk-full behavior?
[ ] DB lock contention?
[ ] Scheduler overlap?
[ ] Memory pressure when parsing snapshot?
[ ] Existing verified data survives overload?


L. DATABASE CHALLENGE


[ ] INSERT OR REPLACE used on identity evidence?
[ ] Foreign keys actually enabled?
[ ] Transaction boundaries correct?
[ ] Corrupt DB detected?
[ ] Restore preserves hashes/current view?
[ ] WAL backup procedure correct?
[ ] Migration file changed after release?
[ ] Rollback claims realistic?
[ ] Current view can be recomputed from evidence?
[ ] Deleting cache cannot delete pins/history?
[ ] Same hash/different content impossible or detected?
[ ] SQL identifiers dynamically built from user data?


M. PHP / WEB CHALLENGE


[ ] Secret file reachable under public webroot?
[ ] .env reachable?
[ ] SQLite reachable?
[ ] backup/log reachable?
[ ] debug enabled?
[ ] stack trace?
[ ] remote admin endpoint?
[ ] sessions/cookies unintentionally created?
[ ] CORS wildcard with credentials?
[ ] XSS via service description?
[ ] HTML interpreted?
[ ] path traversal in chunk endpoint?
[ ] host-header confusion?
[ ] arbitrary X-Forwarded trust?
[ ] PHP version unsupported?
[ ] missing sodium leads unsafe fallback?


N. WEB LOCAL SEARCH CHALLENGE


[ ] Broad host permission unjustified?
[ ] Permissions differ between source and built package?
[ ] Content script can see arbitrary browsing?
[ ] Does the Web Client or server fallback collect history, query, pins or clicks beyond the explicit design?
[ ] Remote script/font/image?
[ ] eval/unsafe-eval?
[ ] Arbitrary fetch via message passing?
[ ] Search causes network?
[ ] Click causes telemetry?
[ ] Favorite/pin causes network?
[ ] Remote moderation lookup per result?
[ ] Result loads service icon remotely?
[ ] javascript:/data:/file URL navigation?
[ ] Malicious name becomes HTML?
[ ] Full Onion hidden?
[ ] Permission added in update without review?
[ ] Storage migration loses pins?
[ ] Cache reset loses private identity pins?
[ ] Node outage deletes index?
[ ] Query appears in diagnostics/support bundle?


O. WEB ORIGIN / QUERY PRIVACY CHALLENGE


[ ] Can a malicious serving Node alter the Web Client and exfiltrate queries or falsify UI state?
[ ] Does it inject DOM?
[ ] Does it expose web-accessible resources?
[ ] Does user-specific config alter website-visible behavior?
[ ] Unique install ID?
[ ] Custom network header per user?
[ ] Remote analytics?
[ ] Project documentation accurately repeats Tor Project’s warning on extra add-ons?
[ ] Claims limited to local-search privacy, not global anonymity?


P. SEARCH / ANTI-FRAUD CHALLENGE


[ ] Same display name different key?
[ ] Unicode homograph?
[ ] Bidi override?
[ ] Invisible characters?
[ ] Similar name to pinned service?
[ ] Ranking conflates relevance with trust?
[ ] Node count boosts trust?
[ ] Popularity badge looks like verification?
[ ] “Official” shown without external proof?
[ ] Technical “verified” label explained?
[ ] Moderation changes identity truth?
[ ] Service owner can inject fake green badge text into description?
[ ] Name truncated so two addresses look same?


Q. PRIVACY CHALLENGE


[ ] Search query anywhere in logs?
[ ] Search query in URL/header/body?
[ ] Query changes network destination/timing?
[ ] Browsing history read?
[ ] Click event?
[ ] Persistent user ID?
[ ] Account/login?
[ ] Cookies?
[ ] Third-party analytics?
[ ] Crash report?
[ ] Remote font?
[ ] Pin list uploaded?
[ ] Favorite list uploaded?
[ ] Diagnostics include custom source/pins unnecessarily?
[ ] Node logs retain unnecessary identifiers indefinitely?
[ ] Withdrawal promises global deletion falsely?


R. MODERATION CHALLENGE


[ ] One central mandatory blacklist?
[ ] Fake reports rewrite signature status?
[ ] Moderation source has provenance?
[ ] User can disable/select source?
[ ] Moderation list update leaks searched service?
[ ] Expired report retained as current?
[ ] Report category translated into “identity invalid” incorrectly?


S. SUPPLY CHAIN CHALLENGE


[ ] Floating dependencies?
[ ] Floating CI actions?
[ ] Unknown Composer plugin?
[ ] npm postinstall surprises?
[ ] Secret in repo/history?
[ ] SBOM missing transitive dependencies?
[ ] Release built from dirty tree?
[ ] Tag can be moved?
[ ] Artifact replaced under same version?
[ ] Served static asset manifest/CSP/network destinations differ from reviewed release?
[ ] Source maps leak local secrets/paths?
[ ] Unexplained binary blob?
[ ] Release lacks hash/provenance?
[ ] CI PR can access release secrets?
[ ] AI-generated code bypassed review?


T. OPERATIONS CHALLENGE


[ ] Clean install docs actually work?
[ ] Non-root?
[ ] Tor SOCKS only local?
[ ] Outbound direct network restricted where feasible?
[ ] Backup tested?
[ ] Restore tested?
[ ] Key backup tested?
[ ] Upgrade tested?
[ ] Rollback tested?
[ ] Disk-full runbook?
[ ] DB corruption runbook?
[ ] Key compromise runbook?
[ ] Node key compromise separated from service key?
[ ] Seed outage?
[ ] Mass spam mode?
[ ] Runtime EOL tracked?


U. GOVERNANCE CHALLENGE


[ ] Can maintainer redefine Protocol without proposal?
[ ] Accepted ADR rewritten?
[ ] Security exception no expiry?
[ ] AI marks human review PASS?
[ ] “external audit” claimed without evidence?
[ ] Seed inclusion opaque?
[ ] Paid placement affects trust?
[ ] License silently chosen?
[ ] Protocol behavior defined only by PHP implementation?
[ ] Old clients’ compatibility ignored?
[ ] Breaking change kept version 1?


V. DOCUMENTATION CHALLENGE


[ ] Architecture diagram matches code?
[ ] Trust boundaries current?
[ ] Every endpoint documented?
[ ] Every config setting documented?
[ ] Every permission documented?
[ ] Every network destination documented?
[ ] Every persistent data class in inventory?
[ ] Known limitation hidden?
[ ] Security claims stronger than tests?
[ ] README says “safe/trusted/unhackable”?
[ ] Version support stale?
[ ] External references stale?


W. RELEASE CHALLENGE


[ ] Gate matrix complete?
[ ] UNKNOWN counted as fail?
[ ] SKIPPED justified?
[ ] Critical/High open?
[ ] Protocol vectors pass both languages?
[ ] Permission review?
[ ] Query privacy packet/instrumentation test?
[ ] Direct fallback test?
[ ] Clean build?
[ ] Independent rebuild?
[ ] SBOM?
[ ] Checksums?
[ ] provenance?
[ ] backup/restore evidence?
[ ] rollback evidence?
[ ] incident runbooks?
[ ] human crypto review?
[ ] external audit correctly marked performed/not performed?


X. ADVERSARIAL SCENARIOS


Scenario 1 — Clone Phishing
Create B with same name/description as pinned A.
Expected:
different identity warning; no pin replacement.


Scenario 2 — Replay
Feed valid old A revision after newer.
Expected:
stale; no current-state downgrade.


Scenario 3 — Fork
Two valid same-revision manifests.
Expected:
FORKED; evidence preserved.


Scenario 4 — Migration Hijack
B claims A moved to B with invalid/no Service signature.
Expected:
identity changed/reject migration.


Scenario 5 — Sybil Consensus
100 fake Nodes claim one mapping.
Expected:
no cryptographic identity override.


Scenario 6 — Tor Failure
SOCKS down, Internet up.
Expected:
verification/sync fail; no direct fallback.


Scenario 7 — Query Privacy
Search a rare string while capturing every browser/network request made by the Web Local Search page.
Expected:
rare string absent; no query-triggered destination change.


Scenario 8 — Candidate Flood
Large synthetic candidate set.
Expected:
admission quotas; bounded queue/work.


Scenario 9 — Malicious Peer Snapshot
Correct envelope, corrupted chunk.
Expected:
hash failure, no commit/cursor advance.


Scenario 10 — DB Crash
Crash between manifest stage and current-view update.
Expected:
transaction recovery; no half-verified state.


Scenario 11 — Web Client Bundle Tampering
New package requests extra host permission.
Expected:
release gate blocks without review.


Scenario 12 — Compromised Node Key
Attacker signs observations.
Expected:
cannot forge Service Manifest/continuity; source can be distrusted/rotated.


Y. RED TEAM EVIDENCE
For every scenario:
test ID
environment
input fixture
expected result
actual result
logs/event IDs
resource measurements
fix reference if failed.


Z. FINAL ADVERSARIAL QUESTION
Before declaring Release Candidate ask:
“If I were trying to discredit this project in a professional security review, which undocumented assumption, unbounded input, misleading security label, centralized dependency, stale platform assumption or unreproduced claim would I attack first?”


The top answers become blocking issues or explicitly accepted limitations before release.


Final Rule
A reviewer saying “I found no obvious issue” is insufficient. Every major claim must survive an explicit attempt to falsify it with reproducible evidence.
