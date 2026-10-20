<?php
declare(strict_types=1);
// Spec 32 section 10: every doctor and security:check report the tests produce must match the
// published health-report schema, so a new field (and any private value in it) needs a schema change.
require_once __DIR__ . '/../vendor/autoload.php';

/** @param array<string,mixed> $report */
function assertHealthReport(array $report): void
{
    static $validator = null; static $schema = null;
    $validator ??= new \Opis\JsonSchema\Validator();
    $schema ??= json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/docs/operations/health-report.schema.json'), false, 64, JSON_THROW_ON_ERROR);
    $result = $validator->validate(json_decode(json_encode($report, JSON_THROW_ON_ERROR), false, 64, JSON_THROW_ON_ERROR), $schema);
    if (!$result->isValid()) {
        $error = $result->error();
        throw new RuntimeException('health report does not match docs/operations/health-report.schema.json: ' . json_encode((new \Opis\JsonSchema\Errors\ErrorFormatter())->format($error), JSON_THROW_ON_ERROR));
    }
}
