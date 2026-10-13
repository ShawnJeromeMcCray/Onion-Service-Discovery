# 50 - Protocol Category Registry v1 Tags Localization and Content Classification Standard

Source: https://docs.google.com/document/d/1OkWgB_tMUtBJ8iZXqyqBFfiIKwQcyXyFURtxijNNZhw/edit?usp=drivesdk

Source modified: 2026-10-12T14:28:23.622Z

---

ONION SERVICE DISCOVERY
PROTOCOL CATEGORY REGISTRY v1, TAGS, LOCALIZATION AND CONTENT CLASSIFICATION STANDARD


Status
Binding Protocol v1 registry and client/operator UX standard.


1. Purpose
Provide a stable, bounded, language-neutral set of broad content categories for public Onion Services.


Goals:
- predictable filtering/navigation;
- no category spelling fragmentation;
- no category-derived trust;
- multilingual display without changing signed identifiers;
- room for precise free-form tags without exploding the core registry.


2. Core Principle
Categories describe CONTENT/FUNCTION.


Categories MUST NOT mean:
- trusted;
- safe;
- legitimate;
- official;
- verified;
- legal;
- malware-free;
- endorsed.


3. Self-Declared Nature
Manifest categories are declared by the Service operator and signed by the Service Identity.


A category assignment proves only:
“the Service Identity signed a Manifest containing this category.”


It does not prove the description is accurate.


4. Moderation Separation
Independent moderation/classification MAY separately state:
- suspected phishing;
- malware;
- illegal-content concern;
- adult-content concern;
- impersonation;
- spam.


Such moderation MUST NOT rewrite the Service-signed category field.


