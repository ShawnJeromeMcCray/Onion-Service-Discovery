-------------------- MODULE OnionServiceDiscovery --------------------
EXTENDS Naturals, FiniteSets, Sequences, TLC
CONSTANTS UnsafeRollback, NetworkSuccessful
VARIABLE state
vars == <<state>>
Keys == {"K1", "K2", "K3"}
Make(kind, rev, key, onion, previous, target, published) ==
  [kind |-> kind, rev |-> rev, key |-> key, onion |-> onion,
   previous |-> previous, target |-> target, published |-> published]
Data == [
  g |-> Make("manifest", 1, "K1", "A", "none", "none", TRUE),
  m2 |-> Make("manifest", 2, "K1", "A", "g", "none", TRUE),
  f2 |-> Make("manifest", 2, "K1", "A", "g", "none", TRUE),
  m3 |-> Make("manifest", 3, "K1", "A", "m2", "none", TRUE),
  rot |-> Make("rotation", 2, "K1", "A", "g", "K2", TRUE),
  rot2 |-> Make("rotation", 2, "K1", "A", "g", "K3", TRUE),
  rotated |-> Make("manifest", 2, "K2", "A", "g", "none", TRUE),
  mig |-> Make("migration", 2, "K1", "A", "g", "B", TRUE),
  mig2 |-> Make("migration", 2, "K1", "A", "g", "C", TRUE),
  migrated |-> Make("manifest", 2, "K1", "B", "g", "none", TRUE),
  combo |-> Make("manifest", 2, "K2", "B", "g", "none", TRUE),
  withdrawn |-> Make("manifest", 2, "K1", "A", "g", "none", FALSE),
  obs |-> Make("remote_observation", 1, "NODE", "A", "g", "none", TRUE),
  forged |-> Make("invalid_signature", 2, "K3", "A", "g", "none", TRUE)]
Records == DOMAIN Data
ValidRecords == Records \ {"forged"}
Manifests == {r \in Records : Data[r].kind = "manifest"}
Parents(r) == {p \in Manifests : p = Data[r].previous /\ Data[p].rev + 1 = Data[r].rev}
RotationAllowed(p, r, E) ==
  Data[p].key = Data[r].key \/
  \E t \in E : Data[t].kind = "rotation" /\ Data[t].previous = p /\
    Data[t].rev = Data[r].rev /\ Data[t].key = Data[p].key /\
    Data[t].onion = Data[p].onion /\ Data[t].target = Data[r].key
MigrationAllowed(p, r, E) ==
  Data[p].onion = Data[r].onion \/
  \E t \in E : Data[t].kind = "migration" /\ Data[t].previous = p /\
    Data[t].rev = Data[r].rev /\ Data[t].key = Data[p].key /\
    Data[t].onion = Data[p].onion /\ Data[t].target = Data[r].onion
RECURSIVE Belongs(_, _, _)
Belongs(r, k, E) ==
  IF r \notin E \/ r \notin Manifests THEN FALSE
  ELSE IF Data[r].rev = 1 THEN Data[r].key = k
  ELSE \E p \in Parents(r) : Belongs(p, k, E) /\
      RotationAllowed(p, r, E) /\ MigrationAllowed(p, r, E)
History(k, E) == {r \in Manifests : Belongs(r, k, E)}
Max(S) == CHOOSE m \in S : \A n \in S : m >= n
Highest(k, E) == IF History(k, E) = {} THEN 0 ELSE Max({Data[r].rev : r \in History(k, E)})
RevisionFork(k, E) == \E r, s \in History(k, E) : r # s /\ Data[r].rev = Data[s].rev
CompetingTransitions(k, E) ==
  \E p \in History(k, E), a, b \in E :
    a # b /\ Data[a].kind = Data[b].kind /\
    Data[a].kind \in {"rotation", "migration"} /\
    Data[a].previous = p /\ Data[b].previous = p /\
    Data[a].rev = Data[p].rev + 1 /\ Data[b].rev = Data[p].rev + 1 /\
    Data[a].key = Data[p].key /\ Data[b].key = Data[p].key /\
    Data[a].onion = Data[p].onion /\ Data[b].onion = Data[p].onion /\
    Data[a].target # Data[b].target
Forked(k, E) == RevisionFork(k, E) \/ CompetingTransitions(k, E)
ForkedKeys(E) == {k \in Keys : Forked(k, E)}
Current(k, E) ==
  IF Highest(k, E) = 0 \/ Forked(k, E) THEN "none"
  ELSE CHOOSE r \in History(k, E) : Data[r].rev = Highest(k, E)
AfterEvidence(s, E) == [s EXCEPT
  !.evidence = E,
  !.highWater = [k \in Keys |-> Max({s.highWater[k], Highest(k, E)})],
  !.everForks = s.everForks \cup ForkedKeys(E)]
