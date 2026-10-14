"""Finite adversarial checks for an explicitly non-runtime research experiment."""
import unittest
from hashlib import sha256
from tools.experiments.checkpoint_consistency import proof, root, verify


class ConsistencyExperiment(unittest.TestCase):
    def test_every_prefix_and_boundary_to_129(self):
        leaves = [f'opaque checkpoint {i}'.encode() for i in range(129)]
        for total in range(130):
            for old in range(total + 1):
                hashes = proof(leaves[:total], old)
                self.assertTrue(verify(old, total, root(leaves[:old]), root(leaves[:total]), hashes))
                self.assertLessEqual(len(hashes), 9)
                if hashes:
                    for index in range(len(hashes)):
                        damaged = list(hashes)
                        damaged[index] = bytes([damaged[index][0] ^ 1]) + damaged[index][1:]
                        self.assertFalse(verify(old, total, root(leaves[:old]), root(leaves[:total]), damaged))
                    self.assertFalse(verify(old, total, root(leaves[:old]), root(leaves[:total]), hashes[:-1]))
                self.assertFalse(verify(old, total, root(leaves[:old]), root(leaves[:total]), hashes + [bytes(32)]))

    def test_divergent_histories_and_untrusted_boundaries(self):
        first = [bytes([i]) for i in range(80)]
        second = list(first)
        second[3] = b'different signed checkpoint'
        self.assertFalse(verify(33, 80, root(first[:33]), root(second), proof(second, 33)))
        self.assertFalse(verify(80, 33, root(first), root(first[:33]), []))
        self.assertFalse(verify(True, 1, root([]), root(first[:1]), []))
        self.assertFalse(verify(0, 1, bytes(32), root(first[:1]), []))
        self.assertFalse(verify(0, 0, root([]), bytes(32), []))
        self.assertEqual(root([]), sha256(b'').digest())
        self.assertEqual(root([b'x']), sha256(b'\x00x').digest())


if __name__ == '__main__':
    unittest.main()
