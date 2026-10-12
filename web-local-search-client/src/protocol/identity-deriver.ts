import { assertVerifiedRecord } from './record-verifier.ts';
import { parseStrictJson, type JsonValue } from './strict-json.ts';
import { requiredObject, requiredString, type VerifiedRecord } from './record-verifier.ts';
import type { WireRecord } from './record-format.ts';

export interface DerivedIdentity {
  genesis_key: string;
  highest_revision: number;
  identity_state: 'VALID' | 'FORKED';
  current_hash: string | null;
  current_manifest: WireRecord | null;
  discoverability: 'PUBLISHED' | 'WITHDRAWN' | 'UNKNOWN';
  history_hashes: string[];
  rotation_hashes: string[];
  migration_hashes: string[];
  conflicts: { revision: number; hashes: string[] }[];
  transition_conflicts: { type: string; previous_hash: string; record_hashes: string[] }[];
}
export interface IdentityView { identities: DerivedIdentity[]; pending_hashes: string[]; rejected: { [hash: string]: string }; }
function integer(value: JsonValue | undefined): number {
  if (typeof value !== 'number' || !Number.isSafeInteger(value)) throw new Error('SCHEMA_INVALID');
  return value;
}
function transition(hash: string, revision: number, a: string, b: string, c: string): string {
  return [hash, revision, a, b, c].join('|');
}

