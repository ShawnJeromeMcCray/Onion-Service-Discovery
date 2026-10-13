# 09 - Implementation Backlog and Milestone Checklist

Source: https://docs.google.com/document/d/1Hwzdbl4RUEkUJkTDqkc61nHu-Jjnux5vMJYvGoNhVR0/edit?usp=drivesdk

Source modified: 2026-10-12T19:27:01.953Z

---

ONION SERVICE DISCOVERY
IMPLEMENTATION BACKLOG UND MILESTONE CHECKLIST


Verwendung
Dieses Dokument ist die operative Arbeitsliste für den Coding-Agenten. Aufgaben werden nicht nur als “done” markiert, sondern erhalten Evidence: Commit/Datei/Test/Report. Ein Milestone ist erst abgeschlossen, wenn alle Exit Criteria erfüllt sind.




NORMATIVE EXECUTION ORDER / DEPENDENCY RULE
Milestone numbers are stable identifiers for traceability. They are NOT a chronological execution order.


The Coding Agent MUST execute by prerequisites:
- M0 Specification Ingestion is always first and uses the current Workspace Index.
- M1 Repository Foundation and M2 ADR Foundation precede security-critical implementation.
- M76 Secure Coding Foundation precedes application feature code that handles untrusted input.
- M75 Protocol Category Registry v1 precedes freezing the Manifest category schema/vectors in M3/M4.
- M57 StorageBackend Abstraction precedes M8 storage repositories/adapters.
- M62 High-Assurance Key Custody/SignerProvider precedes production Service signing paths in M6/M10.
- Logical Service Identifier D-051 and Signed Checkpoint Chain D-052 must be resolved before Protocol v1 Freeze and before final pin/index/checkpoint semantics are claimed conformant.
- M59/M61 govern the WEB-SEPARATED-V1 profile and must pass before that profile is called Production-supported.
- M68/M69 must pass before the dashboard may derive green DISCOVERABLE from signed index evidence.
- M71/M72 must pass before peer/source counts or source admission can be production-enabled.
- M63 Secure Release Trust must pass before Production Stable release publication.
- M65 Final Pre-Implementation Closure is a consistency/freeze gate, not a reason to halt all non-conflicting repository work. While it is REOPENED, unaffected modules may proceed; Protocol Freeze and Production Stable claims are blocked.


If a milestone text conflicts with a newer binding architecture/security standard, the milestone is corrected; its numeric ID never grants higher authority.




MILESTONE 0 — SPECIFICATION INGESTION
[ ] Alle Drive-Spezifikationen gelesen.
[ ] Specification Understanding erstellt.
[ ] Konflikte/Unklarheiten inventarisiert.
[ ] Security Invariants extrahiert.
[ ] Non-goals extrahiert.
[ ] Release Gates extrahiert.
[ ] initiale Traceability Matrix erzeugt.
Exit: keine sicherheitskritische unbekannte Grundannahme ohne ADR.


MILESTONE 1 — REPOSITORY FOUNDATION
[ ] Monorepo-Struktur.
[ ] README.
[ ] LICENSE-Entscheidung dokumentiert.
[ ] SECURITY.
[ ] PRIVACY.
[ ] CONTRIBUTING.
[ ] CHANGELOG.
[ ] ADR-Template.
[ ] Protocol Change Proposal Template.
[ ] Security Exception Template.
[ ] Coding Standards.
[ ] Editor/Formatter-Konfiguration.
[ ] CI skeleton.
[ ] dependency lockfiles.
[ ] secret scan.
[ ] static analysis skeleton.
Exit: Clean checkout kann Toolchain installieren und leere Tests/Checks reproduzierbar ausführen.


MILESTONE 2 — ADR FOUNDATION
[ ] ADR Runtime/Versions.
[ ] ADR Canonical JSON.
[ ] ADR Hash.
[ ] ADR Signature Context.
[ ] ADR Service Key Lifecycle.
[ ] ADR Node Key Lifecycle.
[ ] ADR Duplicate JSON Key Policy.
[ ] ADR Unknown Fields/Forward Compatibility.
[ ] ADR Tor Transport.
[ ] ADR StorageBackend contract / FilesystemStore / SQLiteStore / backend selection.
[ ] ADR Web Local Search client storage/cache/origin trust.
[ ] ADR Sync Model.
[ ] ADR Search.
[ ] ADR Dependency Policy.
Exit: Protocol-critical Entscheidungen nicht mehr implizit.


MILESTONE 3 — PROTOCOL SCHEMAS
[ ] Manifest Schema.
[ ] Key Rotation Schema.
[ ] Onion Migration Schema.
[ ] Node Descriptor Schema.
[ ] Direct Observation Schema.
[ ] Peer Assertion Schema.
[ ] Candidate Schema intern dokumentiert.
[ ] Sync State Schema.
[ ] Delta/Snapshot Envelope Schema.
[ ] Moderation Schema, falls v1.
[ ] Limits Table.
[ ] Error Code Registry.
Exit: jedes Wire Object besitzt normative Beschreibung + Schema.


