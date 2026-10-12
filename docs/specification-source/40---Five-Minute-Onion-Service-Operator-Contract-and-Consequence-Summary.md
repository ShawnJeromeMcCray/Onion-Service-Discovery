# 40 - Five-Minute Onion Service Operator Contract and Consequence Summary

Source: https://docs.google.com/document/d/1ZiRFTgUwz0zlacA_HU3LVqFIqxIpH39JWTkVqeK935k/edit?usp=drivesdk

Source modified: 2026-10-11T06:24:07.405Z

---

ONION SERVICE DISCOVERY
FIVE-MINUTE ONION SERVICE OPERATOR CONTRACT AND CONSEQUENCE SUMMARY


Purpose
This is the concise operator-facing contract. A Service operator should understand the essential technical and privacy consequences before enabling Onion Service Discovery.


1. What You Are Enabling
When you publish an Onion Service Discovery Manifest, you are explicitly opting your Onion Service into a decentralized public discovery ecosystem.


Other independent Nodes may:
- fetch your public Manifest;
- verify its signature and Onion binding;
- store it;
- mirror it;
- include it in public index data;
- retain historical continuity evidence.


2. What You Must Publish
At the standardized Well-Known path:
a signed Manifest containing, at minimum:
- your Onion address;
- Onion Service Discovery Service Identity public key;
- revision/history link;
- publish setting;
- bounded public metadata.


3. What You Do NOT Give Onion Service Discovery
You do NOT give it:
- your private Tor Hidden-Service key;
- your Tor control password;
- user search logs;
- user accounts;
- recurring registration credentials.


4. The Key You Must Protect
Onion Service Discovery uses a separate Service Identity private key.


Protect and back it up securely.


If you lose it before creating a valid rotation:
the protocol may not be able to prove continuity to a new Service Identity key.


No project-wide master recovery key exists in Protocol v1.


5. Your Tor Key Remains with Tor
The application must never need to read or export the private Tor Onion-Service key.


6. Your Public Metadata
Assume anything in the signed Manifest becomes public and can be copied indefinitely by independent parties.


Do not place:
private contact details
secrets
credentials
internal URLs
personal data you do not intend to publish.


7. No Central Registration Renewal
Once connected to discovery pathways, your Service can propagate without recurring manual renewal.
Freshness is based on observations and new signed revisions.


8. Opting Out
To withdraw from active discovery:
publish a valid new Manifest revision with publication disabled according to Protocol.


Important:
decentralized systems cannot guarantee every independent Node deletes historical copies.
Reference clients/nodes should stop treating verified withdrawal as an active normal search result according to policy, while continuity/security evidence can remain.


9. Temporary Downtime
Going offline is not the same as opting out.
Nodes may retain your last valid public state and mark it stale/unreachable.


10. Changing Metadata
Change public metadata through a new signed revision.
Do not reuse the same revision with different content; that creates a Fork/conflict.


11. Moving to a New Onion Address
Use the defined Onion Migration workflow.
The same Service Identity key signs the transition from old Onion to new Onion.
When possible, old and new endpoints provide direct confirmation.


Do not simply change the Onion field without migration evidence if you want continuity.


12. Rotating the Service Identity Key
Use the defined Key Rotation workflow.
The old key authorizes the new key.
Back up keys before rotation.
Test the transition before publication.


13. If Your Key Is Compromised
Stop automatic publishing.
Preserve evidence.
Follow the incident runbook.
Do not “fix” history by deleting or rewriting old revisions.


14. Running a Full Node Is Optional
You can be discoverable without mirroring/verifying the entire network.


Roles are separate:
Publish
Mirror
Discover
Sync.


15. Full Node Consequences
If you enable Node roles:
your server makes outbound Onion requests through Tor;
stores public discovery records;
uses bandwidth/disk;
needs scheduler/limits/backups;
may be exposed to malicious peer/input traffic.


Use secure defaults and the security self-check.


16. Tor Transport
All Onion Service Discovery outbound Onion requests from a Node must use the configured Tor SOCKS path.
There must be no silent direct-network fallback.


17. What Verification Means
If a client says your Manifest signature/Onion binding is valid, this means the technical identity evidence checked successfully.


It does NOT mean:
your content is endorsed;
you are legally who your name says;
your Service is safe;
the Tor Project approves your Service.


18. Display Names Are Not Unique
Another Service can use the same display name.
Your cryptographic Service Identity and Onion history distinguish you technically.


19. External/Clearnet Proofs
If optional future cross-link proof is used, it is additional technical evidence, not a universal identity certificate.


20. Search Queries
Reference clients search locally.
Your Service normally does not receive a Discovery query telling you what a user searched for.


When a user actually opens your Onion Service, ordinary Tor browsing behavior applies.


21. Logs
If you run a Node:
minimize logs.
Do not collect search queries—standard protocol does not send them.
Use bounded retention.


22. Backups
Back up separately:
Service Identity private key
Node Identity private key if running Node
database
production config.


Test restore.


23. Upgrade
Before upgrade:
read release notes
backup
verify support matrix.
After upgrade:
run migrations/self-check.


24. Security Updates
Run supported PHP/Tor/webserver versions according to your environment.
Onion Service Discovery does not replace host patching.


25. What Happens If the Original Project Maintainer Disappears
Your Service Identity and Manifest do not depend on a central project server.
Other Nodes/implementations can continue following the public Protocol.


26. Minimal Publish-Only Checklist
[ ] Read this contract.
[ ] Choose public metadata.
[ ] Generate Service Identity key securely.
[ ] Back up private key.
[ ] Configure canonical Onion.
[ ] Generate Manifest.
[ ] Verify locally.
[ ] Serve Well-Known path.
[ ] Verify through your Onion.
[ ] Explicitly enable publishing.


27. Full Node Additional Checklist
[ ] Configure Tor SOCKS.
[ ] Verify no direct fallback.
[ ] Generate separate Node Identity key.
[ ] Configure roles.
[ ] Configure finite resource limits.
[ ] Configure scheduler.
[ ] Configure database/backup.
[ ] Run doctor.
[ ] Run security:check.
[ ] Sync with test/known peers.
[ ] Monitor health.


28. Incident Checklist
If something looks wrong:
do not rotate/delete blindly.
Capture:
software version
current revision/hash
identity fingerprint
error code
health report.
Then follow relevant runbook.


29. Final Operator Statement
By enabling publication, you are intentionally making your Onion Service’s discovery metadata public and mirrorable. You retain control of your Service Identity key and future signed revisions, but you do not control all historical copies held by independent participants.


30. Five-Minute Success Criterion
A new operator should be able to read this document in roughly five minutes and accurately explain:
what is public;
which private key matters;
how to withdraw;
how to migrate;
why downtime is not withdrawal;
what technical verification does and does not prove.
