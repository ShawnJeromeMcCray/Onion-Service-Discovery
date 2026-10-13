<?php
declare(strict_types=1);

// Token-based PHP policy: comments/strings are not executable sink evidence.
$root = dirname(__DIR__);
$failures = [];
$forbidden = ['eval','unserialize','exec','system','shell_exec','passthru','proc_open','popen','assert','create_function'];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') { continue; }
    $source = file_get_contents($file->getPathname());
    if ($source === false) { throw new RuntimeException('Source unreadable'); }
    $tokens = token_get_all($source, TOKEN_PARSE);
    foreach ($tokens as $index => $token) {
        if (!is_array($token)) { continue; }
        $violation = null;
        if (in_array($token[0], [T_EVAL,T_IS_EQUAL,T_IS_NOT_EQUAL], true)) { $violation = 'DYNAMIC_CODE_OR_LOOSE_COMPARISON'; }
        $name = strtolower(ltrim($token[1], '\\'));
        if (in_array($token[0], [T_STRING,T_NAME_FULLY_QUALIFIED,T_NAME_QUALIFIED], true) && in_array($name, $forbidden, true)) {
            $previous = $index - 1;
            while ($previous >= 0 && is_array($tokens[$previous]) && in_array($tokens[$previous][0], [T_WHITESPACE,T_COMMENT,T_DOC_COMMENT], true)) { --$previous; }
            // PDO::exec with fixed developer SQL is not PHP's process-exec function.
            if ($previous >= 0 && is_array($tokens[$previous]) && in_array($tokens[$previous][0], [T_OBJECT_OPERATOR,T_NULLSAFE_OBJECT_OPERATOR,T_DOUBLE_COLON], true)) { continue; }
            $next = $index + 1;
            while (isset($tokens[$next]) && is_array($tokens[$next]) && in_array($tokens[$next][0], [T_WHITESPACE,T_COMMENT,T_DOC_COMMENT], true)) { ++$next; }
            if (($tokens[$next] ?? null) === '(') { $violation = 'DANGEROUS_CALL'; }
        }
        if ($violation !== null) { $failures[] = substr($file->getPathname(), strlen($root) + 1) . ':' . $token[2] . ':' . $violation; }
    }
}
echo json_encode(['scope' => 'PHP source token sink/comparison policy', 'status' => $failures === [] ? 'PASS' : 'FAIL', 'findings' => $failures], JSON_THROW_ON_ERROR) . "\n";
exit($failures === [] ? 0 : 1);
