# Security copy registry

Spec 31 section 37 asks for one place that lists every security-relevant text of
the browser client: when it appears, what it means, what it must not imply and
which test covers it. English is the only interface language (owner decision);
there are no translations to keep in step. The source of truth for the exact
wording is `web-local-search-client/src/client/app.ts` and
`web-local-search-client/src/client/messages.ts`; change both together.

## Severity

Every notice carries a marker and, for problems, a written label, so colour is
never the only signal (spec 31 sections 5 and 6).

| Client class | Marker and label | Spec 31 vocabulary | Used for |
| --- | --- | --- | --- |
| `sev-info` | `i` | INFORMATION | Ordinary absence, withdrawal |
| `sev-attention` | `!` and "Note:" | ATTENTION | Incomplete history, older data, look-alike metadata, limited coverage, migration |
| `sev-danger` | `⚠` and "Warning:" | HIGH-RISK IDENTITY CHANGE, PROTOCOL/INTEGRITY ERROR | Another key for a pinned name or address, forks, conflicting signed Node data |

Ordinary Tor or Node outages are ATTENTION, never danger (spec 31 section 44).

## Pins

| Copy ID | State | English reference | Meaning | Must not imply | Tests |
| --- | --- | --- | --- | --- | --- |
| PIN.CONTINUITY_VALID | `CONTINUITY_VALID` | Signed continuity matches your pin | The loaded history connects your pinned key to the current Manifest | That the Service is safe or run by whom it claims | client.spec "local search…"; client-model "pins follow…" |
| PIN.MIGRATED | `MIGRATED` | Authorized onion migration from your saved context. The Service moved with a signed migration proof; use the current onion address below. | A signed migration proof moves the identity to a new Onion | That the new Onion was checked for content or uptime | client-model "pins follow…" |
| PIN.WITHDRAWN | `WITHDRAWN` | Publication withdrawn; identity history retained. The operator withdrew this Service from discovery. | The latest signed revision withdraws discovery | That the Service is offline or deleted everywhere | client-model "pin states for withdrawal…" |
| PIN.FORKED | `FORKED` | Warning: Fork conflict retained — no current branch selected. Two signed branches exist. Use neither until you know which one the operator published. | Two signed revisions with the same number exist | That more Nodes or a faster answer decide the fork | client-model "pins follow… fork" |
| PIN.HISTORY_INCOMPLETE | `HISTORY_INCOMPLETE` | Note: More history is needed to connect this pin. Load again or add sources to connect the history. | Continuity cannot be shown yet | That the identity changed | client-model "pin states for withdrawal…" |
| PIN.REVISION_DOWNGRADE | `REVISION_DOWNGRADE` | Note: Older than your remembered revision. A source served an older revision than you saw before. Your pin keeps the newer one; load other sources. | A source served stale data | That the Service rolled back on purpose | client-model "pins follow…" |
| PIN.IDENTITY_CHANGED | `IDENTITY_CHANGED` | Warning: Another key claims the saved onion; pin preserved. Do not trust the other key. Your pin is kept; check the Service's signed rotation or migration evidence first. | Another identity key answers for the pinned Onion | That the new key is legitimate | client-model "pin states for withdrawal…"; announced through the alert region |
| PIN.NOT_IN_INDEX | `NOT_IN_INDEX` | Pinned identity absent from this loaded view. Load the directory to check this pin. | Nothing about the pin is loaded yet | Any problem with the Service | client.spec "local search…" (after reload); client-model "pin states…" |
| PIN.STORAGE | storage note | Pins saved in this browser profile for this page only. They are not sent to Nodes or shared with other Client Hosts. Tor Browser deletes them when it closes or on New Identity; use Export pins to keep a copy. | Where pins live | That pins survive Tor Browser restarts or exist on other hosts | client.spec "local search…", "saved pins that cannot be read…" |

## Results

| Copy ID | State | English reference | Meaning | Must not imply | Tests |
| --- | --- | --- | --- | --- | --- |
| RESULT.SAME_NAME_OTHER_KEY | name equals a pinned label, other key | Warning: Same name as your pinned "LABEL", but a different identity key. This is not the Service you pinned; compare the key and the onion address. | Self-declared names can be copied; keys cannot | That the other identity is malicious | client-model "a pinned name under another identity key…" |
| RESULT.PINNED_LEADS_GROUP | group contains your pin | Pinned locally / Your pinned identity | Your pinned key leads its group of similar metadata | That the other entries are fake | result-groups "the pinned identity leads its group…" |
| RESULT.ADDRESS_CONFLICT | several identities claim one Onion | Warning: Different Service identities claim this onion address. A pinned key is never replaced by source agreement. | Conflicting signed claims exist | Which claim is right | client-model "pin states for withdrawal…" |
| RESULT.FORKED | fork | Warning: Fork evidence is retained. Faster responses or more Nodes do not resolve it. | Two signed branches exist | That a majority decides | client-model "pins follow… fork" |
| RESULT.EVIDENCE | published | Service-key signature validated · revision N | The Manifest's signature verifies under its key | Content safety, operator identity, uptime | client.spec |
| RESULT.HOST_PRESENCE | every result | Host presence is not confirmed by this browser. N signed Node statements are claims, not independent proof. | The browser did not contact the Service | That N Nodes are independent | client.spec |
| RESULT.DIRECTION | direction controls in metadata | Note: Metadata contains direction-changing characters. Compare the full Onion and identity key. | Displayed order may differ from the signed text | Fraud | client-model "direction controls and mixed writing systems…" |
| RESULT.INVISIBLE | default-ignorable or tag characters | Note: Metadata contains invisible characters, which can make different names look the same. Compare the full Onion and identity key. | Two names may look identical but differ | Fraud | client-model "invisible characters are reported…" |
| RESULT.MIXED_SCRIPT | Latin with Cyrillic or Greek in a name | Note: The name mixes visually similar writing systems. Names are self-declared; compare the full Onion and identity key. | Possible look-alike name | Fraud | client-model "direction controls and mixed writing systems…" |
| RESULT.COVERAGE | after a load | Current revision included by A of B loaded sources. Details: supplied by …, X still serves revision N, not listed by … | Which loaded Node snapshots contain this revision | That the agreeing Nodes are independent or that a majority is right | client.spec "results and sources show where the data came from…"; client-model "the view says which source…" |
| RESULT.ONLY_QUARANTINED | every origin of the current Manifest set aside later | Note: Only Nodes that were set aside for conflicting signed data supplied this entry. Treat it with care until another source confirms it. | The Manifest signature is valid, but no trusted source delivered it | That the Service itself is bad | client-model "the view says which source…" |
| RESULT.OBSERVERS | details | Signed direct observations of this revision by N Node key(s), latest TIME: KEYS. Several keys can belong to one operator. | Nodes that fetched the Manifest themselves | Independent confirmation | client-model "the view says which source…" |
| RESULT.KEY_MEANING | details | Identity key: the Service's own signing key. It is neither the Tor key of the onion address nor proof of who runs the Service. | What the shown key is | Real-world identity | client.spec "keyboard focus…" (details open) |

