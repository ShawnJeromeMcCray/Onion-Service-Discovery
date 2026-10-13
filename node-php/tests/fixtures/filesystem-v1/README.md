# Historical filesystem format 1 fixtures

Generated from the implementation at source revision
04c04189b500132ad4807087a2c501f04c1dd55d, using
public synthetic Genesis/revision-2 records only. No private keys or real data.

`stable` contains two committed revisions. `pending-head` exited after legacy
HEAD publication before its final COMMIT; legacy format-1 semantics regard that
state as committed. `pending-prepare` exited before legacy HEAD publication;
its second revision remains uncommitted. Migration must preserve these historical
interpretations before switching to format 2. Fixtures are copied into private
temporary directories; repository fixture permissions are not deployment policy.
