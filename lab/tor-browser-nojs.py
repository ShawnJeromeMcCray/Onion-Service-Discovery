"""Actual Tor Browser local No-JavaScript workflow; no privacy/public-Tor certification."""
from __future__ import annotations
import argparse
import json
import os
from pathlib import Path
import socket
import subprocess
import tempfile
import time
import urllib.request
ROOT = Path(__file__).resolve().parents[1]
ELEMENT = 'element-6066-11e4-a52e-4f735466cecf'


def port() -> int:
    with socket.socket() as listener:
        listener.bind(('127.0.0.1', 0))
        return listener.getsockname()[1]


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--browser',type=Path,required=True)
    p.add_argument('--driver',type=Path,required=True)
    p.add_argument('--php',default='php')
    args = p.parse_args()
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    with tempfile.TemporaryDirectory(prefix='osd-nojs-browser-') as tmp:
        folder = Path(tmp)
        subprocess.run([args.php,str(ROOT/'lab/server-search-fixture.php'),tmp],check=True,capture_output=True,timeout=30)
        http_port,driver_port = port(),port()
        with (folder/'server.log').open('wb') as http_log,(folder/'driver.log').open('wb') as driver_log:
            env = os.environ.copy()
            env['ONION_SERVICE_DISCOVERY_CONFIG'] = str(folder/'config.json')
            http = subprocess.Popen([args.php,'-S',f'127.0.0.1:{http_port}','-t',str(ROOT/'node-php/public'),str(ROOT/'node-php/public/index.php')],env=env,stdout=http_log,stderr=http_log)
            driver = subprocess.Popen([str(args.driver.resolve()),'--host','127.0.0.1','--port',str(driver_port)],stdout=driver_log,stderr=driver_log)
            session = None
            try:
                def call(method: str,path: str,payload: object | None=None) -> object:
                    data = None if payload is None else json.dumps(payload).encode()
                    req = urllib.request.Request(f'http://127.0.0.1:{driver_port}'+path,data=data,headers={'Content-Type':'application/json'},method=method)
                    with opener.open(req,timeout=60) as response:
                        return json.load(response)['value']
                end = time.monotonic()+10
                while True:
                    try:
                        call('GET','/status')
                        with opener.open(f'http://127.0.0.1:{http_port}/onion-service-discovery/search/',timeout=1):
                            break
                    except OSError:
                        if time.monotonic()>end: raise RuntimeError('local fixture startup failed') from None
                        time.sleep(.1)
                value = call('POST','/session',{'capabilities':{'alwaysMatch':{'browserName':'firefox','moz:firefoxOptions':{'binary':str(args.browser.resolve()),'args':['-headless'],'env':{'TOR_SKIP_LAUNCH':'1','TOR_SKIP_CONTROLPORTTEST':'1'},'prefs':{'javascript.enabled':False,'network.proxy.type':0,'extensions.torlauncher.start_tor':False,'extensions.torlauncher.prompt_at_startup':False}}}}})
                session = value['sessionId']
                base = f'/session/{session}'
                def element(selector: str) -> str:
                    return call('POST',base+'/element',{'using':'css selector','value':selector})[ELEMENT]
                def click(selector: str) -> None:
                    call('POST',base+'/element/'+element(selector)+'/click',{})
                def content() -> str:
                    return call('GET',base+'/source')
                call('POST',base+'/url',{'url':f'http://127.0.0.1:{http_port}/onion-service-discovery/'})
                initial = content()
                if 'Local verification and search are unavailable with JavaScript disabled' not in initial or 'Use server search: your query is sent to this Node' not in initial:
                    raise RuntimeError('No-JS recovery/disclosure unavailable')
                click('a[href="/onion-service-discovery/search/"]')
                if 'The local-search privacy property does not apply in this mode' not in content():
                    raise RuntimeError('server profile was silently selected without disclosure')
                call('POST',base+'/element/'+element('#query')+'/value',{'text':'library'})
                click('button[type="submit"]')
                body = content()
                if '1 matching identities' not in body or 'Synthetic Library' not in body or 'Synthetic Climate Archive' in body:
                    raise RuntimeError('No-JS POST search failed')
                current = call('GET',base+'/url')
                if not current.endswith('/onion-service-discovery/search/server') or 'library' in current:
                    raise RuntimeError('query entered URL/history target')
                call('POST',base+'/element/'+element('#query')+'/value',{'text':'sensitive-nojs-browser-probe-419'})
                click('button[type="submit"]')
                if '0 matching identities' not in content() or 'sensitive-nojs-browser-probe-419' in content():
                    raise RuntimeError('rare query echoed or wrong result')
                if call('GET',base+'/cookie'):
                    raise RuntimeError('No-JS search introduced a cookie')
                http_log.flush()
                if 'sensitive-nojs-browser-probe-419' in (folder/'server.log').read_text():
                    raise RuntimeError('query logged by local HTTP harness')
                print(json.dumps({'status':'PASS_DEVELOPMENT','browser_version':value['capabilities']['browserVersion'],'javascript_enabled':False,'checks':['ACTUAL_TOR_BROWSER_NOJS_RECOVERY_LINK','EXPLICIT_SERVER_PRIVACY_DISCLOSURE','REAL_HTML_FORM_POST_SEARCH','QUERY_NOT_IN_URL_OR_RESPONSE_OR_REFERENCE_LOG','COOKIE_FREE_SEARCH'],'profile':'headless direct loopback in Docker network none; external Tor launcher disabled','public_tor_verified':False,'privacy_security_level_certified':False,'android_verified':False,'production_approved':False}))
            finally:
                if session is not None:
                    try: call('DELETE',f'/session/{session}')
                    except OSError: pass
                for process in [driver,http]:
                    process.terminate()
                    try: process.wait(timeout=10)
                    except subprocess.TimeoutExpired:
                        process.kill()
                        process.wait()


if __name__ == '__main__': main()
