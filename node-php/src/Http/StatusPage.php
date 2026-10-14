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
        $css = 'body{font:1rem system-ui,sans-serif;max-width:52rem;margin:0 auto;padding:1.25rem;line-height:1.6;background:#111827;color:#f3f4f6}h1,h2{line-height:1.3}section{border:1px solid #6b7280;border-radius:.5rem;padding:1rem;margin:1rem 0}dt{font-weight:bold}dd{margin:0 0 1rem;overflow-wrap:anywhere}a{color:#c4b5fd}a:focus{outline:3px solid #fff}code{overflow-wrap:anywhere}';
        $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'sha256-" . base64_encode(hash('sha256', $css, true)) . "'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'";
        $headers['Permissions-Policy'] = 'camera=(), microphone=(), geolocation=()';
        $overall = is_string($facts['overall'] ?? null) ? $facts['overall'] : 'NOT_CONFIGURED';
        $action = is_string($facts['next_action'] ?? null) ? $facts['next_action'] : 'Follow the installation guide and configure this installation locally.';
        $body = '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Onion Service Discovery — Service status</title><style>' . $css . '</style><main><h1>Onion Service Discovery</h1><h2>Can this Service currently be found?</h2><p><strong>' . self::escape($overall) . '</strong></p><p>Discoverability is sampled evidence from checked Discovery Nodes: the exact current revision in a verified complete signed snapshot plus that Node’s own direct observation. It is never a global guarantee.</p><section><h2>Next action</h2><p>' . self::escape($action) . '</p></section><section><h2>Publication facts</h2><dl>';
        $allowed = ['onion','self_verification','bootstrap','announcement','external_observation','active_index_membership','sampled_discoverability','evidence_checked_at'];
        foreach ($allowed as $field) {
            $value = $facts[$field] ?? 'NOT_CHECKED';
            if (is_string($value)) { $body .= '<dt>' . self::escape(str_replace('_', ' ', $field)) . '</dt><dd>' . self::escape($value) . '</dd>'; }
        }
        foreach (['roles','manifest'] as $group) {
            if (!is_array($facts[$group] ?? null)) { continue; }
            $fields = $group === 'roles' ? ['publish_service','mirror_directory','discover_services','sync_with_peers'] : ['state','revision','hash','current_key','publish_service','continuity','genesis_anchor'];
            foreach ($fields as $field) {
                $value = $facts[$group][$field] ?? null;
                if (is_string($value) || is_int($value) || is_bool($value)) {
                    $body .= '<dt>' . self::escape($group . ': ' . str_replace('_', ' ', $field)) . '</dt><dd>' . self::escape(is_bool($value) ? ($value ? 'ON' : 'OFF') : (string)$value) . '</dd>';
                }
            }
        }
        $body .= '</dl></section><p>Signature validity establishes technical key evidence, not content safety or operator identity. Several Node keys may belong to one operator.</p><p>Development implementation. Production approval remains open.</p></main></html>';
        return ['status' => 200, 'headers' => $headers, 'body' => $method === 'HEAD' ? '' : $body];
    }
    private static function escape(string $text): string { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'); }
}