MILESTONE 4 — TEST VECTORS
[ ] Genesis valid.
[ ] Revision chain valid.
[ ] stale revision.
[ ] fork.
[ ] invalid signature.
[ ] onion mismatch.
[ ] invalid schema.
[ ] boundary sizes.
[ ] key rotation valid/invalid.
[ ] migration valid/invalid.
[ ] node descriptor valid/invalid.
[ ] observation valid/invalid.
[ ] canonicalization vectors.
[ ] Unicode cases.
[ ] duplicate key cases.
Exit: Testvektoren sprachunabhängig und versioniert.


MILESTONE 5 — PHP DOMAIN CORE
[ ] OnionAddress value object.
[ ] Key types.
[ ] Hash type.
[ ] Revision type.
[ ] Manifest domain.
[ ] Rotation domain.
[ ] Migration domain.
[ ] Node Descriptor domain.
[ ] Observation domain.
[ ] Candidate state.
[ ] Service state.
[ ] typed errors/results.
[ ] unit tests.
Exit: kein Netzwerk, aber alle Protokollobjekte korrekt modelliert.


MILESTONE 6 — PHP CANONICALIZATION AND CRYPTO
[ ] canonicalizer adapter.
[ ] hash service.
[ ] service signer/verifier.
[ ] node signer/verifier.
[ ] rotation verifier.
[ ] migration verifier.
[ ] known-answer tests.
[ ] cross-language vector compatibility prepared.
Exit: PHP produziert exakt spezifizierte Bytes/Hashes/Signaturen.


MILESTONE 7 — PHP VALIDATION
[ ] JSON byte limit.
[ ] encoding validation.
[ ] duplicate-key handling.
[ ] schema validation.
[ ] semantic validation.
[ ] unknown field behavior.
[ ] bounded arrays/strings.
[ ] failure mapping.
[ ] fuzz target.
Exit: keine Netzwerkstruktur gelangt ungeprüft in Domain.


MILESTONE 8 — PHP STORAGE DOMAIN / REPOSITORIES (requires M57)
[ ] backend-neutral storage migration/version framework.
[ ] storage schema/version tracking; SQL schema_migrations only inside SQL adapters.
[ ] identity repository.
[ ] revision repository.
[ ] manifest repository.
[ ] rotation repository.
[ ] migration repository.
[ ] observation repository.
[ ] peer assertion repository.
[ ] node repository.
[ ] sync state repository.
[ ] candidate repository.
[ ] quarantine repository.
[ ] StorageBackend atomic commit coordinator/contract.
[ ] backend-neutral invariants plus adapter-specific indexes/constraints.
[ ] recovery tests.
Exit: Identity-Konflikte können nicht durch Upsert verschwinden.


MILESTONE 9 — LOCAL OPERATOR CLI
[ ] config validate.
[ ] status.
[ ] key generate service.
[ ] key generate node.
[ ] manifest generate.
[ ] manifest verify.
[ ] storage:migrate.
[ ] storage:verify.
[ ] peer list/add/remove local.
[ ] quarantine list.
[ ] snapshot build optional.
Exit: lokale Einrichtung ohne Web-Admin möglich.


MILESTONE 10 — MANIFEST PUBLISHING
[ ] lokale Service-Metadatenkonfiguration.
[ ] revision manager.
[ ] previous hash.
[ ] atomic manifest write.
[ ] Well-Known deployment docs.
[ ] publish_service=false behavior.
Exit: Betreiber kann einmal konfigurieren und danach automatisch gültige Revisionen erzeugen.


MILESTONE 11 — TOR TRANSPORT
[ ] target validator.
[ ] single transport adapter.
[ ] proxy configuration.
[ ] remote hostname resolution behavior verified.
[ ] timeout controls.
[ ] max bytes.
[ ] redirect policy.
[ ] typed transport errors.
[ ] concurrency limits.
[ ] tests prove no alternate application path bypasses adapter.
Exit: alle Onion-Requests laufen kontrolliert durch einen auditierbaren Pfad.


MILESTONE 12 — DIRECT VERIFICATION
[ ] candidate input.
[ ] fetch manifest.
[ ] validate.
[ ] bind onion.
[ ] signature.
[ ] revision/history.
[ ] rotation/migration.
[ ] transactional store.
[ ] direct observation.
[ ] queue result.
[ ] complete negative matrix.
Exit: VERIFIED_CURRENT nur nach vollständiger Pipeline.


MILESTONE 13 — CANDIDATE SCHEDULER
[ ] queue max.
[ ] source quotas.
[ ] dedup.
[ ] priority.
[ ] attempts.
[ ] exponential backoff.
[ ] jitter.
[ ] concurrency.
[ ] work budget.
[ ] dead/quarantine states.
Exit: große Candidate-Flut bleibt bounded.


MILESTONE 14 — NODE IDENTITY
[ ] node key management.
[ ] node descriptor.
[ ] descriptor revision.
[ ] node signature.
[ ] capability list.
[ ] node key rotation policy.
Exit: Nodes besitzen separate, prüfbare Identität.


