# 27 - Formal Protocol Model and Model-Checking Verification Plan

Source: https://docs.google.com/document/d/1ZexdGAgo746df46SRwJOttMmpXq94QydK2I-LsFIUzs/edit?usp=drivesdk

Source modified: 2026-10-11T06:16:26.935Z

---

ONION SERVICE DISCOVERY
FORMAL PROTOCOL MODEL AND MODEL-CHECKING VERIFICATION PLAN


Status
Mandatory assurance artifact before Protocol v1 is declared stable. This document complements executable tests; it does not replace implementation testing.


1. Purpose
Natural-language specifications can hide contradictory assumptions. Onion Service Discovery therefore maintains a compact formal state model for the most security-critical protocol behaviors:
- Service revision chains;
- fork detection;
- Service Identity key rotation;
- Onion-address migration;
- Candidate verification;
- Peer synchronization commit ordering;
- pin/identity continuity;
- withdrawal/liveness separation.


2. Tooling
Preferred first choice:
TLA+ with PlusCal where state-machine readability benefits.


Alternative:
Alloy for relational counterexample exploration.


The agent MAY use another mature formal method only through ADR explaining:
- why it models the required temporal/state properties;
- reproducibility;
- reviewer accessibility;
- CI integration.


3. Scope Boundary
Formal model need not model:
- Ed25519 arithmetic;
- Tor cryptography internals;
- complete HTTP stack;
- UI rendering.


Instead it models cryptographic checks as trusted predicates with explicit assumptions. The purpose is to verify protocol state logic assuming primitive verification functions are correct.


4. State Variables — Service Continuity
At minimum:
KnownRevisions
CurrentRevision
CurrentManifestHash
KnownKeys
CurrentKeyEpoch
KnownOnionBindings
CurrentOnion
ForkEvidence
MigrationEvidence
RotationEvidence
DiscoverabilityState
LivenessState


5. Service Revision Actions
GenesisAccepted
SuccessorAccepted
StaleReceived
DuplicateReceived
ForkReceived
InvalidChainReceived
WithdrawAccepted
ObservationFailed


6. Revision Safety Properties
FORMAL-REV-001:
CurrentRevision never decreases after accepting a valid higher revision.


FORMAL-REV-002:
A stale revision can never become current solely because it is received later.


FORMAL-REV-003:
Two distinct valid manifest hashes at the same logical identity and revision produce ForkEvidence.


FORMAL-REV-004:
ForkEvidence is never silently erased by an ordinary later network event.


FORMAL-REV-005:
Liveness failure cannot alter cryptographic revision validity.


7. Rotation Model
Variables:
KeyEpoch
RotationRecords
AcceptedKeyTransitions
ConflictingRotations


Actions:
ProposeRotation
ValidateOldSignature
ValidateNewConfirmation where required
CommitRotation
ReceiveConflictingRotation


Properties:
FORMAL-KEY-001:
Current key can change only through a valid authorized transition.


FORMAL-KEY-002:
Unknown new key without valid transition cannot inherit continuity.


FORMAL-KEY-003:
Two competing valid successor keys from the same predecessor/effective state produce conflict.


FORMAL-KEY-004:
Crash or partial commit cannot expose a current-key state without corresponding rotation evidence.


8. Onion Migration Model
Variables:
CurrentOnion
HistoricalOnions
MigrationRecords
EndpointConfirmations
MigrationConflicts


Properties:
FORMAL-MIG-001:
Current Onion changes with continuity only after valid Service Identity migration evidence.


FORMAL-MIG-002:
Peer assertion alone cannot create local direct old-endpoint confirmation.


FORMAL-MIG-003:
Competing migrations from the same predecessor become conflict evidence.


FORMAL-MIG-004:
Historical Onion bindings remain reconstructable after migration.


9. Candidate Verification Model
States:
RECEIVED
ADDRESS_VALIDATED
QUEUED
DIRECT_CHECK_PENDING
VERIFIED
BACKOFF
QUARANTINED
REJECTED


Safety:
FORMAL-CAND-001:
VERIFIED is reachable only following a successful DirectVerification event.


FORMAL-CAND-002:
Peer advertisement never transitions Candidate directly to VERIFIED.


FORMAL-CAND-003:
Invalid Onion address never reaches transport action.


FORMAL-CAND-004:
Backoff cannot create an infinite zero-delay retry cycle.


10. Peer Sync Model
Variables:
LocalEvidence
StagingBatch
CommittedCursor
RemoteCursor
Conflicts
SyncState


Actions:
FetchState
FetchBatch
ValidateEnvelope
ValidateRecords
Stage
CommitBatch
AdvanceCursor
AbortBatch