Rows == <<"g", "m2">>
Prefix(n) == {Rows[i] : i \in 1..n}
Init == \E addressValid \in BOOLEAN : state = [
  evidence |-> {}, highWater |-> [k \in Keys |-> 0], everForks |-> {},
  cursor |-> 0, committedRows |-> {}, pendingRows |-> {}, pendingCursor |-> 0,
  candidate |-> "RECEIVED", addressValid |-> addressValid, attempted |-> FALSE,
  directSucceeded |-> FALSE, localDirect |-> FALSE, importedObservation |-> FALSE,
  retryDelay |-> 0, pinRoot |-> "K1", pinContext |-> "A", votes |-> 0, offline |-> FALSE]
Receive == \E r \in ValidRecords : state' = AfterEvidence(state, state.evidence \cup {r})
StaleSnapshot ==
  IF UnsafeRollback THEN state' = [state EXCEPT !.evidence = {"g"}]
  ELSE state' = AfterEvidence(state, state.evidence \cup {"g"})
BeginSync == \E n \in 1..Len(Rows) : state.pendingCursor = 0 /\ n > state.cursor /\
  state' = [state EXCEPT !.pendingRows = Prefix(n), !.pendingCursor = n]
CommitSync == state.pendingCursor > 0 /\
  LET s == AfterEvidence(state, state.evidence \cup state.pendingRows)
  IN state' = [s EXCEPT !.cursor = state.pendingCursor,
    !.committedRows = s.committedRows \cup state.pendingRows,
    !.pendingRows = {}, !.pendingCursor = 0]
CrashBeforeCommit == state.pendingCursor > 0 /\
  state' = [state EXCEPT !.pendingRows = {}, !.pendingCursor = 0]
AdmitCandidate == state.candidate = "RECEIVED" /\
  state' = [state EXCEPT !.candidate = IF state.addressValid THEN "ADDRESS_VALIDATED" ELSE "REJECTED"]
QueueCandidate == state.candidate = "ADDRESS_VALIDATED" /\
  state' = [state EXCEPT !.candidate = "QUEUED"]
StartDirectCheck == state.candidate = "QUEUED" /\ state.addressValid /\
  state' = [state EXCEPT !.candidate = "DIRECT_CHECK_PENDING", !.attempted = TRUE]
FinishDirectCheck == state.candidate = "DIRECT_CHECK_PENDING" /\
  IF NetworkSuccessful THEN
    state' = [state EXCEPT !.candidate = "VERIFIED", !.directSucceeded = TRUE, !.localDirect = TRUE]
  ELSE state' = [state EXCEPT !.candidate = "BACKOFF", !.retryDelay = 1]
ImportObservation == state' = [state EXCEPT !.importedObservation = TRUE]
NodeVotes == state.votes < 1 /\ state' = [state EXCEPT !.votes = @ + 1]
LivenessFailure == state' = [state EXCEPT !.offline = TRUE]
FollowMigration == Current("K1", state.evidence) # "none" /\
  Data[Current("K1", state.evidence)].onion # state.pinContext /\
  state' = [state EXCEPT !.pinContext = Data[Current("K1", state.evidence)].onion]
Next == Receive \/ StaleSnapshot \/ BeginSync \/ CommitSync \/ CrashBeforeCommit \/
  AdmitCandidate \/ QueueCandidate \/ StartDirectCheck \/ FinishDirectCheck \/
  ImportObservation \/ NodeVotes \/ LivenessFailure \/ FollowMigration
Spec == Init /\ [][Next]_vars
TypeOK == state.evidence \subseteq ValidRecords /\ state.cursor \in 0..2 /\
  state.pendingCursor \in 0..2 /\ state.committedRows \subseteq ValidRecords /\
  state.candidate \in {"RECEIVED", "ADDRESS_VALIDATED", "QUEUED", "DIRECT_CHECK_PENDING", "VERIFIED", "BACKOFF", "REJECTED"}
NoRevisionDowngrade == \A k \in Keys : Highest(k, state.evidence) >= state.highWater[k]
ForkEvidencePreserved == state.everForks \subseteq ForkedKeys(state.evidence)
NoCursorWithoutData == Prefix(state.cursor) \subseteq (state.committedRows \cap state.evidence)
NoUnauthorizedKeyChange == \A k \in Keys : \A r \in History(k, state.evidence) :
  Data[r].rev = 1 \/ (\E p \in Parents(r) : Belongs(p, k, state.evidence) /\ RotationAllowed(p, r, state.evidence))
NoUnauthorizedMigration == \A k \in Keys : \A r \in History(k, state.evidence) :
  Data[r].rev = 1 \/ (\E p \in Parents(r) : Belongs(p, k, state.evidence) /\ MigrationAllowed(p, r, state.evidence))
NoInvalidAddressTransport == state.attempted => state.addressValid
VerifiedRequiresDirectCheck == state.candidate = "VERIFIED" => state.directSucceeded
ImportedIsNotLocal == state.localDirect => state.directSucceeded
PinRootNotPeerControlled == state.pinRoot = "K1"
NoZeroDelayBackoff == state.candidate = "BACKOFF" => state.retryDelay >= 1
PinMigrationAuthorized == state.pinContext = "A" \/
  (\E r \in state.evidence : Data[r].kind = "migration" /\ Data[r].key = "K1" /\
    Data[r].onion = "A" /\ Data[r].target = state.pinContext)
BoundedExploration == Cardinality(state.evidence) <= 4
=============================================================================