MILESTONE 15 — PUBLIC READ API
[ ] API spec.
[ ] /node.
[ ] /state.
[ ] /peers.
[ ] /services/{onion}.
[ ] /changes or equivalent.
[ ] snapshot/chunk endpoints if used.
[ ] pagination.
[ ] response limits.
[ ] stable error envelope.
[ ] no remote admin.
[ ] contract tests.
Exit: anderer Node kann sicher lesen, aber nicht remote administrieren.


MILESTONE 16 — PEER MANAGEMENT
[ ] built-in seeds.
[ ] manual peers.
[ ] learned peers.
[ ] source provenance.
[ ] health state.
[ ] backoff.
[ ] bounded list.
[ ] selection policy.
[ ] no count-as-trust scoring.
Exit: Seed-Ausfall ist tolerierbar, wenn andere bekannte Peers existieren.


MILESTONE 17 — SYNC ENGINE
[ ] state comparison.
[ ] delta/snapshot request.
[ ] response staging.
[ ] schema/crypto verification.
[ ] conflict handling.
[ ] transaction.
[ ] cursor after commit.
[ ] retry/backoff.
[ ] peer budgets.
[ ] multi-node E2E.
Exit: Eventual Consistency ohne unsafe overwrite.


MILESTONE 18 — DISCOVERY SOURCES
[ ] peer candidate ingestion.
[ ] related service candidate ingestion.
[ ] manual announce path, falls v1.
[ ] optional crawler interface getrennt.
[ ] provenance.
[ ] limits.
Exit: neue Services können automatisch propagieren, bleiben aber bis Direct Verification Candidates.


MILESTONE 19 — SNAPSHOT/DELTA DISTRIBUTION
[ ] deterministic record ordering.
[ ] snapshot descriptor.
[ ] content hashes.
[ ] chunks/deltas.
[ ] size limits.
[ ] cache semantics.
[ ] incremental client update.
Exit: Client muss nicht permanent vollständigen Index neu laden.


MILESTONE 20 — WEB LOCAL SEARCH FOUNDATION
[ ] TypeScript strict.
[ ] static HTML/CSS/JS bundle.
[ ] no extension manifest.
[ ] no browser-extension permission model; Web origin/CSP/network capability surface inventoried and reviewed.
[ ] strict CSP.
[ ] local resources only.
[ ] no remote runtime dependencies.
[ ] reproducible build.
[ ] asset manifest/hashes.
[ ] lint/typecheck/test harness.
[ ] dependency inventory.
Exit: statischer Web Local Search Client reproduzierbar buildbar, direkt vom Node auslieferbar, keine Extension erforderlich.


MILESTONE 21 — CLIENT PROTOCOL LIBRARY
[ ] same schemas/test vectors.
[ ] canonicalization.
[ ] hash/signature verify.
[ ] identity/revision logic.
[ ] migration/rotation.
[ ] conformance green.
Exit: PHP/TS kompatibel.


MILESTONE 22 — WEB CLIENT STORAGE/CACHE
[ ] In-Memory-Index baseline.
[ ] optional origin-local IndexedDB/Cache Storage.
[ ] client remains functional when storage unavailable.
[ ] cache schema/version if persistence used.
[ ] cache rebuild.
[ ] corruption/clear behavior.
[ ] origin-local pins optional.
[ ] no unique installation identifier.
Exit: lokale Suche funktioniert ohne Persistenz; Browser-Speicher ist nur Optimierung.


MILESTONE 23 — SAME-ORIGIN INDEX LOADING
[ ] selected Node serves Web Client and index.
[ ] snapshot descriptor.
[ ] bounded chunks.
[ ] progressive loading.
[ ] no broad CORS requirement.
[ ] Node/index freshness visible.
[ ] user can manually switch to another independent Node.
[ ] no client seed pool required for baseline.
Exit: ein einzelner besuchter Node liefert eine vollständige generische Suchbasis ohne queryabhängige Fetches.


MILESTONE 24 — CLIENT UPDATE PIPELINE
[ ] fetch independent of search query.
[ ] verify records.
[ ] verify chunk/hash.
[ ] staging.
[ ] conflict preservation.
[ ] progressive in-memory apply.
[ ] optional cache apply.
[ ] diagnostics.
[ ] storage unavailable path.
Exit: Querytext beeinflusst keine Requestpayload, Destination oder den generischen Indexdownload.


MILESTONE 25 — LOCAL SEARCH AND SERVER FALLBACK
Local Search:
[ ] tokenizer/query normalization.
[ ] fields.
[ ] relevance ranking.
[ ] filters.
[ ] deterministic tests.
[ ] performance.
[ ] no remote dependency during search.
[ ] network instrumentation proves zero query traffic.


No-JavaScript fallback:
[ ] POST form.
[ ] explicit privacy disclosure.
[ ] no query URL.
[ ] no query logging.
[ ] no query-triggered remote work.
[ ] bounded results/work.
[ ] no-store.
[ ] ranking parity fixtures.


Exit: mit geladenem Index lokal suchbar; ohne JavaScript steht optional ein klar gekennzeichneter serverseitiger Fallback zur Verfügung.
MILESTONE 26 — TRUST AND IDENTITY UX
[ ] full onion.
[ ] fingerprint.
[ ] direct verification label.
[ ] continuity label.
[ ] stale label.
[ ] migration.
[ ] fork.
[ ] identity changed.
[ ] explanations.
[ ] no “safe/trusted” shortcuts.
Exit: Security Review der Copy abgeschlossen.


