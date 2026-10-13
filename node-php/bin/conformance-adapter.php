<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use OnionServiceDiscovery\Crypto\CanonicalJson;
use OnionServiceDiscovery\Domain\OnionAddress;
use OnionServiceDiscovery\Protocol\IdentityDeriver;
use OnionServiceDiscovery\Protocol\ProtocolException;
use OnionServiceDiscovery\Protocol\RecordFormat;
use OnionServiceDiscovery\Protocol\RecordVerifier;
use OnionServiceDiscovery\Validation\StrictJson;

$verifier = new RecordVerifier(dirname(__DIR__, 2) . '/protocol/schemas');
$count = 0;
$total = 0;
while (($line = fgets(STDIN, 262146)) !== false) {
    $total += strlen($line);
    if (++$count > 256 || $total > 1048576 || strlen($line) > 262144 || !str_ends_with($line, "\n")) {
        echo '{"status":"FAIL","error":"WORK_BUDGET_EXHAUSTED"}', PHP_EOL;
        exit(3);
    }
    try {
        $request = StrictJson::parse($line, 262144);
        if (array_diff(array_keys(get_object_vars($request)), ['operation', 'input_chunks', 'expected_onion', 'max_bytes']) !== [] || !is_array($request->input_chunks ?? null)) {
            throw new ProtocolException('SCHEMA_INVALID');
        }
        $chunks = $request->input_chunks;
        foreach ($chunks as $chunk) {
            if (!is_string($chunk)) {
                throw new ProtocolException('ENCODING_INVALID');
            }
        }
        $encoded = implode('', $chunks);
        $bytes = base64_decode($encoded, true);
        if ($bytes === false || base64_encode($bytes) !== $encoded) {
            throw new ProtocolException('ENCODING_INVALID');
        }
        if (($request->operation ?? null) === 'validate') {
            $binding = isset($request->expected_onion) ? OnionAddress::parse($request->expected_onion) : null;
            $verified = $verifier->verifyWire($bytes, $binding);
            $record = $verified->document();
            $output = ['status' => 'PASS', 'hash' => $verified->contentHash, 'wire' => $verified->canonicalWire,
                'payload_hex' => bin2hex(CanonicalJson::encode(RecordFormat::payload($record))), 'message_hex' => bin2hex(RecordFormat::message($record))];
        } elseif (($request->operation ?? null) === 'address') {
            $output = ['status' => 'PASS', 'address' => OnionAddress::parse($bytes)->hostname];
        } elseif (($request->operation ?? null) === 'strict-json') {
            if (!is_int($request->max_bytes ?? 16384)) {
                throw new ProtocolException('DOCUMENT_TOO_LARGE');
            }
            $output = ['status' => 'PASS', 'value' => StrictJson::parse($bytes, $request->max_bytes ?? 16384)];
        } elseif (($request->operation ?? null) === 'canonicalize') {
            $output = ['status' => 'PASS', 'canonical_hex' => bin2hex(CanonicalJson::encode(StrictJson::parse($bytes, 65536)))];
        } elseif (($request->operation ?? null) === 'sequence') {
            $sequence = StrictJson::parse($bytes, 1048576);
            if (!is_array($sequence->records ?? null) || !is_int($sequence->max_records ?? 100000)) {
                throw new ProtocolException('SCHEMA_INVALID');
            }
            $records = array_map(static function (mixed $record) use ($verifier) {
                if (!$record instanceof stdClass) {
                    throw new ProtocolException('SCHEMA_INVALID');
                }
                return $verifier->verify($record);
            }, $sequence->records);
            $output = ['status' => 'PASS', 'state' => IdentityDeriver::derive($records, $sequence->max_records ?? 100000)];
        } elseif (($request->operation ?? null) === 'snapshot-membership') {
            $fixture = StrictJson::parse($bytes, 1048576);
            if (!is_array($fixture->local_records ?? null) || !is_array($fixture->chunks ?? null) || !is_array($fixture->checkpoint_wire ?? null) || !is_string($fixture->expected_node_key ?? null)) { throw new ProtocolException('SCHEMA_INVALID'); }
            $history = [];
            foreach ($fixture->local_records as $record) {
                if (!$record instanceof stdClass) { throw new ProtocolException('SCHEMA_INVALID'); }
                $history[] = $verifier->verify($record);
            }
            foreach ($fixture->chunks as $wire) { if (!is_string($wire)) { throw new ProtocolException('SCHEMA_INVALID'); } }
            foreach ($fixture->checkpoint_wire as $part) { if (!is_string($part)) { throw new ProtocolException('SCHEMA_INVALID'); } }
            $output = ['status' => 'PASS', 'result' => (new \OnionServiceDiscovery\Protocol\SnapshotMembership($verifier))->verify(implode('', $fixture->checkpoint_wire), $fixture->chunks, $fixture->expected_node_key, $history)];
        } else {
            throw new ProtocolException('USAGE_ERROR');
        }
    } catch (InvalidArgumentException $error) {
        $output = ['status' => 'FAIL', 'error' => $error->getMessage()];
    } catch (Throwable) {
        $output = ['status' => 'FAIL', 'error' => 'INTERNAL_ERROR'];
    }
    echo json_encode($output, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
}
