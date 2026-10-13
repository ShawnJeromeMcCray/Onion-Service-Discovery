-------------------- MODULE SnapshotMembership --------------------
EXTENDS Naturals, TLC
CONSTANT UnsafeAssertion
VARIABLE artifacts, included, claims
vars == <<artifacts, included, claims>>
Nodes == {"A", "B"}
Proofs == [signed : BOOLEAN, complete : BOOLEAN, exactCurrentTuple : BOOLEAN, active : BOOLEAN]
Valid(p) == p.signed /\ p.complete /\ p.exactCurrentTuple /\ p.active
Init == /\ artifacts = [n \in Nodes |-> [signed |-> FALSE, complete |-> FALSE, exactCurrentTuple |-> FALSE, active |-> FALSE]]
        /\ included = [n \in Nodes |-> FALSE]
        /\ claims = [n \in Nodes |-> FALSE]
Load(n, p) == /\ artifacts' = [artifacts EXCEPT ![n] = p]
              /\ included' = [included EXCEPT ![n] = FALSE]
              /\ UNCHANGED claims
Verify(n) == /\ included' = [included EXCEPT ![n] = Valid(artifacts[n])]
             /\ UNCHANGED <<artifacts, claims>>
NodeAssertion(n) == /\ claims' = [claims EXCEPT ![n] = TRUE]
                    /\ included' = IF UnsafeAssertion THEN [included EXCEPT ![n] = TRUE] ELSE included
                    /\ UNCHANGED artifacts
Next == \E n \in Nodes : (\E p \in Proofs : Load(n, p)) \/ Verify(n) \/ NodeAssertion(n)
Spec == Init /\ [][Next]_vars
TypeOK == /\ artifacts \in [Nodes -> Proofs]
          /\ included \in [Nodes -> BOOLEAN]
          /\ claims \in [Nodes -> BOOLEAN]
NoUnprovedInclusion == \A n \in Nodes : included[n] => Valid(artifacts[n])
=============================================================================