MILESTONE 27 — PINNING
[ ] explicit pin.
[ ] persist.
[ ] mismatch warning.
[ ] migration interaction.
[ ] reset/cache rebuild preserves pins.
[ ] export/import optional.
Exit: bekannte Identität kann nicht durch Peermehrheit still ersetzt werden.


MILESTONE 28 — PRIVACY VALIDATION
[ ] network instrumentation.
[ ] query privacy tests.
[ ] browsing history not reported.
[ ] no third-party analytics.
[ ] data flow doc.
[ ] privacy statement.
Exit: alle Datenflüsse dokumentiert und getestet.


MILESTONE 29 — MODERATION BOUNDARY
[ ] identity vs moderation model.
[ ] optional source provenance.
[ ] local source selection.
[ ] UI separation.
[ ] tests.
Exit: Moderation kann kryptografische Identität nicht überschreiben.


MILESTONE 30 — SECURITY HARDENING
[ ] fuzz campaigns.
[ ] resource stress.
[ ] malformed corpora.
[ ] conflict/fork stress.
[ ] queue flood simulation.
[ ] dependency review.
[ ] secrets review.
[ ] Web Client origin/CSP/network-destination/browser-capability review.
[ ] endpoint inventory.
[ ] network destination inventory.
Exit: Findings triagiert, Critical/High geschlossen oder menschlich akzeptiert.


MILESTONE 31 — OPERATIONS
[ ] install docs.
[ ] nginx example.
[ ] Apache example.
[ ] scheduler example.
[ ] hardening.
[ ] backup.
[ ] restore.
[ ] upgrade.
[ ] rollback.
[ ] key rotation.
[ ] health/monitoring.
[ ] troubleshooting.
Exit: Clean-room operator kann System aufsetzen.


MILESTONE 32 — SUPPLY CHAIN
[ ] SBOM server.
[ ] SBOM client.
[ ] checksums.
[ ] dependency audit.
[ ] SAST.
[ ] secret scan.
[ ] license scan.
[ ] release provenance approach.
Exit: Release Inputs/Outputs nachvollziehbar.


MILESTONE 33 — INCIDENT READINESS
[ ] service key incident.
[ ] node key incident.
[ ] protocol incident.
[ ] client privacy incident.
[ ] supply chain incident.
[ ] data corruption.
[ ] seed outage.
[ ] mass spam.
Exit: Runbooks reviewed.


MILESTONE 34 — PERFORMANCE
[ ] realistic small/medium/large fixtures.
[ ] Node StorageBackend benchmarks.
[ ] sync delta.
[ ] verification budget.
[ ] client import.
[ ] search P50/P95.
[ ] memory/storage.
Exit: dokumentierte Budgets eingehalten oder begründet.


MILESTONE 35 — RELEASE CANDIDATE
[ ] feature freeze.
[ ] full CI.
[ ] full conformance.
[ ] 3-node E2E.
[ ] client E2E.
[ ] upgrade.
[ ] backup/restore.
[ ] rollback.
[ ] clean install.
[ ] fuzz report.
[ ] performance report.
[ ] gate matrix draft.
Exit: nur Release-Blocker offen.


MILESTONE 36 — HUMAN SECURITY REVIEW
[ ] protocol/crypto.
[ ] Tor transport.
[ ] CSP/origin trust/server-search privacy review.
[ ] privacy.
[ ] trust UX.
[ ] security exceptions.
[ ] release artifacts.
Exit: dokumentierte Approvals oder offene Blocker.


MILESTONE 37 — PRODUCTION RELEASE
[ ] version/tag.
[ ] changelog.
[ ] release notes.
[ ] checksums.
[ ] SBOM.
[ ] packages.
[ ] install/upgrade docs.
[ ] known issues.
[ ] gate matrix PASS.
[ ] release evidence archived.


MILESTONE 38 — POST-RELEASE
[ ] verify published artifacts.
[ ] clean install from released artifacts.
[ ] monitor issue/security intake.
[ ] patch policy.
[ ] next protocol changes only via proposal.


PER-MILESTONE REPORT TEMPLATE
Milestone:
Status:
Deliverables:
Changed files/modules:
Tests executed:
Test results:
Security impact:
Privacy impact:
Performance impact:
Documentation updated:
Open risks:
Security exceptions:
Human review required:
Evidence links:
Next milestone:


GLOBAL BACKLOG RULES
- Kein Milestone überspringt normative Abhängigkeiten.
- Parallelisierung nur bei klar unabhängigen Bereichen.
- Ein “optional” Feature darf keine Pflichtinvariante ersetzen.
- Alle Security Bugs erhalten Regression Tests.
- Alle Wire-Format-Änderungen erhalten Testvektoren.
- Alle DB- und persistenten Web-Client-Storage-Änderungen erhalten Migration/Failure Tests; Browser-Persistenz bleibt optional.
- Alle neuen Web Client network destinations, remote assets, CSP weakenings, service workers or server-query data flows receive review.
- Alle neuen Netzwerkziele erhalten Threat-Model-Update.
- Alle neuen Dependencies erhalten dokumentierte Prüfung


