"""Portable cross-language canonical source-value SHA tests; no private rows."""
import hashlib
import pathlib
import sys
import unittest

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[1]))
from full_row_digests import canon, row_hash, table_hash


class FullRowDigestsTests(unittest.TestCase):
    def test_identity_zero_and_unicode_value_digest_is_stable(self):
        self.assertEqual(row_hash(['0', 'CI_ZERO_ACCOUNT', '1']),
                         '355244ae16345f19baedafd737e7d8f22f008948638756ccb7ebb538b83045a4')
        self.assertEqual(row_hash(['1.20', '2020-02-03 04:05:06', 'a\\b', None]),
                         'b1b806ea3b5ac7d8d0ee9248bee8e623805673e93144df16a755f02543e6530e')

    def test_mysql_decimal_storage_scale_and_null_sensitivity(self):
        self.assertEqual(canon('1.2', 'decimal(10,2) UNSIGNED NOT NULL'), '1.20')
        self.assertEqual(canon('000', 'bigint(20) UNSIGNED NOT NULL'), '0')
        self.assertEqual(canon('0012', 'int(11) NOT NULL'), '12')
        self.assertEqual(canon('0.00', 'decimal(10,2) NOT NULL'), '0.00')
        self.assertNotEqual(row_hash(['']), row_hash([None]))
        self.assertNotEqual(row_hash(['1', '2']), row_hash(['12']))

    def test_full_row_multiset_preserves_multiplicity(self):
        h = row_hash(['A', None])
        other = row_hash(['B', ''])
        self.assertEqual(table_hash([h, other, h]), table_hash([other, h, h]))
        self.assertNotEqual(table_hash([h, other]), table_hash([h, other, h]))
        self.assertEqual(table_hash([]), hashlib.sha256(b'').hexdigest())

    def test_incompatible_decimal_is_rejected(self):
        with self.assertRaisesRegex(ValueError, 'exceeds stored scale'):
            canon('1.234', 'decimal(10,2)')
        with self.assertRaisesRegex(ValueError, 'Unreviewed'):
            canon('{}', 'json NOT NULL')


if __name__ == '__main__':
    unittest.main()
