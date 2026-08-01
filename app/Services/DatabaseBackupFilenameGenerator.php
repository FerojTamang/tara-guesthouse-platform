<?php
declare(strict_types=1);
namespace App\Services;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class DatabaseBackupFilenameGenerator
{
    /** @var \Closure(int): string */ private readonly \Closure $random;
    public function __construct(?callable $random = null) { $this->random = \Closure::fromCallable($random ?? static fn(int $n): string => random_bytes($n)); }
    public function generate(DateTimeImmutable $at): string
    {
        $bytes = ($this->random)(6);
        if (!is_string($bytes) || strlen($bytes) !== 6) throw new InvalidArgumentException('Backup filename could not be generated.');
        return 'tara-guest-house-' . $at->setTimezone(new DateTimeZone('UTC'))->format('Ymd\\THis\\Z') . '-' . bin2hex($bytes) . '.sql';
    }
}
