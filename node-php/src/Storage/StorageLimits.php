<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Storage;

final readonly class StorageLimits
{
    public function __construct(
        public int $maxRecords = 10000,
        public int $maxOperationalRows = 30000,
        public int $maxAdmissionsPerTransaction = 256,
        public int $maxBytes = 268435456,
        public int $maxCheckpointBytes = 33554432,
        public int $maxJournalBytes = 16777216,
        public int $maxValueBytes = 8192,
        public int $lockWaitMilliseconds = 2000,
    ) {
        foreach (get_object_vars($this) as $value) {
            if ($value < 1) {
                throw new StorageException('CONFIGURATION_INVALID');
            }
        }
        if ($maxRecords > 1000000 || $maxOperationalRows > 100000 || $maxAdmissionsPerTransaction > 256 ||
            $maxCheckpointBytes > 134217728 || $maxJournalBytes > 67108864 || $maxValueBytes > 65536 || $lockWaitMilliseconds > 10000 || $maxBytes > 1099511627776) {
            throw new StorageException('CONFIGURATION_INVALID');
        }
    }
}
