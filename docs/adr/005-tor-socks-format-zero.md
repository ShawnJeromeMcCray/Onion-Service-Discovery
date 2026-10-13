# ADR 005: explicit Tor SOCKS extension format zero

Status: implemented development hardening; D-042 human Tor/security review and
deployed/live Tor evidence remain open.
Date: 2026-10-13

The official [Tor SOCKS specification](https://spec.torproject.org/socks-extensions.html)
was refreshed on 2026-10-13. It recommends format zero for new clients instead of
legacy username/password isolation. The previous transport generated two random
legacy fields.

Transport now sends username `<torS0X>0` and a fresh 128-bit random hex password
as the per-request isolation parameter. No RPC/user identity or persistent token
is supplied. Requests receive different parameters. Loopback-only SOCKS remote
hostname resolution, fixed endpoints, redirect refusal, HTTP-only transport,
byte/time ceilings and ambient-proxy rejection remain enforced. Signed Protocol
bytes and key/storage formats do not change.

Legacy format can fail on listeners rejecting it. Tor Control Port integration
adds privileges unnecessarily. Purpose-shared tokens could reduce circuit churn
but widen linkability; per-request isolation remains pending measured review.

The official document states support since Tor 0.4.9.1-alpha and Arti 1.2.8, with
older implementations interpreting format zero as legacy isolation. This is a
documented compatibility expectation, not an executed runtime matrix. Controlled
tests inspect exact SOCKS bytes and reject reuse. Actual daemon/deployed circuit
behavior still needs release evidence.
