<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Http;

/** Static templates and context-escaped public facts only; no remote requests. */
final class StatusPage
{
    /**
     * @param array<string,mixed> $facts
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    public static function render(array $facts, string $method = 'GET'): array
    {
        $headers = ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff'];
        if (!in_array($method, ['GET','HEAD'], true)) { return ['status' => 405, 'headers' => $headers + ['Allow' => 'GET, HEAD'], 'body' => 'Method not allowed']; }
        $css = 'body{font:1rem system-ui,sans-serif;max-width:52rem;margin:0 auto;padding:1.25rem;line-height:1.6;background:#111827;color:#f3f4f6}h1,h2{line-height:1.3}section{border:1px solid #6b7280;border-radius:.5rem;padding:1rem;margin:1rem 0}dt{font-weight:bold}dd{margin:0 0 1rem;overflow-wrap:anywhere}a{color:#c4b5fd}a:focus{outline:3px solid #fff}code{overflow-wrap:anywhere}.answer{font-size:1.25rem}.state{display:inline-block;min-width:9.5rem;font-family:monospace;font-weight:bold}li{margin:.5rem 0}';
        $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'sha256-" . base64_encode(hash('sha256', $css, true)) . "'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'";
        $headers['Permissions-Policy'] = 'camera=(), microphone=(), geolocation=()';
        $overall = is_string($facts['overall'] ?? null) ? $facts['overall'] : 'NOT_CONFIGURED';
        $answer = is_string($facts['answer'] ?? null) ? $facts['answer'] : 'NO';
        $action = is_string($facts['next_action'] ?? null) ? $facts['next_action'] : 'Onion Service Discovery is installed but not configured. Follow the installation guide and configure this installation locally.';
        $body = '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Onion Service Discovery — Service status</title><style>' . $css . '</style><main><h1>Onion Service Discovery</h1>'
            . '<h2>Can this Service currently be found through Onion Service Discovery?</h2><p class="answer"><strong>' . self::escape($answer) . '</strong> — ' . self::escape(is_string($facts['sampled_discoverability'] ?? null) && $answer !== 'NO' && $facts['sampled_discoverability'] !== 'UNCONFIRMED' ? $facts['sampled_discoverability'] : $action) . '</p>'
            . '<p>State: <code>' . self::escape($overall) . '</code>. Discoverability is sampled evidence from checked Discovery Nodes: the exact current revision in a verified complete signed snapshot plus that Node’s own direct observation. It is never a global guarantee.</p>'
            . '<section><h2>Next action</h2><p>' . self::escape($action) . '</p></section>';
        if (is_array($facts['pipeline'] ?? null)) {
            $body .= '<section><h2>Steps</h2><ol>';
            foreach ($facts['pipeline'] as $step => $state) {
                if (is_string($step) && is_string($state)) { $body .= '<li><span class="state">' . self::escape($state) . '</span> ' . self::escape(ucfirst(str_replace('_', ' ', $step))) . '</li>'; }
            }
            $body .= '</ol></section>';
        }
        $body .= '<section><h2>Publication facts</h2><dl>';
        $allowed = ['onion','self_verification','self_verification_checked_at','bootstrap','announcement','announcement_last_at','external_observation','active_index_membership','sampled_discoverability','evidence_checked_at'];
        foreach ($allowed as $field) {
            $value = $facts[$field] ?? null;
            if (is_string($value)) { $body .= '<dt>' . self::escape(str_replace('_', ' ', $field)) . '</dt><dd>' . self::escape($value) . '</dd>'; }
        }
        if (is_array($facts['manifest'] ?? null)) {
            foreach (['state','revision','hash','logical_service_id','current_key','key_epoch','publish_service','continuity'] as $field) {
                $value = $facts['manifest'][$field] ?? null;
                if (is_string($value) || is_int($value) || is_bool($value)) {
                    $body .= '<dt>' . self::escape('manifest: ' . str_replace('_', ' ', $field)) . '</dt><dd>' . self::escape(is_bool($value) ? ($value ? 'ON' : 'OFF') : (string)$value) . '</dd>';
                }
            }
        }
        $body .= '</dl></section>';
        if (is_array($facts['node'] ?? null)) {
            $node = $facts['node'];
            $body .= '<section><h2>Discovery Node</h2>';
            if (is_string($node['node_onion'] ?? null) && is_string($node['node_identity_public_key'] ?? null)) {
                $body .= '<p>Publishers, peers and visitors need both values below to use this Node. Give them through a channel they trust; they must never take the key from the Node’s own answers.</p>';
            }
            $body .= '<dl>';
            $labels = ['node_onion' => 'Node onion', 'node_identity_public_key' => 'Node public key', 'identity' => 'Identity', 'storage_backend' => 'Storage backend', 'storage_state' => 'Storage',
                'signed_records' => 'Signed records', 'snapshot' => 'Snapshot', 'snapshot_generation' => 'Snapshot generation', 'snapshot_created_at' => 'Snapshot created at',
                'last_direct_verification_at' => 'Last direct verification', 'last_successful_sync_at' => 'Last successful sync'];
            foreach ($labels as $field => $label) {
                $value = $node[$field] ?? null;
                if (is_string($value) || is_int($value)) { $body .= '<dt>' . self::escape($label) . '</dt><dd>' . ($field === 'node_onion' || $field === 'node_identity_public_key' ? '<code>' . self::escape((string) $value) . '</code>' : self::escape((string) $value)) . '</dd>'; }
            }
            foreach (['candidates' => 'Discovery queue', 'peers' => 'Peers'] as $field => $label) {
                if (!is_array($node[$field] ?? null)) { continue; }
                $parts = [];
                foreach ($node[$field] as $name => $count) { if (is_string($name) && is_int($count)) { $parts[] = strtolower(str_replace('_', ' ', $name)) . ': ' . $count; } }
                $body .= '<dt>' . self::escape($label) . '</dt><dd>' . self::escape($parts === [] ? 'none' : implode(', ', $parts)) . '</dd>';
            }
            $body .= '</dl></section>';
        }
        if (is_array($facts['roles'] ?? null)) {
            $body .= '<section><h2>Roles</h2><dl>';
            foreach (['publish_service' => 'Publish', 'mirror_directory' => 'Mirror', 'discover_services' => 'Discover', 'sync_with_peers' => 'Sync'] as $field => $label) {
                $value = $facts['roles'][$field] ?? null; if (!is_bool($value)) { continue; }
                $explanation = $facts['role_explanations'][$field] ?? null;
                $body .= '<dt>' . self::escape($label . ': ' . ($value ? 'ON' : 'OFF')) . '</dt><dd>' . self::escape(is_string($explanation) ? $explanation : '') . '</dd>';
            }
            $body .= '</dl></section>';
        }
        $body .= '<p>Signature validity establishes technical key evidence, not content safety or operator identity. Several Node keys may belong to one operator.</p><p>Development implementation. Production approval remains open.</p></main></html>';
        return ['status' => 200, 'headers' => $headers, 'body' => $method === 'HEAD' ? '' : $body];
    }
    private static function escape(string $text): string { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'); }
}
