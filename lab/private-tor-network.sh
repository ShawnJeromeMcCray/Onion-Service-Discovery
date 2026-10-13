#!/bin/sh
# Execute only inside Docker --network none with trusted Tor and Chutney binaries.
set -eu
: "${CHUTNEY_PATH:?Set CHUTNEY_PATH to the reviewed Chutney checkout}"
: "${CHUTNEY_TOR:?Set CHUTNEY_TOR to the trusted Tor executable}"
: "${CHUTNEY_TOR_GENCERT:?Set CHUTNEY_TOR_GENCERT to the trusted tor-gencert executable}"
if [ ! -r /proc/net/route ]; then exit 2; fi
if awk 'NR>1 && $2=="00000000" {found=1} END {exit !found}' /proc/net/route; then
    echo 'External default route forbidden; use Docker --network none.' >&2
    exit 2
fi
osd_project=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
osd_net=$(mktemp -d /tmp/osd-private-tor-net.XXXXXXXX)
export CHUTNEY_DATA_DIR="$osd_net"
export CHUTNEY_DISABLE_IPV6=1
export CHUTNEY_BOOTSTRAP_TIME=120
osd_chutney="$CHUTNEY_PATH/chutney"
cleanup() {
    "$osd_chutney" stop >/dev/null 2>&1 || true
    rm -rf -- "$osd_net"
}
trap cleanup EXIT INT TERM
"$osd_chutney" init --net hs-v3-min
"$osd_chutney" configure
# The application transport intentionally fixes Onion HTTP to port 80.
sed -i 's/HiddenServicePort 5858 /HiddenServicePort 80 /' "$osd_net/nodes/008h/torrc"
"$osd_chutney" start --launch-phase 1
"$osd_chutney" wait_for_bootstrap --launch-phase 1
"$osd_chutney" start --launch-phase 2
"$osd_chutney" wait_for_bootstrap --launch-phase 2
php "$osd_project/lab/private-tor-onion.php" "$osd_net/nodes/008h/hidden_service/hostname" 9007 4747
