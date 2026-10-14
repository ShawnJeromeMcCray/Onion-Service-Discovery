"""Research-only RFC 6962 tree consistency; NOT an accepted OSD wire format.

Leaves are opaque bytes. Caller authentication, history availability, checkpoint
signatures, sequence binding and active-index semantics are outside this module.
No signer, HTTP endpoint or runtime imports this experiment.
"""
from hashlib import sha256

MAX_SIZE = 9007199254740991


def node(left, right):
    return sha256(b'\x01' + left + right).digest()


def root(leaves):
    if not leaves:
        return sha256(b'').digest()
    if len(leaves) == 1:
        return sha256(b'\x00' + leaves[0]).digest()
    split = 1 << ((len(leaves) - 1).bit_length() - 1)
    return node(root(leaves[:split]), root(leaves[split:]))


def proof(leaves, old_size):
    if type(old_size) is not int or not 0 <= old_size <= len(leaves) <= MAX_SIZE:
        raise ValueError('SIZE_INVALID')
    if old_size == 0:
        return []

    def sub(items, size, complete):
        if size == len(items):
            return [] if complete else [root(items)]
        split = 1 << ((len(items) - 1).bit_length() - 1)
        if size <= split:
            return sub(items[:split], size, complete) + [root(items[split:])]
        return sub(items[split:], size - split, False) + [root(items[:split])]

    return sub(leaves, old_size, True)


def verify(old_size, new_size, old_root, new_root, hashes):
    if (type(old_size) is not int or type(new_size) is not int
            or not 0 <= old_size <= new_size <= MAX_SIZE
            or not isinstance(hashes, list) or len(hashes) > 64
            or any(type(value) is not bytes or len(value) != 32
                   for value in [old_root, new_root, *hashes])):
        return False
    if old_size == 0:
        return old_root == root([]) and not hashes and (new_size > 0 or new_root == old_root)
    if old_size == new_size:
        return old_root == new_root and not hashes
    first, last = old_size - 1, new_size - 1
    while first & 1:
        first >>= 1
        last >>= 1
    position = 0
    if first == 0:
        old, new = old_root, old_root
    else:
        if not hashes:
            return False
        old = new = hashes[0]
        position = 1
    for value in hashes[position:]:
        if last == 0:
            return False
        if first & 1 or first == last:
            old = node(value, old)
            new = node(value, new)
            while first != 0 and not first & 1:
                first >>= 1
                last >>= 1
        else:
            new = node(new, value)
        first >>= 1
        last >>= 1
    return last == 0 and old == old_root and new == new_root