/** Derive only from verified evidence. All source and liveness assertions are orthogonal. */
export function deriveIdentities(records: readonly VerifiedRecord[], maxRecords = 100000): IdentityView {
  if (!Number.isSafeInteger(maxRecords) || maxRecords < 1 || maxRecords > 1000000 || records.length > maxRecords) throw new Error('WORK_BUDGET_EXHAUSTED');
  const manifests = new Map<string, WireRecord>();
  const children = new Map<string, Set<string>>();
  const rotations = new Map<string, Set<string>>();
  const migrations = new Map<string, Set<string>>();
  const rotationTargets = new Map<string, Map<string, Set<string>>>();
  const migrationTargets = new Map<string, Map<string, Set<string>>>();
  const addTarget = (map: Map<string, Map<string, Set<string>>>, context: string, target: string, hash: string) => {
    if (!map.has(context)) map.set(context, new Map());
    const targets = map.get(context)!;
    if (!targets.has(target)) targets.set(target, new Set());
    targets.get(target)!.add(hash);
  };
  const add = (map: Map<string, Set<string>>, key: string, hash: string) => {
    if (!map.has(key)) map.set(key, new Set());
    map.get(key)!.add(hash);
  };
  for (const verified of records) {
    assertVerifiedRecord(verified);
    const record = parseStrictJson(new TextEncoder().encode(verified.canonicalWire), 65536);
    const hash = verified.contentHash;
    if (verified.type === 'service_manifest') {
      manifests.set(hash, record);
      if (record.previous_manifest_hash !== null) add(children, requiredString(record.previous_manifest_hash), hash);
    } else if (verified.type === 'service_identity_key_rotation') {
      add(rotations, transition(requiredString(record.previous_state_hash), integer(record.effective_revision), requiredString(record.onion), requiredString(record.old_public_key), requiredString(record.new_public_key)), hash);
      addTarget(rotationTargets, transition(requiredString(record.previous_state_hash), integer(record.effective_revision), requiredString(record.onion), requiredString(record.old_public_key), ''), requiredString(record.new_public_key), hash);
    } else if (verified.type === 'onion_service_migration') {
      add(migrations, transition(requiredString(record.previous_state_hash), integer(record.effective_revision), requiredString(record.from_onion), requiredString(record.to_onion), requiredString(record.service_identity_public_key)), hash);
      addTarget(migrationTargets, transition(requiredString(record.previous_state_hash), integer(record.effective_revision), requiredString(record.from_onion), requiredString(record.service_identity_public_key), ''), requiredString(record.to_onion), hash);
    }
  }
  const states = new Map<string, { revisions: Map<number, Set<string>>; rotations: Set<string>; migrations: Set<string>; highest: number; transitionConflicts: Map<string, { type: string; previous_hash: string; record_hashes: string[] }> }>();
  const ownership = new Map<string, string>();
  const queue: string[] = [];
  for (const [hash, record] of [...manifests].sort(([a], [b]) => a < b ? -1 : a > b ? 1 : 0)) {
    if (record.revision !== 1) continue;
    const identity = requiredString(record.service_identity_public_key);
    ownership.set(hash, identity);
    if (!states.has(identity)) states.set(identity, { revisions: new Map(), rotations: new Set(), migrations: new Set(), highest: 0, transitionConflicts: new Map() });
    queue.push(hash);
  }
  const seen = new Set<string>();
  const rejected: { [hash: string]: string } = Object.create(null) as { [hash: string]: string };
  for (let position = 0; position < queue.length; position++) {
    const hash = queue[position]!;
    if (seen.has(hash)) continue;
    seen.add(hash);
    const record = manifests.get(hash)!;
    const identity = ownership.get(hash)!;
    const state = states.get(identity)!;
    const revision = integer(record.revision);
    if (!state.revisions.has(revision)) state.revisions.set(revision, new Set());
    state.revisions.get(revision)!.add(hash);
    state.highest = Math.max(state.highest, revision);
    if (revision < Number.MAX_SAFE_INTEGER) {
      const context = transition(hash, revision + 1, requiredString(record.onion), requiredString(record.service_identity_public_key), '');
      for (const [kind, targets] of [['KEY_ROTATION_CONFLICT', rotationTargets.get(context)], ['MIGRATION_CONFLICT', migrationTargets.get(context)]] as const) {
        if (targets !== undefined && targets.size > 1) {
          const proofs = [...targets.values()].flatMap(values => [...values]).sort();
          state.transitionConflicts.set(kind + '|' + hash, { type: kind, previous_hash: hash, record_hashes: proofs });
        }
      }
    }
    for (const childHash of [...(children.get(hash) ?? [])].sort()) {
      const child = manifests.get(childHash)!;
      const next = integer(child.revision);
      if (revision === Number.MAX_SAFE_INTEGER || next !== revision + 1) { rejected[childHash] = 'REVISION_CHAIN_BROKEN'; continue; }
      let rotationEvidence: Set<string> | undefined;
      let migrationEvidence: Set<string> | undefined;
      if (child.service_identity_public_key !== record.service_identity_public_key) {
        const matches = rotations.get(transition(hash, next, requiredString(record.onion), requiredString(record.service_identity_public_key), requiredString(child.service_identity_public_key)));
        if (matches === undefined) { rejected[childHash] = 'KEY_ROTATION_INVALID'; continue; }
        rotationEvidence = matches;
      }
      if (child.onion !== record.onion) {
        const matches = migrations.get(transition(hash, next, requiredString(record.onion), requiredString(child.onion), requiredString(record.service_identity_public_key)));
        if (matches === undefined) { rejected[childHash] = 'MIGRATION_INVALID'; continue; }
        migrationEvidence = matches;
      }
      for (const match of rotationEvidence ?? []) state.rotations.add(match);
      for (const match of migrationEvidence ?? []) state.migrations.add(match);
      ownership.set(childHash, identity);
      queue.push(childHash);
    }
  }
  const identities: DerivedIdentity[] = [];
  for (const identity of [...states.keys()].sort()) {
    const state = states.get(identity)!;
    const history: string[] = [];
    const conflicts: { revision: number; hashes: string[] }[] = [];
    for (const revision of [...state.revisions.keys()].sort((a, b) => a - b)) {
      const hashes = [...state.revisions.get(revision)!].sort();
      history.push(...hashes);
      if (hashes.length > 1) conflicts.push({ revision, hashes });
    }
    const transitionConflicts = [...state.transitionConflicts.keys()].sort().map(key => state.transitionConflicts.get(key)!);
    const forked = conflicts.length > 0 || transitionConflicts.length > 0;
    const currentHash = forked ? null : [...state.revisions.get(state.highest)!][0]!;
    const current = currentHash === null ? null : manifests.get(currentHash)!;
    identities.push({ genesis_key: identity, highest_revision: state.highest, identity_state: forked ? 'FORKED' : 'VALID', current_hash: currentHash, current_manifest: current,
      discoverability: current === null ? 'UNKNOWN' : requiredObject(current.discovery).publish_service === true ? 'PUBLISHED' : 'WITHDRAWN',
      history_hashes: history, rotation_hashes: [...state.rotations].sort(), migration_hashes: [...state.migrations].sort(), conflicts, transition_conflicts: transitionConflicts });
  }
  return { identities, pending_hashes: [...manifests.keys()].filter(hash => !seen.has(hash) && rejected[hash] === undefined).sort(), rejected };
}
