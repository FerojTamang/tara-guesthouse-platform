<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Requests\GuestBookingRequest;

interface BookingSubmitter
{
    public function submit(GuestBookingRequest $request): Booking;
}