AUDIT-GRADE EXPANSION MILESTONES


MILESTONE 39 — STANDARDS REFRESH
[ ] External-reference register created.
[ ] Tor v3 address spec checked.
[ ] Tor SOCKS/isolation spec checked.
[ ] Tor Browser desktop/Android JavaScript and storage behavior checked.
[ ] Current Tor Browser desktop/Android origin/CORS/CSP/storage/security-level behavior checked.
[ ] PHP support window checked.
[ ] OWASP ASVS stable baseline recorded.
[ ] SLSA current baseline recorded.
[ ] CycloneDX version/tool recorded.
Exit: no time-sensitive external assumption exists only in model memory.


MILESTONE 40 — PROTOCOL PRECISION FREEZE
[ ] limits.json.
[ ] exact OnionAddress vectors.
[ ] key/signature encodings.
[ ] domain separation.
[ ] manifest hash.
[ ] integer limits.
[ ] null/absent rule.
[ ] unknown-field rule.
[ ] exact state machines.
[ ] API schemas.
[ ] chunk exact bytes.
[ ] cursor behavior.
Exit: two independent implementers can create byte-compatible Protocol v1 without asking the original author.


MILESTONE 41 — PRIVACY AND DATA GOVERNANCE
[ ] data inventory.
[ ] retention matrix.
[ ] log inventory.
[ ] network-flow diagram.
[ ] diagnostics redaction tests.
[ ] query/click/pin network instrumentation tests.
[ ] withdrawal/decentralized-retention wording.
Exit: every stored/transmitted field has purpose and retention.


MILESTONE 42 — HARDENING AND WEB CLIENT BOUNDARY REVIEW
[ ] PHP hardening matrix.
[ ] filesystem permission tests.
[ ] webroot secret exposure tests.
[ ] outbound/Tor-only enforcement review.
[ ] Web Client origin/CSP/network-destination/storage/capability inventory.
[ ] Web Client origin/CSP/network/capability diff gate.
[ ] fingerprinting review.
[ ] no remote resource tests.
Exit: deployment/browser defaults have explicit security rationale and evidence.


MILESTONE 43 — GOVERNANCE AND SUPPLY CHAIN
[ ] governance policy transferred into repo.
[ ] ADR/protocol proposal registers.
[ ] security exception expiry check.
[ ] seed governance.
[ ] SBOM server/client.
[ ] SLSA-aligned provenance where supported.
[ ] independent rebuild.
[ ] artifact checksum/signing policy.
[ ] dependency/license inventory.
Exit: release is attributable to reviewed source and process.


MILESTONE 44 — FINAL ADVERSARIAL ACCEPTANCE
[ ] full Red Team checklist.
[ ] 12+ defined adversarial scenarios executed.
[ ] audit map.
[ ] ASVS applicability matrix.
[ ] audit package.
[ ] all Critical/High internal findings closed.
[ ] EXTERNAL_AUDIT_NOT_PERFORMED remains explicit unless real external audit occurred.
[ ] final gate matrix.
Exit: release candidate has evidence against its own strongest claims, not merely passing happy-path tests.




MILESTONE 45 — FORMAL MODEL
[ ] TLA+/PlusCal or approved equivalent.
[ ] revision invariants.
[ ] fork invariants.
[ ] key rotation invariants.
[ ] migration invariants.
[ ] candidate state.
[ ] sync cursor/commit.
[ ] pinning.
[ ] crash model.
[ ] traceability to executable tests.
Exit: no unresolved safety counterexample and Human Protocol/Security review.


MILESTONE 46 — INDEPENDENT CONFORMANCE SUITE
[ ] CORE/NODE/CLIENT/FULL profiles.
[ ] machine-readable vector manifest.
[ ] exact-byte canonicalization/crypto.
[ ] mock malicious Node/Service.
[ ] black-box API harness.
[ ] reproducible reports.
[ ] PHP pass.
[ ] TypeScript pass.
[ ] third-language core pass.
Exit: Protocol behavior no longer depends on reference-code interpretation.


MILESTONE 47 — REPRODUCIBLE TEST NETWORK
[ ] LAB-FAST.
[ ] LAB-INTEGRATION.
[ ] LAB-TOR.
[ ] LAB-STRESS.
[ ] LAB-CHAOS.
[ ] deterministic topologies.
[ ] fault injector.
[ ] resource reports.
[ ] no accidental public-network stress.
Exit: release scenarios reproduce with one documented command.


MILESTONE 48 — INTERNET STANDARDS AND PUBLICATION
[ ] RFC 8615 Well-Known registration draft.
[ ] final suffix decision.
[ ] JSON Schema Draft 2020-12 IDs/bundle.
[ ] RFC 9457 error decision.
[ ] error/capability/record registries.
[ ] RFC-style Protocol Handbook generated.
[ ] IANA Considerations reviewed.
Exit: public specification is standards-ready without false standards claims.


