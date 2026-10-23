# Admitting Nodes to the default source list (D-026, D-027)

Owner decision D-026 (2026-10-22): the default source list starts empty and is
filled only through a defined admission process. Nobody invents seed addresses,
and no Node is listed without its operator's consent. The criteria and steps
below are the draft of that process (D-027) for the owner and maintainers to
confirm before the first Node is admitted. Until enough Nodes pass, Client Hosts and
visitors choose their sources themselves, as they do today.

The default source list is the set of Discovery Nodes that the reference client
and the deployment templates suggest when a Client Host configures no sources.
It is a convenience for first contact, not a trust authority: every record a
listed Node serves is still verified in the browser, and a listed Node gains no
power over identities, names or rankings.

## Criteria

A Node can be proposed when all of these hold, and stays listed only while they
keep holding:

1. **Operator consent and contact.** The operator asks for the listing, names a
   contact address that is monitored, and accepts the removal rules below.
2. **Stable identity.** The Node has served the same Onion and Node key, with a
   growing signed checkpoint chain, for at least 30 days.
3. **Fresh, complete snapshots.** Its signed checkpoint is never older than seven
   days (decision D-036), and its snapshots verify completely in the reference
   client and in `network:discoverability-check`.
4. **Reference behaviour.** It runs a reviewed release or development package,
   keeps request logging off for public routes (decision D-034) and publishes no
   query-dependent endpoint unless it is the documented optional NoJS search.
5. **No conflicting evidence.** No checkpoint or descriptor equivocation from it
   was ever observed, and it is not in quarantine at any listed Node.

## Independence

At least three Nodes must be listed before the list is shipped at all, and they
must belong to at least three operators who state that they are independent of
each other. No operator may hold more than one third of the listed Nodes. The
project records who operates which Node (operator names may be pseudonyms), so
that concentration stays visible; the client never treats several keys as proof
of independent operators.

## Proposal and key exchange

1. The operator opens an issue with the Node Onion, the Node public key and its
   D-025 fingerprint, the contact and a short description of the hosting.
2. A maintainer obtains the same key a second way, independently of the issue and
   of the Node's own answers: for example a signed message, a call reading the
   fingerprint aloud, or a key published on a site the operator controls.
3. A maintainer checks the criteria with the reference tools and records the
   result in the issue.
4. The change to the list is a pull request that names the issue, the second
   channel used and the checks run. It needs a maintainer approval that is not the
   proposer's own.

## Removal

A Node leaves the list, through an ordinary pull request that names the reason,
when its operator asks, when it misses the freshness criterion for 14 days in a
row, or when its contact stops answering for 30 days. Observed equivocation, a key
change without a new proposal, or query logging removes it at once. A removed Node
can be proposed again after the cause is fixed; a new key is a new Node.

## Current list

Empty. No Node has passed this process yet.
