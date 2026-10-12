"""One-command controlled lab; prerequisites are installed separately, no live Tor."""
import argparse,json,subprocess,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
if __name__=='__main__':
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--php-container',action='store_true');parser.add_argument('--browser',action='store_true');parser.add_argument('--formal',action='store_true');args=parser.parse_args()
 topology=json.loads((ROOT/'lab/topologies/controlled.json').read_text())
 if set(topology)!={'topology_version','profile','source_nodes','service_identities','client_hosts','storage_backends','network','synthetic_fixture','failure_schedule'} or topology['topology_version']!=1 or topology['source_nodes']!=3 or topology['service_identities']!=4:raise SystemExit('Controlled topology rejected')
 print('CONTROLLED DEVELOPMENT LAB: synthetic data; no live Tor or production certification',flush=True)
 jobs=[[sys.executable,'tools/run_input_conformance.py']+(['--php-container']if args.php_container else []),[sys.executable,'release-tools/test_release_verifier.py']]
 if args.browser:jobs.append(['npm','--prefix','web-local-search-client','run','test:browser'])
 if args.formal:jobs.append([sys.executable,'tools/check_formal_model.py'])
 for command in jobs:
  result=subprocess.run(command,cwd=ROOT)
  if result.returncode:raise SystemExit(result.returncode)
 print('PASS: controlled lab checks only; actual Tor, physical faults and external review remain open',flush=True)