MILESTONE 49 — ACCESSIBILITY AND SECURITY UX
[ ] WCAG 2.2 AA applicability.
[ ] keyboard/screen-reader/zoom review.
[ ] security-copy registry.
[ ] pin mismatch comprehension.
[ ] migration comprehension.
[ ] fork comprehension.
[ ] stale/offline comprehension.
[ ] Unicode/confusable review.
[ ] no color-only warning.
Exit: Human UX/Security review with evidence.


MILESTONE 50 — OPERATOR AND DEVELOPER EXPERIENCE
[ ] CLI command contract.
[ ] doctor.
[ ] security:check.
[ ] machine-readable health.
[ ] support bundle.
[ ] secret redaction tests.
[ ] fresh operator walkthrough.
[ ] fresh contributor walkthrough.
[ ] troubleshooting trees.
Exit: no undocumented manual state editing required.


MILESTONE 51 — CAPACITY AND PERFORMANCE
[ ] Tier S.
[ ] Tier M.
[ ] Tier L.
[ ] Tier XL stress model.
[ ] low-end client.
[ ] search P50/P95.
[ ] sync/storage/bandwidth.
[ ] Tor-aware concurrency.
[ ] supported-scale statement per Production-supported StorageBackend; SQLiteStore-specific scale statement when SQLiteStore is supported.
[ ] default limits derived from evidence.
Exit: supported scale and overload behavior published.


MILESTONE 52 — SYNTHETIC REFERENCE CORPUS
[ ] deterministic generator.
[ ] small/medium datasets.
[ ] identity stories.
[ ] invalid corpus.
[ ] Unicode/i18n.
[ ] moderation.
[ ] abuse fixtures.
[ ] snapshot/delta fixtures.
[ ] dataset manifest/hashes.
Exit: tests/demos/benchmarks require no real user data.


MILESTONE 53 — PROTOCOL ARCHAEOLOGY AND RATIONALE
[ ] record lifecycle matrix.
[ ] version-semantic metadata.
[ ] historical release fixtures.
[ ] old-state inspection plan/tool.
[ ] rejected-architecture register.
[ ] architecture rationale complete.
Exit: future maintainer can explain both “what” and “why” without oral history.


MILESTONE 54 — MULTI-LANGUAGE AND ECOSYSTEM READINESS
[ ] independent minimal validator.
[ ] Protocol Core SDK boundaries.
[ ] third-party implementation guide.
[ ] compatibility matrix.
[ ] persona docs.
[ ] publish-only integration.
[ ] seed governance.
[ ] maintainer succession.
[ ] Five-Minute Operator Contract.
Exit: Protocol can outlive the reference implementations and original maintainer.


FINAL MILESTONE ORDERING
Milestones 45–54 are not necessarily all sequential; independent implementation work may parallelize.
However:
- Protocol Stable requires 45, 46 and 48 plus existing Protocol/Security gates.
- Production Stable requires 47, 49, 50, 51, 52, 53 and 54 plus all earlier release gates.




MILESTONE 55 — SQLITESTORE ADAPTER RUNTIME PRECHECK
[ ] when SQLiteStore selected: PDO available.
[ ] when SQLiteStore selected: pdo_sqlite driver available.
[ ] when SQLiteStore selected: required SQLite capability set/version checked.
[ ] foreign keys/transactions self-test.
[ ] fail-fast diagnostic when unavailable.
[ ] no external sqlite3 CLI dependency.
[ ] FilesystemStore and publish-only paths verified without SQLite/PDO-SQLite.
Exit: SQLite requirements apply only to SQLiteStore; selecting FilesystemStore or publish-only operation has no hidden SQLite dependency.


MILESTONE 56 — WEB LOCAL SEARCH AND NO-JAVASCRIPT FALLBACK
[ ] static same-origin Web Client.
[ ] Android Tor Browser flow.
[ ] desktop Tor Browser flow.
[ ] in-memory search baseline.
[ ] optional storage failure path.
[ ] generic snapshot/chunk loading.
[ ] WEB-LOCAL: zero query-dependent network side effects; SERVER-FALLBACK: explicit POST only to selected serving server, no logs/persistence/remote work.
[ ] strict CSP/no remote assets.
[ ] explicit serving-origin trust limitation.
[ ] No-JavaScript fallback POST.
[ ] no query logging.
[ ] no query-dependent remote work.
[ ] ranking parity fixtures local/server.
[ ] accessibility/mobile tests.
Exit: Onion Service Discovery search is usable without any separate extension, with privacy semantics explicit for both modes.




MILESTONE 57 — STORAGE BACKEND ABSTRACTION
[ ] StorageBackend interface.
[ ] backend-neutral domain/repository layer.
[ ] FilesystemStore.
[ ] SQLiteStore.
[ ] canonical export/import.
[ ] backend conformance suite.
[ ] crash recovery tests.
[ ] migration between FilesystemStore and SQLiteStore.
[ ] operator backend selection.
Exit: full Node works without SQL and with SQLite, with identical Protocol semantics.


