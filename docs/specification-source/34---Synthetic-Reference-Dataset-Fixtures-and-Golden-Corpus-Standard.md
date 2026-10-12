# 34 - Synthetic Reference Dataset Fixtures and Golden Corpus Standard

Source: https://docs.google.com/document/d/1bma0eBbVdyjw1uvGD7mnN0GEKZngTRS9npaNaxQOx8c/edit?usp=drivesdk

Source modified: 2026-10-11T06:20:42.034Z

---

ONION SERVICE DISCOVERY
SYNTHETIC REFERENCE DATASET, FIXTURES AND GOLDEN CORPUS STANDARD


1. Purpose
Testing, performance work, demos, UI development and external audits must not depend on real users or public Onion Services. Onion Service Discovery therefore maintains deterministic synthetic datasets with cryptographically valid and intentionally invalid records.


2. Dataset Classes
DATASET-SMALL
1,000 Services.


DATASET-MEDIUM
10,000 Services.


DATASET-LARGE
100,000 Services.


DATASET-STRESS
1,000,000 synthetic Service identities where feasible for offline benchmark generation.


3. Deterministic Generation
Generator accepts:
dataset version
random seed
protocol version
profile.
Same inputs produce same logical records and expected hashes/signatures.


4. Test-Only Keys
All keys generated for datasets are test-only.
Repository marks paths clearly.
No production key material.
Secret scanners use narrow documented fixture exceptions only.


5. Service Persona Distribution
Include:
stable single-revision
multi-revision
key rotation
Onion migration
withdrawal
temporarily unreachable
long unseen
fork
identity changed
same-name distinct identity
confusable names
multilingual metadata
maximum-field boundary
minimal manifest
moderation warnings
multiple observers.


6. Name Corpus
Synthetic neutral names.
Avoid accidentally impersonating real high-risk brands in shipped demo fixtures unless clearly fictional/test-labeled.
Include controlled Unicode/homograph cases.


7. Language Corpus
Represent:
English
German
French
Spanish
Arabic/RTL
Japanese
mixed Unicode stress cases.
Purpose is i18n/UI testing, not demographic modeling.


8. Search Corpus
Construct terms with:
rare exact match
common match
category match
description-only match
language filter
keyword stuffing
duplicate-like services.


Expected result/ranking snapshots versioned.


9. Identity Corpus
Golden examples:
Genesis
Revision 2/3
stale
fork
rotation K1->K2
conflicting rotation
migration A->B
migration chain A->B->C
invalid migration
lost-continuity identity change.


10. Node Corpus
Healthy Nodes
stale descriptors
old Protocol version
equivocating descriptor
different capabilities
learned/pinned source metadata.


11. Moderation Corpus
Synthetic signed sources:
phishing warning
malware warning
spam warning
expired warning
conflicting source opinions.
Never alter cryptographic identity status.


12. Invalid Corpus
Separate invalid files:
duplicate keys
bad UTF-8 fixture where storage allows binary
wrong types
oversized fields
unsafe integers
bad Base64url
wrong signature length
wrong signature
wrong hash
wrong Onion checksum
wrong version
unknown field
nested depth violation.


13. Golden Expected Outputs
For each fixture:
expected canonical bytes hash
expected signature status
expected protocol error/state
expected current-view derivation
expected UI semantic state where applicable.


14. Manifest
dataset-manifest.json:
dataset_version
protocol_version
generator_version
seed
record counts
file hashes
expected aggregate root
creation toolchain.


15. Immutability
Published dataset version is immutable.
Change fixture -> new dataset version.
Do not rewrite benchmark history silently.


16. Generation Source
Generator code is source of truth for large datasets.
Small golden corpus committed directly for audit readability.


17. Snapshot Fixtures
Generate deterministic snapshots/chunks using chosen Protocol v1 format.
Include:
full snapshot
incremental changes
expired cursor recovery
chunk corruption negative fixture.


18. Database Fixtures
Provide prebuilt SQLite fixtures only as convenience.
Canonical generation path remains records + migrations so DB fixture does not become normative.
DB fixture hash/version recorded.


19. IndexedDB Fixtures
Client integration can populate through normal import pipeline rather than relying on opaque browser DB copy where possible.


20. UI Story Fixtures
Named fixture scenarios:
STORY-NORMAL
STORY-PIN-MISMATCH
STORY-MIGRATION
STORY-FORK
STORY-OFFLINE
STORY-WITHDRAWN
STORY-CONFUSABLE
STORY-MODERATION.


21. Accessibility Fixtures
Long text
RTL
screen-reader state changes
large zoom
long Onion/fingerprint
multiple warnings.
Used in visual/manual accessibility review.


22. Performance Distribution
Dataset should model realistic distribution:
most Services modest history;
small subset many revisions;
small subset long descriptions;
moderate category/language variety.
Stress dataset can deliberately maximize boundary costs.


23. Abuse Fixtures
100k duplicate candidates
100k distinct valid-format candidates
large peer lists
spam metadata
invalid-signature batch
fork flood.
Stored/generated separately to avoid ordinary test overhead.


24. Privacy
No real search logs.
No real browsing history.
No real personal information.
No copied private Onion metadata.


25. Licensing
Dataset and generator distributed under a license compatible with conformance/testing reuse.
Human project license decision applies or separate explicit license selected.


26. Reproducibility
CI regenerates sample subset and checks hashes.
Release verifies generator output version.


27. Benchmark Linkage
Every benchmark report references exact dataset version/hash.


28. Audit Linkage
Audit package includes small/medium corpus and generator instructions.
Auditor can reproduce critical identity scenarios without network.


29. Third-Party Implementation
Conformance docs encourage external implementers to use golden corpus.
Expected result files language-neutral.


30. Corpus Evolution
When a parser/security bug found:
add minimized regression fixture.
Classify:
normative protocol edge
implementation-only bug
fuzz regression.


31. Storage Size
Large/stress datasets need not all be committed if generator deterministic.
Release/archive may publish compressed artifacts with hashes.


32. No Remote Fetch Requirement
Core test suite can build/use local fixtures offline.
Do not require an external fixture server.


33. Acceptance
Reference corpus PASS requires:
- deterministic generator;
- small/medium published fixture sets;
- identity/anti-fraud stories;
- invalid corpus;
- performance datasets;
- manifest/hash integrity;
- zero real private/user data.
