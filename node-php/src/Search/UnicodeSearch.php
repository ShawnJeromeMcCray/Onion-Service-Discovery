<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Search;

/** Frozen presentation-only Unicode 17; signed bytes and identity are untouched.
 * @phpstan-type UnicodeData array{format_version:int,unicode_version:string,decomposition:array<int,list<int>>,combining:array<int,int>,composition:array<string,int>,lower:array<int,list<int>>,cased:list<array{int,int}>,case_ignorable:list<array{int,int}>}
 */
final class UnicodeSearch
{
    /** @var UnicodeData|null */
    private static ?array $data = null;
    /** @return UnicodeData */
    private static function data(): array
    {
        if (self::$data !== null) { return self::$data; }
        $path = dirname(__DIR__, 3) . '/protocol/registries/search-unicode-v1.json';
        if (is_link($path) || !is_file($path) || filesize($path) > 262144) { throw new \RuntimeException('SEARCH_TABLE_UNAVAILABLE'); }
        $wire = file_get_contents($path, false, null, 0, 262145);
        if ($wire === false || strlen($wire) > 262144) { throw new \RuntimeException('SEARCH_TABLE_UNAVAILABLE'); }
        $decoded = json_decode($wire, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || ($decoded['format_version'] ?? null) !== 1 || ($decoded['unicode_version'] ?? null) !== '17.0.0') { throw new \RuntimeException('SEARCH_TABLE_INVALID'); }
        /** @var UnicodeData $decoded Trusted generated, release-integrity-protected table. */
        return self::$data = $decoded;
    }
    /** @return list<int> */
    public static function points(string $text): array
    {
        if (strlen($text) > 1048576 || preg_match('//u', $text) !== 1) { throw new \InvalidArgumentException('SEARCH_TEXT_INVALID'); }
        $wire = iconv('UTF-8', 'UTF-32BE', $text);
        if ($wire === false) { throw new \InvalidArgumentException('SEARCH_TEXT_INVALID'); }
        $values = $wire === '' ? [] : unpack('N*', $wire);
        if ($values === false) { throw new \InvalidArgumentException('SEARCH_TEXT_INVALID'); }
        $points = array_map(static fn (mixed $value): int => (int)$value, array_values($values));
        if (count($points) > 262144) { throw new \InvalidArgumentException('SEARCH_WORK_LIMIT'); }
        return $points;
    }
    private static function byte(int $value): string { return chr($value & 255); }
    /** @param list<int> $points */
    public static function text(array $points): string
    {
        $text = '';
        foreach ($points as $point) {
            if ($point < 0 || $point > 0x10ffff) { throw new \InvalidArgumentException('SEARCH_TEXT_INVALID'); }
            $text .= $point < 0x80 ? self::byte($point) : ($point < 0x800 ? self::byte(0xc0 | ($point >> 6)) . self::byte(0x80 | ($point & 63)) : ($point < 0x10000 ? self::byte(0xe0 | ($point >> 12)) . self::byte(0x80 | (($point >> 6) & 63)) . self::byte(0x80 | ($point & 63)) : self::byte(0xf0 | ($point >> 18)) . self::byte(0x80 | (($point >> 12) & 63)) . self::byte(0x80 | (($point >> 6) & 63)) . self::byte(0x80 | ($point & 63))));
        }
        return $text;
    }
    /** @param list<int> $out
     * @param array<int,list<int>> $decomposition
     */
    private static function expand(int $point, array &$out, array $decomposition): void
    {
        if ($point >= 0xac00 && $point < 0xd7a4) {
            $syllable = $point - 0xac00; $out[] = 0x1100 + intdiv($syllable, 588); $out[] = 0x1161 + intdiv($syllable % 588, 28);
            if ($syllable % 28 !== 0) { $out[] = 0x11a7 + $syllable % 28; }
        } elseif (isset($decomposition[$point])) {
            foreach ($decomposition[$point] as $part) { self::expand($part, $out, $decomposition); }
        } else { $out[] = $point; }
        if (count($out) > 262144) { throw new \InvalidArgumentException('SEARCH_WORK_LIMIT'); }
    }
    /** @param array<string,int> $composition */
    private static function compose(int $a, int $b, array $composition): ?int
    {
        if ($a >= 0x1100 && $a < 0x1113 && $b >= 0x1161 && $b < 0x1176) { return 0xac00 + ($a - 0x1100) * 588 + ($b - 0x1161) * 28; }
        if ($a >= 0xac00 && $a < 0xd7a4 && ($a - 0xac00) % 28 === 0 && $b > 0x11a7 && $b < 0x11c3) { return $a + $b - 0x11a7; }
        return $composition[$a . ':' . $b] ?? null;
    }
    public static function nfkc(string $text): string
    {
        $data = self::data(); $decomposed = [];
        $input = self::points($text); $units = 0; foreach ($input as $point) { $units += $point > 0xffff ? 2 : 1; }
        if ($units > 65536) { throw new \InvalidArgumentException('SEARCH_WORK_LIMIT'); }
        foreach ($input as $point) { self::expand($point, $decomposed, $data['decomposition']); }
        $ordered = []; $segment = [];
        foreach ($decomposed as $point) {
            if (($data['combining'][$point] ?? 0) === 0) {
                foreach (self::reorder($segment, $data['combining']) as $part) { $ordered[] = $part; }
                $segment = []; $ordered[] = $point;
            } else { $segment[] = $point; }
        }
        foreach (self::reorder($segment, $data['combining']) as $part) { $ordered[] = $part; }
        $result = []; $starter = -1; $lastClass = 0;
        foreach ($ordered as $point) {
            $class = $data['combining'][$point] ?? 0;
            $merged = $starter >= 0 && ($lastClass === 0 || $lastClass < $class) ? self::compose($result[$starter], $point, $data['composition']) : null;
            if ($merged !== null) { $result[$starter] = $merged; }
            else { if ($class === 0) { $starter = count($result); } $result[] = $point; $lastClass = $class; }
        }
        return self::text(array_values($result));
    }
    /** @param list<int> $segment
     * @param array<int,int> $combining
     * @return list<int>
     */
    private static function reorder(array $segment, array $combining): array
    {
        // PHP 8 stable sort preserves equal-CCC order.
        usort($segment, static fn (int $a, int $b): int => ($combining[$a] ?? 0) <=> ($combining[$b] ?? 0));
        return $segment;
    }
    /** @param list<array{int,int}> $ranges */
    private static function inRanges(int $point, array $ranges): bool
    {
        $lo = 0; $hi = count($ranges) - 1;
        while ($lo <= $hi) { $mid = intdiv($lo + $hi, 2); $range = $ranges[$mid]; if ($point < $range[0]) { $hi = $mid - 1; } elseif ($point > $range[1]) { $lo = $mid + 1; } else { return true; } }
        return false;
    }
    public static function normalize(string $text): string
    {
        $data = self::data(); $points = self::points(self::nfkc($text)); $out = []; $count = count($points);
        foreach ($points as $index => $point) {
            if ($point === 0x03a3) {
                $before = $index - 1; $after = $index + 1;
                while ($before >= 0 && self::inRanges($points[$before], $data['case_ignorable'])) { --$before; }
                while ($after < $count && self::inRanges($points[$after], $data['case_ignorable'])) { ++$after; }
                $out[] = $before >= 0 && self::inRanges($points[$before], $data['cased']) && ($after >= $count || !self::inRanges($points[$after], $data['cased'])) ? 0x03c2 : 0x03c3;
            } else { foreach ($data['lower'][$point] ?? [$point] as $mapped) { $out[] = $mapped; } }
        }
        return self::text($out);
    }
}