MILESTONE 58 — OPTIONAL SQL SERVER ADAPTERS
[ ] PostgreSQL adapter.
[ ] MySQL/MariaDB adapter.
[ ] prepared statements.
[ ] transactional invariant tests.
[ ] migration tests.
[ ] benchmark profile.
Exit: adapters pass the same storage contract or are explicitly excluded from Stable support.


MILESTONE 59 — CLIENT HOST / DATA NODE SEPARATION
[ ] Client Host role.
[ ] data-only Node role.
[ ] no active content from data Nodes.
[ ] combined convenience profile.
[ ] self-hosted Client Host instructions.
[ ] static bundle verification.
Exit: executable code origin and data sources can be operated independently.


MILESTONE 60 — MULTI-SOURCE MALICIOUS NODE DEFENSE
[ ] 3+ source support.
[ ] valid-record union.
[ ] source attribution.
[ ] omission scenario.
[ ] stale/replay scenario.
[ ] invalid fabrication.
[ ] signed Node checkpoints.
[ ] rollback detection.
[ ] equivocation detection.
[ ] no majority identity rule.
Exit: one malicious data Node cannot silently control the client’s entire discovery view.


MILESTONE 61 — SEPARATED WEB PRIVACY PROFILE
[ ] direct CORS prototype.
[ ] generic proxy prototype.
[ ] Tor Browser desktop test.
[ ] Tor Browser Android test.
[ ] query-independent traffic proof.
[ ] no cookies/credentials.
[ ] Origin/Referer/caching analysis.
[ ] ADR selects reference profile.
Exit: separated architecture has measured, documented privacy behavior.




MILESTONE 62 — HIGH-ASSURANCE KEY CUSTODY
[ ] Service Identity offline signer.
[ ] SignerProvider interface.
[ ] web runtime has no Service key dependency in preferred profile.
[ ] Node signer separated.
[ ] protected software-key format if offered.
[ ] Argon2id KDF test vectors/parameter benchmark.
[ ] XChaCha20-Poly1305 key-envelope tests.
[ ] encrypted backup.
[ ] recovery drill.
[ ] optional HSM/TPM/KMS/external signer adapter boundary.
Exit: compromise of the public web process does not automatically expose the preferred-profile Service Identity private key.


MILESTONE 63 — SECURE RELEASE TRUST
[ ] mature update metadata framework selected.
[ ] root/targets/snapshot/timestamp or equivalent roles.
[ ] threshold/offline high-value keys.
[ ] release metadata expiry/freshness.
[ ] rollback test.
[ ] freeze test.
[ ] compromised Git host scenario.
[ ] compromised online signing role scenario.
[ ] release:verify.
Exit: trusted update/release decision does not depend solely on Git hosting or TLS.


MILESTONE 64 — TOR AND CRYPTO EVOLUTION
[ ] strict v3 parser.
[ ] unknown address version rejection.
[ ] supported Tor matrix.
[ ] crypto suite registry/documentation.
[ ] v2 transition design fixture.
[ ] downgrade test.
[ ] compatibility-watch checklist.
Exit: future Tor/crypto evolution has a controlled migration path without weakening v1.


MILESTONE 65 — FINAL PRE-IMPLEMENTATION CLOSURE
[ ] all mandatory preparation documents current.
[ ] no known normative contradiction.
[ ] all unresolved security-critical decisions listed in Decision Register.
[ ] planning-vs-implementation decisions correctly separated.
[ ] Final Pre-Implementation Closure Audit re-PASS after full cross-document contradiction/mechanical-corruption scan.
Exit: stop general planning and start coding.




MILESTONE 66 — PLUG-AND-PLAY OPERATOR DASHBOARD
[ ] common publish-only config.
[ ] ready-to-deploy package.
[ ] /status/ server-rendered page.
[ ] role overview.
[ ] manifest status.
[ ] Tor self-verification.
[ ] bootstrap state.
[ ] bounded self-announcement.
[ ] external direct-observation check.
[ ] sampled searchable/export check.
[ ] truthful overall state.
[ ] Android/desktop responsive UX.
[ ] no secret leakage.
[ ] accessible warning hierarchy.
Exit: a fresh operator can understand and reach discoverable state from their own Onion Service without undocumented steps.


MILESTONE 67 — DISCOVERABILITY PROPAGATION UX
[ ] initial publication story.
[ ] pending verification story.
[ ] stale external revision story.
[ ] one-source discoverable story.
[ ] multi-source redundancy story.
[ ] degraded story.
[ ] identity conflict story.
[ ] withdrawal story.
[ ] migration story.
[ ] no fake global percentage.
Exit: every displayed discoverability state is test-fixture-backed and technically truthful.




MILESTONE 68 — VERIFIABLE INDEX INCLUSION
[ ] exact active Service tuple.
[ ] canonical active-index record.
[ ] deterministic record identifier.
[ ] signed Node checkpoint.
[ ] deterministic active index root.
[ ] canonical GET /services/by-id/{logical_service_id} exact stable-identity lookup.
[ ] GET /services/{onion} remains address convenience and resolves/exposes logical_service_id.
[ ] valid key rotation and Onion migration do not change the stable lookup identity.
[ ] local snapshot membership verification.
[ ] stale revision detection.
[ ] dashboard wired to actual membership evidence.
[ ] no listed=true shortcut.
Exit: operator can independently verify their current revision is actually in a Node's signed active index.


