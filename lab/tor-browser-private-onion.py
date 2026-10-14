"""Actual headless Tor Browser over isolated real Tor; no public/privacy certification."""
from __future__ import annotations
import argparse
import json
from pathlib import Path
import signal
import socket
import subprocess
import sys
import tempfile
import time
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'validator-python'))
from onion_address import validate_onion_address

ELEMENT = 'element-6066-11e4-a52e-4f735466cecf'


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--browser', type=Path, required=True)
    parser.add_argument('--driver', type=Path, required=True)
    parser.add_argument('--onion', required=True)
    parser.add_argument('--socks-port', type=int, required=True)
    parser.add_argument('--expected-node-key', required=True)
    parser.add_argument('--server-log', type=Path, required=True)
    args = parser.parse_args()
    validate_onion_address(args.onion)
    if not 1 <= args.socks_port <= 65535:
        raise ValueError('invalid SOCKS port')
    routes = Path('/proc/net/route').read_text().splitlines()[1:]
    if any(line.split()[1] == '00000000' for line in routes):
        raise RuntimeError('external default route forbidden')
    with socket.socket() as listener:
        listener.bind(('127.0.0.1', 0))
        port = listener.getsockname()[1]
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    def terminate(*_: object) -> None:
        raise RuntimeError('private browser driver terminated')
    signal.signal(signal.SIGTERM, terminate)
    session = None
    with tempfile.TemporaryDirectory(prefix='osd-private-onion-browser-') as tmp:
        with (Path(tmp) / 'driver.log').open('wb') as log:
            driver = subprocess.Popen([str(args.driver.resolve()), '--host', '127.0.0.1', '--port', str(port)], stdout=log, stderr=log)
            try:
                def call(method: str, path: str, value: object | None = None, timeout: int = 60) -> object:
                    body = None if value is None else json.dumps(value).encode()
                    req = urllib.request.Request(f'http://127.0.0.1:{port}' + path, data=body, method=method, headers={'Content-Type': 'application/json'})
                    with opener.open(req, timeout=timeout) as response:
                        return json.load(response)['value']
                end = time.monotonic() + 10
                while True:
                    try:
                        call('GET', '/status')
                        break
                    except OSError:
                        if time.monotonic() > end:
                            raise RuntimeError('geckodriver startup failed') from None
                        time.sleep(.1)
                versions = []
                secure_context = None
                for scripting in (True, False):
                    value = call('POST', '/session', {'capabilities': {'alwaysMatch': {'browserName': 'firefox', 'moz:firefoxOptions': {'binary': str(args.browser.resolve()), 'args': ['-headless'], 'env': {'TOR_SKIP_LAUNCH': '1', 'TOR_SKIP_CONTROLPORTTEST': '1'}, 'prefs': {'javascript.enabled': scripting, 'network.proxy.type': 1, 'network.proxy.socks': '127.0.0.1', 'network.proxy.socks_port': args.socks_port, 'network.proxy.socks_version': 5, 'network.proxy.socks_remote_dns': True, 'network.proxy.no_proxies_on': '', 'extensions.torlauncher.start_tor': False, 'extensions.torlauncher.prompt_at_startup': False}}}}})
                    session = value['sessionId']
                    versions.append(value['capabilities']['browserVersion'])
                    base = f'/session/{session}'
                    def script(code: str, arguments: list | None = None) -> object:
                        return call('POST', base + '/execute/sync', {'script': code, 'args': arguments or []})
                    def until(code: str) -> None:
                        end = time.monotonic() + 60
                        while not script(code):
                            if time.monotonic() > end:
                                summary = script("return {source:document.querySelector('#source-statuses')?.textContent,operation:document.querySelector('#operation-status')?.textContent,count:document.querySelector('#record-count')?.textContent}")
                                raise RuntimeError('private onion browser assertion timed out: ' + str(summary))
                            time.sleep(.1)
                    def element(selector: str) -> str:
                        return call('POST', base + '/element', {'using': 'css selector', 'value': selector})[ELEMENT]
                    def click(selector: str) -> None:
                        call('POST', base + '/element/' + element(selector) + '/click', {})
                    call('POST', base + '/url', {'url': 'http://' + args.onion + '/onion-service-discovery/'})
                    if scripting:
                        until("return document.querySelector('#source-0-key')!==null")
                        secure_context = script('return window.isSecureContext')
                        script("document.querySelector('#source-0-key').value=arguments[0];document.querySelector('#limited-coverage').checked=true;document.querySelector('#load-sources').click();return true", [args.expected_node_key])
                        until("return document.querySelectorAll('.source-status strong').length===1&&document.querySelector('.source-status strong').textContent==='COMPLETE'&&document.querySelectorAll('.result-card').length===1")
                        if not script("return document.querySelectorAll('img,iframe,video,audio').length===0&&!document.querySelector('.result-card').textContent.includes('UNVERIFIED')"):
                            raise RuntimeError('unsafe or unverified onion rendering')
                        time.sleep(.5)
                        before = args.server_log.stat().st_size
                        probe = 'private-onion-browser-local-query-probe'
                        script("const q=document.querySelector('#query');q.value=arguments[0];q.dispatchEvent(new Event('input',{bubbles:true}));return true", [probe])
                        until("return document.querySelectorAll('.result-card').length===0")
                        time.sleep(.5)
                        if args.server_log.stat().st_size != before or probe in args.server_log.read_text():
                            raise RuntimeError('local query caused onion HTTP request')
                        script("const q=document.querySelector('#query');q.value='';q.dispatchEvent(new Event('input',{bubbles:true}));return true")
                        until("return document.querySelectorAll('.result-card').length===1")
                        script("Array.from(document.querySelectorAll('button')).find(button=>button.textContent==='Pin this identity').click();return true")
                        until("return document.querySelectorAll('.pin-card').length===1")
                    else:
                        # W3C DOM operations only; no script execution in this profile.
                        content = call('GET', base + '/source')
                        if 'Local verification and search are unavailable with JavaScript disabled' not in content:
                            raise RuntimeError('NoJS boundary not shown')
                        click('a[href="/onion-service-discovery/search/"]')
                        if 'The local-search privacy property does not apply in this mode' not in call('GET', base + '/source'):
                            raise RuntimeError('NoJS disclosure missing')
                        click('button[type="submit"]')
                        if len(call('POST', base + '/elements', {'using': 'css selector', 'value': 'article'})) != 1:
                            raise RuntimeError('NoJS onion POST did not find prepared service')
                        probe = 'private-onion-nojs-query-probe'
                        call('POST', base + '/element/' + element('#query') + '/value', {'text': probe})
                        click('button[type="submit"]')
                        if probe in call('GET', base + '/url') or probe in call('GET', base + '/source') or probe in args.server_log.read_text():
                            raise RuntimeError('NoJS query retained in URL, response or reference log')
                    if call('GET', base + '/cookie'):
                        raise RuntimeError('application cookie unexpectedly set')
                    call('DELETE', base, timeout=5)
                    session = None
                print(json.dumps({'status': 'PASS_DEVELOPMENT', 'browser_versions': versions, 'onion_secure_context_observed': secure_context, 'checks': ['ACTUAL_TOR_BROWSER_REAL_PRIVATE_ONION', 'EXPECTED_NODE_KEY_SIGNED_WORKER_IMPORT', 'LOCAL_QUERY_NO_ONION_HTTP_REQUEST', 'LOCAL_IDENTITY_PIN', 'NOJS_EXPLICIT_DISCLOSURE', 'NOJS_HTML_POST_ON_REAL_ONION', 'QUERY_EXCLUDED_FROM_URL_RESPONSE_REFERENCE_LOG', 'COOKIE_FREE_PROFILES'], 'profile': 'headless verified Tor Browser, isolated real Tor SOCKS, explicit lab direct-source config; external Tor launcher disabled', 'public_tor_verified': False, 'privacy_security_level_certified': False, 'android_verified': False, 'production_approved': False}))
            finally:
                if session is not None:
                    try:
                        call('DELETE', f'/session/{session}', timeout=5)
                    except OSError:
                        pass
                driver.terminate()
                try:
                    driver.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    driver.kill()
                    driver.wait()


if __name__ == '__main__':
    main()
