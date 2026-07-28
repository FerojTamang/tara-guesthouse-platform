<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AvailabilityException;
use App\Models\AvailabilityResult;
use App\Repositories\AvailabilityRepository;
use App\Requests\AvailabilityRequest;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * Coordinates one validated room-availability calculation.
 */
final class AvailabilityService
{
    /** @var Closure(): mixed */
    private readonly Closure $clock;

    /**
     * @param null|callable(): mixed $clock
     */
    public function __construct(
        private readonly AvailabilityRepository $availability,
        ?callable $clock = null,
    ) {
        $this->clock = $clock === null
            ? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'))
            : Closure::fromCallable($clock);
    }

    public function search(AvailabilityRequest $request): AvailabilityResult
    {
        try {
            $checkedAt = ($this->clock)();

            if (!$checkedAt instanceof DateTimeImmutable || $checkedAt->getOffset() !== 0) {
                throw new RuntimeException('The availability clock returned invalid data.');
            }

            $roomIds = $this->availability->findAvailableRoomIds(
                $request->stayPeriod(),
                $request->adults(),
                $request->children(),
                $checkedAt,
            );

            return new AvailabilityResult(
                $request->stayPeriod(),
                $request->adults(),
                $request->children(),
                $checkedAt,
                $roomIds,
            );
        } catch (Throwable $failure) {
            throw AvailabilityException::failure($failure);
        }
    }
}