Properties:
FORMAL-SYNC-001:
CommittedCursor never advances beyond durably committed batch state.


FORMAL-SYNC-002:
Aborted batch cannot mutate accepted current identity state.


FORMAL-SYNC-003:
Receiving same valid batch twice is idempotent.


FORMAL-SYNC-004:
Stale peer data cannot downgrade newer local identity state.


FORMAL-SYNC-005:
Imported DirectObservation remains attributed to remote Node and never becomes local DirectObservation.


11. Pinning Model
Variables:
PinnedServiceIdentity
PinnedOnionContext
ObservedIdentity
MigrationValid


Properties:
FORMAL-PIN-001:
Peer majority cannot mutate a local pin.


FORMAL-PIN-002:
Different Service Identity produces warning state.


FORMAL-PIN-003:
Valid migration may update current Onion context while retaining logical identity, according to explicit client policy.


12. Discoverability vs Liveness
Model two orthogonal axes:
Discoverability: UNKNOWN, PUBLISHED, WITHDRAWN
Liveness: RECENT, TEMP_UNREACHABLE, STALE, LONG_UNSEEN


Properties:
FORMAL-LIFE-001:
Unreachable does not imply withdrawn.
FORMAL-LIFE-002:
Withdrawn does not erase continuity history.
FORMAL-LIFE-003:
Liveness timestamps do not resolve cryptographic fork.


13. Sybil Abstraction
Formal model treats each Peer as potentially adversarial.
No safety property may assume:
- majority honest;
- number of peers maps to independent operators;
- first peer truthful.


14. Crash Model
Introduce nondeterministic crash points around:
- DB transaction;
- key rotation;
- migration;
- sync batch;
- cursor update.


Properties:
FORMAL-CRASH-001:
After recovery, persistent state satisfies same invariants as a completed or rolled-back transaction.
FORMAL-CRASH-002:
No half-rotation/current-key state.
FORMAL-CRASH-003:
No cursor-without-data state.


15. Model Size
Use bounded small model sufficient to find counterexamples:
- 2–4 revisions;
- 2–3 keys;
- 2–3 Onion addresses;
- 2–4 peers;
- valid and malicious event branches.


State explosion is controlled by symmetry and bounded domains.


16. Assumptions
Explicitly model assumptions:
ASSUME_SIGNATURE_VERIFIER_SOUND
ASSUME_HASH_COLLISION_RESISTANCE for modeled logic
ASSUME_DATABASE_TRANSACTION_ATOMIC within selected storage model
ASSUME_TOR_TRANSPORT_BINDING when DirectVerification predicate succeeds


Assumptions are listed in audit documentation and never confused with proven properties.


17. Liveness Properties
Where tractable:
- a queued candidate with endlessly successful resources and scheduler opportunity can eventually reach verification;
- a healthy peer with changes can eventually converge;
- withdrawal revision can eventually propagate.


Liveness assumptions must include fair scheduling; no unrealistic guarantee under permanent adversarial outage.


18. Counterexample Workflow
If model checker produces counterexample:
- save trace as artifact;
- classify spec bug vs model bug;
- update protocol/ADR;
- add executable regression test reproducing analogous sequence;
- rerun model.


19. Formal-to-Code Traceability
Every formal invariant receives:
Formal ID
Normative spec section
Implementation modules
Executable tests
Model theorem/invariant name


Maintain docs/formal/traceability.md.


20. CI
Formal model should run:
- on changes to protocol state semantics;
- on changes to model itself;
- before Protocol Freeze;
- before RC.


A reduced fast model can run each CI; larger exploration can run scheduled.


21. Model Review
Human Protocol/Security reviewer checks:
- variables correspond to real semantics;
- assumptions are not smuggling desired result;
- omitted state is documented;
- invariants reflect security claims;
- counterexample coverage.


22. Deliverables
formal/
  README.md
  OnionServiceDiscovery.tla
  OnionServiceDiscovery.cfg
  traces/
  invariants.md
  assumptions.md
  traceability.md


If Alloy additionally used:
  model.als
  assertions/


23. Acceptance
Formal milestone PASS requires:
- all listed safety invariants modeled;
- no unresolved counterexample;
- assumptions documented;
- executable tests mapped;
- model reproducible from clean environment;
- human review completed.


24. Non-Claim
Passing model checking does not prove the entire implementation correct. It proves the explored formal model satisfies stated properties within modeled bounds and assumptions.


25. Final Requirement
Any future change to revision, rotation, migration, sync commit, pinning or conflict semantics requires updating the formal model before the change can become Protocol normative.
