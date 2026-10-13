"""Functional checks in an actual Tor Browser binary; no public Tor/privacy certification."""
from __future__ import annotations
import argparse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
from pathlib import Path
import subprocess
import tempfile
import threading
import time
import urllib.request

ROOT = Path(__file__).resolve().parents[1]


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--browser', type=Path, required=True)
    parser.add_argument('--driver', type=Path, required=True)
    args = parser.parse_args()
    requests: list[str] = []
    allowed = {'index.html', 'client.js', 'verifier-worker.js', 'client.css', 'demo.json'}

    class Handler(BaseHTTPRequestHandler):
        def log_message(self, *_: object) -> None:
            pass

        def do_GET(self) -> None:
            requests.append(self.path)
            name = self.path.removeprefix('/onion-service-discovery/') or 'index.html'
            if self.path.startswith('/onion-service-discovery/') and name == 'config.json':
                body = json.dumps({'client_config_version': 1, 'mode': 'client-host', 'allowed_sources': [], 'minimum_distinct_keys': 3, 'delivery_profile': 'client-host-proxy'}).encode()
                mime = 'application/json'
            elif self.path.startswith('/onion-service-discovery/') and name in allowed:
                body = (ROOT / 'web-local-search-client/dist' / name).read_bytes()
                mime = {'html': 'text/html', 'js': 'application/javascript', 'css': 'text/css', 'json': 'application/json'}[name.rsplit('.', 1)[1]]
            else:
                self.send_error(404)
                return
            self.send_response(200)
            self.send_header('Content-Type', mime)
            self.send_header('Cache-Control', 'no-store')
            self.send_header('Content-Length', str(len(body)))
            self.end_headers()
            self.wfile.write(body)

    http = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
    thread = threading.Thread(target=http.serve_forever, daemon=True)
    thread.start()
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    session: str | None = None
    with tempfile.TemporaryDirectory(prefix='osd-tor-browser-') as tmp:
        log = Path(tmp) / 'driver.log'
        with log.open('wb') as stream:
            driver = subprocess.Popen([str(args.driver.resolve()), '--host', '127.0.0.1', '--port', '4444'], stdout=stream, stderr=stream)
            try:
                def call(method: str, path: str, payload: object | None = None) -> object:
                    body = None if payload is None else json.dumps(payload).encode()
                    request = urllib.request.Request('http://127.0.0.1:4444' + path, data=body, headers={'Content-Type': 'application/json'}, method=method)
                    with opener.open(request, timeout=60) as response:
                        return json.load(response)['value']

                deadline = time.monotonic() + 10
                while True:
                    try:
                        call('GET', '/status')
                        break
                    except OSError:
                        if time.monotonic() > deadline:
                            raise RuntimeError('geckodriver startup failed') from None
                        time.sleep(.1)
                value = call('POST', '/session', {'capabilities': {'alwaysMatch': {'browserName': 'firefox', 'moz:firefoxOptions': {'binary': str(args.browser.resolve()), 'args': ['-headless'], 'env': {'TOR_SKIP_LAUNCH': '1', 'TOR_SKIP_CONTROLPORTTEST': '1'}, 'prefs': {'network.proxy.type': 0, 'extensions.torlauncher.start_tor': False, 'extensions.torlauncher.prompt_at_startup': False}}}}})
                session = value['sessionId']
                def script(code: str) -> object:
                    return call('POST', f'/session/{session}/execute/sync', {'script': code, 'args': []})
                def until(code: str) -> None:
                    end = time.monotonic() + 30
                    while not script(code):
                        if time.monotonic() > end:
                            raise RuntimeError('Tor Browser functional assertion timed out')
                        time.sleep(.1)
                call('POST', f'/session/{session}/url', {'url': f'http://127.0.0.1:{http.server_port}/onion-service-discovery/'})
                until("return document.querySelector('#load-demo') !== null")
                script("document.querySelector('#load-demo').click(); return true")
                until("return document.querySelectorAll('.result-card').length === 3")
                if not script("return !document.querySelector('#synthetic-notice').hidden && document.querySelectorAll('.visit,img,iframe,video,audio').length === 0"):
                    raise RuntimeError('unsafe demo rendering')
                before = len(requests)
                script("const q=document.querySelector('#query'); q.value='sensitive-local-tor-browser-probe'; q.dispatchEvent(new Event('input',{bubbles:true})); return true")
                until("return document.querySelectorAll('.result-card').length === 0")
                time.sleep(.3)
                if len(requests) != before or any('sensitive-local-tor-browser-probe' in path for path in requests):
                    raise RuntimeError('query caused HTTP request')
                script("const q=document.querySelector('#query'); q.value='library'; q.dispatchEvent(new Event('input',{bubbles:true})); return true")
                until("return document.querySelectorAll('.result-card').length === 1")
                script("Array.from(document.querySelectorAll('button')).find(x=>x.textContent==='Pin this identity').click(); return true")
                until("return document.querySelectorAll('.pin-card').length === 1")
                print(json.dumps({'status': 'PASS_DEVELOPMENT', 'browser_version': value['capabilities']['browserVersion'], 'checks': ['ACTUAL_TOR_BROWSER_LOCAL_CLIENT_RENDER', 'WORKER_SIGNED_DEMO_VERIFICATION', 'LOCAL_QUERY_NO_HTTP_REQUEST', 'TEXT_ONLY_DEMO_NO_VISIT_LINK', 'LOCAL_PIN'], 'profile': 'headless local HTTP; external Tor launcher disabled; direct loopback only', 'public_tor_verified': False, 'privacy_profile_certified': False, 'android_verified': False, 'production_approved': False}))
            finally:
                if session is not None:
                    try:
                        call('DELETE', f'/session/{session}')
                    except OSError:
                        pass
                driver.terminate()
                try:
                    driver.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    driver.kill()
                    driver.wait()
                http.shutdown()
                http.server_close()


if __name__ == '__main__':
    main()