## Sources

| Copy ID | State | English reference | Meaning | Must not imply | Tests |
| --- | --- | --- | --- | --- | --- |
| SOURCE.VERIFIED | `COMPLETE` | ✓ Verified | Signed checkpoint and chunks verified under the Node's key | That the Node is honest or independent | client.spec |
| SOURCE.STALE | `STALE` | ! Verified, but old · Old checkpoint or clock discrepancy. Historical signatures remain evidence, not freshness. | Older than seven days or from the future | Compromise | source-loader tests |
| SOURCE.PARTIAL | `PARTIAL` | ! Partly verified · History verification is incomplete… | Bounded history steps remain | Failure | source-loader tests |
| SOURCE.FAILED | `FAILED` | ! Not loaded · plain-language reason from messages.ts and the code | Nothing from this attempt was used | Compromise (ordinary outages are ATTENTION) | client.spec "a failed refresh…" |
| SOURCE.QUARANTINED | `QUARANTINED` | ⚠ Set aside: conflicting signed data | Equivocation, rollback, broken chain or key change | That the Node's operator is malicious | source-loader, source-witnesses tests |
| SOURCE.CANCELLED | `CANCELLED` | ! Cancelled · You cancelled loading before this source finished. Nothing from it changed. | The visitor's own action | Failure | client.spec "cancel marks…" |
| SOURCE.CHECKPOINT | loaded source | Signed checkpoint generation G, created TIME (AGE). | How old the Node's signed state is | That the data is current beyond that time | client.spec "results and sources show where the data came from…" |
| SOURCE.EVIDENCE | quarantined source with two conflicting records | Save the conflicting signed records → a local JSON bundle of exactly those two records | Reportable proof of equivocation | That the file contains pins or searches (it does not) | worker op `export-evidence` |
| SOURCE.HOST_KEY | source with a key in `source_node_keys` | Expected Node key for source N, provided by this Client Host (read-only) | The host vouches for this Node key; another key is refused as SOURCE_NODE_KEY_CHANGED | That the host is more trustworthy than its code | client.spec "expected Node keys from the Client Host…" |
| SOURCE.BASELINE | first contact | First-contact baseline; earlier Node checkpoint history is not proven. | Rollback protection starts now | A problem | client.spec |
| COVERAGE.LIMITED | fewer than three fresh keys | Note: Limited source coverage. Multiple keys still do not guarantee independent operators. Identity pins do not depend on a vote. | Few sources compared | That three keys mean three operators | client.spec |
| LOAD.SUMMARY | a load ends | Loading finished: N of M sources verified, … / Loading finished without a verified source (…). The last verified local index stays available. | What the last load achieved | — | client.spec "keyboard focus…" |

## Privacy and boundaries

| Copy ID | Where | English reference | Must not imply | Tests |
| --- | --- | --- | --- | --- |
| PRIVACY.SEARCH | result summary | Searched on this device; the reference client sends no search terms. | That a modified page could not send them | client.spec "local search…" (no request while typing) |
| PRIVACY.HOST | host notice | The Client Host serves this page's code. The reference client sends it no search terms or pins, but a modified page could. Self-host a verified release for stronger separation from data Nodes. | That multi-source verification protects against a malicious host | — |
| PRIVACY.RELAY | source panel, relay profile | This Client Host fetches the Nodes' public signed data for you over Tor. It learns when you load the directory and which Nodes you load, never your searches or pins. | That the host learns nothing | client.spec |
| PRIVACY.VISITOR_NODES | visitor form | Get both values from the Node's operator through a channel you trust, not from the Node itself. This Client Host fetches the Node's public signed data for you over Tor: it learns which Nodes you add, never your searches or pins. | — | client.spec "visitor-chosen Nodes…" |
| STARTUP.NO_JS | without scripting | Local search needs JavaScript … No search has been sent. … A server search, if offered below, is a separate option that sends your query to this Node. | That the page works without JavaScript | client.spec "without JavaScript…" |

## Not yet done

The human comprehension study (spec 31 section 30), a manual screen-reader and
forced-colours review, and Tor Browser Android checks are open; see
[accessibility notes](../browser/accessibility.md).
