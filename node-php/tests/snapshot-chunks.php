<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use OnionServiceDiscovery\Crypto\CanonicalJson;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Protocol\SnapshotChunkVerifier;
use OnionServiceDiscovery\Protocol\RecordFormat;
$root = dirname(__DIR__, 2); $verifier = new RecordVerifier($root . '/protocol/schemas');
$f = json_decode(file_get_contents($root . '/protocol/test-vectors/signed-records.json'), false, 64, JSON_THROW_ON_ERROR);
foreach ($f->cases as $case) { if ($case->id === 'snapshot-with-chunk') { $checkpoint = $verifier->verifyWire(base64_decode($case->input_base64, true)); break; } }
$records = [$f->stories->genesis, $f->stories->successor]; usort($records, static fn (stdClass $a, stdClass $b): int => strcmp(RecordFormat::contentHash($a), RecordFormat::contentHash($b)));
$wire = CanonicalJson::encode($records); $validator = new SnapshotChunkVerifier($verifier); $result = $validator->verify($wire, $checkpoint, 0);
if (count($result['records']) !== 2) { throw new RuntimeException('chunk interoperability'); }
try { $validator->verify($wire . ' ', $checkpoint, 0); throw new RuntimeException('tampered chunk accepted'); } catch (InvalidArgumentException $e) { if ($e->getMessage() !== 'SNAPSHOT_CHUNK_HASH_INVALID') { throw $e; } }
try { $validator->verify($wire, $checkpoint, 0, 'sha256:' . str_repeat('f', 64)); throw new RuntimeException('unordered history accepted'); } catch (InvalidArgumentException $e) { if ($e->getMessage() !== 'SNAPSHOT_RECORD_ORDER_INVALID') { throw $e; } }
echo "PASS: independent Python-generated chunk bytes and order/hash rejection\n";
