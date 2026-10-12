# Well-known registration preparation (not submitted)

Draft suffix: `onion-service-discovery.json`.
Purpose: locate an opt-in, self-hosted signed discovery manifest for the exact
Tor v3 Onion hostname. GET/HEAD returns bounded UTF-8 application/json, with no
redirect-based identity binding, authentication cookie or search query.
Specification reference: the reviewed/frozen public protocol handbook, after
approval. Proposed change controller: the project governance owner, to be named
through the decision register; the implementation agent cannot appoint one.

This is a preparation document, not an IANA registration or an RFC publication.
The draft reference implementation uses
`/.well-known/onion-service-discovery.json`. Do not claim that suffix is registered.

Observed registry check on 2026-10-12:
https://www.iana.org/assignments/well-known-uris/well-known-uris-1.csv
contained 106 rows and no `onion-service-discovery` match. Observed CSV SHA-256:
`71548a92d79fd606ec60c279843eb70130f148de6cfabbeae9f9170463104033`.
This observation is time-specific and is not an assurance of future availability.
Recheck RFC 8615 and the current IANA submission requirements at submission time.

Security/privacy considerations to include: strict exact-hostname binding;
separate Service/Node/Tor/release keys; bounded duplicate-aware parsing; mature
signature verification; signed opt-in and withdrawal; public metadata may be
copied permanently; a signature does not endorse content or legal identity;
no normal browser query is transmitted; a malicious serving origin can replace
browser code; a valid peer observation does not become local direct verification.

Before submission, approve the exact suffix/format, protocol version and key
bytes, public specification reference, change controller, maintenance process and
extension registry policy. Prepare examples and interoperable exact-byte vectors.
Do not send an application, announce RFC/IANA approval or reserve an identifier
on the project's behalf without the required owner/reviewer authorization.