MILESTONE 69 — MULTI-SOURCE CHECKPOINT INTEGRITY
[ ] checkpoint sequence persistence.
[ ] rollback detection.
[ ] omission scenario.
[ ] source disagreement.
[ ] equivocation fixture.
[ ] preserve conflicting checkpoints.
[ ] checkpoint comparison across sources.
[ ] optional checkpoint gossip capability.
Exit: a Node cannot silently rewrite its own observed index history without producing detectable signed inconsistency where evidence is compared.


MILESTONE 70 — MERKLE INCLUSION PROOFS
[ ] decide v1 mandatory vs optional capability.
[ ] leaf construction.
[ ] branch construction.
[ ] deterministic ordering.
[ ] empty/odd tree rules.
[ ] proof encoding.
[ ] proof endpoint.
[ ] PHP vectors.
[ ] TypeScript vectors.
[ ] independent validator vectors.
[ ] invalid-proof fuzz tests.
Exit: compact proof is cryptographically bound to signed checkpoint, or capability remains disabled until all conditions pass.




MILESTONE 71 — SYBIL IDENTITY QUANTITY NEUTRALITY
[ ] Service count never increases trust.
[ ] Node count never resolves identity truth.
[ ] Witness count never creates trust score.
[ ] no majority fork resolution.
[ ] UI wording distinguishes distinct identities from independent operators.
[ ] objective misbehavior evidence overrides vote count.
Exit: creating more cryptographic identities alone yields no authority.


MILESTONE 72 — BOUNDED PEER AND SOURCE ADMISSION
[ ] finite active source set.
[ ] finite probation pool.
[ ] per-source referral/candidate caps.
[ ] global work budget.
[ ] gradual source replacement.
[ ] source-family grouping.
[ ] source concentration warning.
[ ] one source cannot populate all active slots.
Exit: Sybil peer creation cannot linearly expand local work or eclipse every stable source through simple referral flooding.


MILESTONE 73 — SYBIL SERVICE FARM SEARCH DEFENSE
[ ] 100k distinct matching Service fixture.
[ ] near-duplicate detection.
[ ] collapsible similar-result UI.
[ ] relevance count-neutrality.
[ ] no backlinks/observers/witness boost.
[ ] bounded query latency/memory.
[ ] all records remain inspectable.
Exit: automated Service farms cannot monopolize ordinary search solely through quantity.


MILESTONE 74 — ANNOUNCEMENT FLOOD RESILIENCE
[ ] rotating Service identities.
[ ] rotating Node/source identities.
[ ] same-target duplicate flood.
[ ] global admission budget.
[ ] target-centric backoff.
[ ] no synchronous verification storm.
[ ] optional PoW prototype only if benchmark-justified.
Exit: generating fresh identities does not reset the system's global resource controls.




MILESTONE 75 — PROTOCOL CATEGORY REGISTRY v1
[ ] categories-v1.json.
[ ] adult included.
[ ] all registry IDs frozen.
[ ] schema enum/generated constants.
[ ] PHP/TypeScript same source.
[ ] localization scaffold.
[ ] category filter.
[ ] adult local visibility preference.
[ ] category-count ranking neutrality.
[ ] invalid/unknown/duplicate/max fixtures.
Exit: category semantics are stable, interoperable and not mixed with trust/moderation.


MILESTONE 76 — SECURE CODING FOUNDATION (EARLY PREREQUISITE)
[ ] SECURITY-COVERAGE.md.
[ ] SECURITY-ATTACK-CLASS-REGISTRY.md.
[ ] typed validator/domain layer.
[ ] SafeJsonDecoder.
[ ] approved TorHttpClient.
[ ] safe renderer pattern.
[ ] strict PHP comparison/type policy.
[ ] dangerous API lint rules.
[ ] hard resource ceilings.
Exit: application feature code is built on constrained safe primitives rather than raw attacker-controlled strings.


MILESTONE 77 — APPLICATION EXPLOIT RESISTANCE
[ ] XSS/DOM XSS suite.
[ ] SQL injection suite.
[ ] command/code injection guard.
[ ] path traversal/symlink suite.
[ ] SSRF/destination suite.
[ ] CSRF/authz suite if admin enabled.
[ ] CRLF/header/open redirect suite.
[ ] deserialization/template/prototype pollution review.
[ ] ReDoS.
[ ] request/cache ambiguity review.
[ ] archive/decompression tests where applicable.
Exit: applicable common exploit classes have prevention controls and regression tests.


MILESTONE 78 — SECURITY STANDARDS AND FUZZ CLOSURE
[ ] ASVS 5.0.0 map.
[ ] OWASP Top 10 current map.
[ ] CWE Top 25 current map.
[ ] SAST.
[ ] dependency/SCA.
[ ] secret scanning.
[ ] Protocol fuzz targets.
[ ] property-based boundary tests.
[ ] differential parser fuzz.
[ ] mutation testing for critical validators/state machines.
[ ] exceptional-condition/fault injection.
Exit: security claims are backed by automated evidence and current standards mapping.