5. Category Identifier Syntax
Protocol v1 category IDs:
- lowercase ASCII;
- 1..64 bytes;
- grammar: [a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?;
- no underscores;
- no whitespace;
- no Unicode;
- no leading/trailing hyphen;
- no duplicate IDs.


6. Category Count
Maximum categories per Manifest remains 32.


Reference operator UX SHOULD normally recommend 1..5 broad categories.


7. Registry Version
The registry has an explicit version:
category_registry_version = 1


The registry version is documentation/client metadata, not a free-form Manifest field unless the Protocol schema explicitly adds it.


8. Unknown Category Behavior
Protocol v1 validator:
- accepts only registry-defined Protocol v1 category identifiers for the normative category field;
- unknown categories fail validation unless an explicit future extension mechanism is used.


This avoids permanent category spelling fragmentation.


9. Tags Are Separate
Specific descriptors belong in optional tags, not the core category registry.


Example:
categories:
  privacy
  software


tags:
  messaging
  self-hosted
  open-source


10. Tag Syntax
If Protocol v1 includes tags:
- maximum 32 tags;
- each 1..48 Unicode scalar values after field-specific validation;
- no control characters;
- no bidi controls unless explicitly allowed by future internationalization rules;
- deterministic Unicode normalization policy;
- duplicates rejected after defined comparison normalization;
- tags are plain text only.


If tags are not included in initial v1 schema, reserve them for a backward-compatible Protocol extension instead of silently overloading categories.


11. Tag Trust
Tags are self-declared searchable metadata only.


No trust/ranking boost from:
- tag popularity;
- number of tags;
- same tag across many Services.


12. Registry v1 — Core Categories


adult
Adult-oriented sexual/erotic content or services intended for adults.
This identifier is a content category, not a legal determination.


archives
Historical archives, mirrors, preserved datasets or collections.


art
Visual arts, creative work, design and artistic projects.


audio
Audio content, radio, podcasts, spoken audio and sound.


blogs
Blogs, personal publishing and journals.


books
Books, literature, long-form texts and publishing.


business
Business information, organizations and commercial operations not primarily a storefront.


commerce
Shops, marketplaces, product/service sales and commercial listings.


communication
Messaging, email, chat, contact and communications services.


community
General communities, groups and community hubs.


directories
Structured listings or directories of resources/services.


documentation
Technical/manual/reference documentation.


education
Courses, tutorials, educational resources and teaching.


entertainment
General entertainment not more specifically categorized.


files
File hosting, file sharing, downloads and repositories of files.


finance
Financial information/services, accounting, payments or finance-related content.
Category does not imply regulation or legitimacy.


forums
Threaded discussion boards and forum-style communities.


games
Games, gaming communities and game-related services.


health
Health, wellness or medical information/services.
Category does not establish medical reliability.


hosting
Hosting, infrastructure, network/server services and related tooling.


images
Image galleries, photography and image-centric services.


journalism
Journalism, reporting, newsrooms and current-affairs publications.


legal
Legal information, legal resources or legal-service information.
Category does not establish attorney status or jurisdictional validity.


media
General mixed-media publishing.


music
Music-focused content/services.


politics
Political discussion, organizations, commentary, campaigning or policy content.
Category is descriptive only and not an endorsement.


privacy
Privacy-focused information, software or services.


research
Research, academic/scientific projects, datasets and publications.


search
Search engines, discovery services and searchable indexes.


security
Cybersecurity, defensive security, vulnerability research and security tooling.


services
General online services not fitting a narrower functional category.


social
Social-networking or social-feed services.


software
Software projects, packages, applications and source/software distribution.


technology
General technology content and technical communities.


tools
Utilities, online tools and functional web applications.


video
Video hosting, streaming and video-centric publishing.


wiki
Wiki-style collaborative knowledge bases.


other
Broad fallback only when no registered category reasonably applies.


13. Adult Category Semantics
adult is a normal Protocol category.


It MUST NOT automatically imply:
- illegal;
- unsafe;
- abusive;
- malware;
- untrusted.


It also MUST NOT imply age verification has occurred.


14. Adult UX
Clients SHOULD provide a local user preference to:
- show;
- de-emphasize;
- hide;
adult-category results.


The preference is local.


No account or central preference service is required.


15. Adult Misclassification
A Service can omit the adult category maliciously or mistakenly.


Therefore:
- self-declared category cannot be relied on as the sole content-safety control;
- independent moderation sources MAY classify content separately;
- search UI can present “operator-declared category” distinctly from external moderation signals.


16. Illegal/Abusive Content Is Not a Category
Do not add registry values such as:
illegal
malware
phishing
scam
trusted
verified
official


Those are moderation/evidence/status concepts, not operator content categories.


17. Category Localization
Signed Manifest stores only stable category ID:
"security"


Client localization maps it to labels:
German: Sicherheit
English: Security
French: Sécurité
etc.


Translation updates do not change the Manifest bytes or category identity.


18. Localization Fallback
If locale translation unavailable:
display canonical English registry label or the raw stable ID safely.


19. Registry Canonical English Labels
Every category has:
id
canonical English label
normative semantic description
registry status
introduced version
deprecated version optional
replacement optional.


20. Registry Status
Allowed statuses:
PERMANENT
PROVISIONAL
DEPRECATED
RESERVED


All categories present at Protocol v1 freeze become PERMANENT for Registry v1. No post-freeze addition or semantic repurposing occurs under the same registry version.


21. Deprecation
A category identifier is never silently repurposed.


If deprecated:
- old meaning remains historically stable;
- replacement may be defined;
- clients continue parsing it for archived records;
- new manifests SHOULD use replacement after deprecation cutoff policy.


22. Reserved IDs
The registry MAY reserve identifiers for future use.


Reserved IDs are rejected in normal v1 Manifests until activated.


23. Category Evolution
The Protocol Category Registry v1 is immutable after Protocol v1 freeze because v1 validators reject unknown category IDs.


After freeze:
- existing category IDs retain their meaning permanently;
- no new permanent ID may be appended to categories-v1.json under the same Protocol-v1 semantics;
- a new category requires an explicitly versioned extension mechanism that old implementations can safely ignore, or a new Protocol version;
- an existing ID is never repurposed.


Before the initial Protocol v1 freeze, registry changes require Protocol Change Proposal, semantic/collision review, localization/search/abuse review and Human Protocol/Product approval.






24. No Hidden Hierarchy in Signed Data
Protocol v1 stores flat category IDs.


Clients MAY group categories visually, but visual hierarchy is not normative identity data.


25. Optional UI Grouping
Example UI-only groups:
CONTENT
COMMUNICATION
KNOWLEDGE
TECHNOLOGY
COMMERCE
MEDIA
OTHER


This grouping can evolve without changing Manifest signatures.


26. Multiple Categories
Services can select multiple categories.


Example:
community
privacy
security


Order is canonicalized according to Protocol set-like array rules.


27. Deterministic Order
Categories are a set-like field:
- no duplicates;
- canonical ascending ASCII sort before signing if Protocol canonical field rules require sorted arrays;
- exact behavior frozen in schema/vectors.


28. Category Validation
Validation checks:
- array type;
- max 32;
- string type;
- byte/scalar limits;
- ASCII grammar;
- registry membership;
- duplicates;
- deterministic order where normative.


29. No HTML
Category IDs and localized labels are rendered as text only.


No category value can inject:
HTML
CSS
JavaScript
URL
attribute syntax.


30. Search Behavior
Category filtering is an exact ID filter.


A Service cannot invent a lookalike Unicode category because category IDs are ASCII registry tokens.


31. Ranking
Selecting more categories does not increase rank.


A category match MAY narrow/filter or contribute a bounded relevance signal if explicitly specified by the versioned ranking algorithm.


32. Category Spam
A Service with 32 categories is not inherently more relevant than one with 2.


The ranking algorithm MUST prevent category-count advantage.


33. Category/Tag Confusables
Category IDs avoid Unicode confusables by being registry ASCII tokens.


Tags, if Unicode, use the project's Unicode/confusable display rules.


34. Configuration UX
Operator UI/config SHOULD present a selectable registry list rather than asking users to type category IDs manually.


Manual config still validates exact IDs.


35. Config Example
'categories' => [
    'community',
    'privacy',
    'security',
],


36. Adult Config Example
'categories' => [
    'adult',
    'community',
],


37. Synthetic Dataset
Golden corpus MUST include:
- each category;
- multiple categories;
- adult;
- invalid category;
- deprecated category fixture once applicable;
- duplicate categories;
- wrong case;
- Unicode lookalike;
- overlong category;
- 33 categories.


38. Conformance
CONF-CATEGORY-REGISTRY-V1
CONF-CATEGORY-UNKNOWN-REJECT
CONF-CATEGORY-DUPLICATE
CONF-CATEGORY-ORDER
CONF-CATEGORY-MAX
CONF-CATEGORY-ASCII
CONF-CATEGORY-ADULT
CONF-CATEGORY-NO-RANK-COUNT-BOOST


39. Accessibility
Category filtering must be keyboard/screen-reader usable.


Adult visibility preference must not rely on color alone.


40. Privacy
Category filters are executed locally in Web Local Search mode.


Local category filtering MUST NOT transmit the selected category/query to a Discovery Node.


41. No Central Taxonomy Authority at Runtime
The registry is part of the Protocol specification/release artifacts.


Runtime operation does not call a central category server.


42. Registry Artifact
Required machine-readable artifact:
protocol/registries/categories-v1.json


Fields:
registry_version
categories[]
  id
  canonical_label
  description
  status
  introduced
  deprecated optional
  replacement optional


43. Schema Linkage
The Manifest JSON Schema MUST validate category values against the frozen v1 registry or an equivalent generated enumeration.


44. Code Generation
Clients/nodes MAY generate constants/types from the registry artifact.


Hand-maintained duplicate category lists in multiple languages are prohibited where generation can prevent drift.


45. Historical Stability
Archived Manifest revision category IDs retain their original registry meaning indefinitely.


46. Acceptance
PASS requires:
- normative v1 registry artifact;
- adult category included;
- categories separated from moderation/trust;
- localization outside signed identifiers;
- tags separate;
- unknown category handling frozen;
- code/schema generated from one registry source;
- local filtering privacy;
- conformance vectors.
