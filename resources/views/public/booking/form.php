<?php
$bookingErrorField = is_string($errorField ?? null) ? $errorField : null;
$bookingFieldError = static fn (string $field): ?string => $error !== null && $bookingErrorField === $field ? $error : null;
?>
<section class="tg-journey-hero" aria-labelledby="booking-title"><div class="tg-container"><nav class="tg-breadcrumb" aria-label="Breadcrumb"><a href="/">Home</a><span aria-hidden="true">/</span><span aria-current="page">Booking</span></nav><p class="tg-eyebrow">Guest booking request</p><h1 id="booking-title">Tell us about your stay</h1><p>Your request remains pending until the approved payment is submitted and verified.</p></div></section>

<div class="tg-container tg-journey-content">
    <ol class="tg-progress" aria-label="Booking progress"><li class="is-complete">Room</li><li aria-current="step">Guest Details</li><li>Payment</li></ol>

    <?php if ($error !== null): ?><div id="booking-form-error" class="tg-alert tg-alert--error" role="alert" tabindex="-1"><strong>Please review your booking details.</strong><p><?= $e($error) ?></p><?php if ($bookingErrorField !== null): ?><a href="#<?= $e($bookingErrorField) ?>">Go to the field that needs attention</a><?php endif; ?></div><?php endif; ?>

    <?php if ($room === null): ?>
        <section class="tg-empty-state" aria-labelledby="choose-room-title"><p class="tg-eyebrow">Room required</p><h2 id="choose-room-title">Choose a room first</h2><p>Check availability to select an eligible room for your dates and guest count. You do not need an account.</p><a class="tg-button tg-button--emerald" href="/availability" data-ripple>Check availability</a></section>
    <?php else: ?>
        <div class="tg-booking-layout">
            <form method="post" action="/booking" class="tg-booking-form tg-journey-form" novalidate<?= $error !== null ? ' aria-describedby="booking-form-error"' : '' ?>>
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <input type="hidden" name="room_id" value="<?= $e($values['roomId']) ?>">
                <h2>Guest details</h2>
                <div class="tg-form-grid tg-form-grid--booking">
                    <?php $fieldError = $bookingFieldError('check_in'); ?><div><label for="check_in">Check-in</label><input id="check_in" name="check_in" type="date" value="<?= $e($values['checkIn']) ?>" required<?= $fieldError !== null ? ' aria-invalid="true" aria-describedby="check_in_error"' : '' ?>><?php if ($fieldError !== null): ?><p id="check_in_error" class="tg-field-error"><?= $e($fieldError) ?></p><?php endif; ?></div>
                    <?php $fieldError = $bookingFieldError('check_out'); ?><div><label for="check_out">Check-out</label><input id="check_out" name="check_out" type="date" value="<?= $e($values['checkOut']) ?>" required<?= $fieldError !== null ? ' aria-invalid="true" aria-describedby="check_out_error"' : '' ?>><?php if ($fieldError !== null): ?><p id="check_out_error" class="tg-field-error"><?= $e($fieldError) ?></p><?php endif; ?></div>
                    <?php $fieldError = $bookingFieldError('guest_full_name'); ?><div class="tg-field--wide"><label for="guest_full_name">Full name</label><input id="guest_full_name" name="guest_full_name" type="text" maxlength="120" autocomplete="name" value="<?= $e($values['guestFullName']) ?>" required<?= $fieldError !== null ? ' aria-invalid="true" aria-describedby="guest_full_name_error"' : '' ?>><?php if ($fieldError !== null): ?><p id="guest_full_name_error" class="tg-field-error"><?= $e($fieldError) ?></p><?php endif; ?></div>
                    <?php $fieldError = $bookingFieldError('guest_email'); ?><div><label for="guest_email">Email</label><input id="guest_email" name="guest_email" type="email" maxlength="150" autocomplete="email" value="<?= $e($values['guestEmail']) ?>" required<?= $fieldError !== null ? ' aria-invalid="true" aria-describedby="guest_email_error"' : '' ?>><?php if ($fieldError !== null): ?><p id="guest_email_error" class="tg-field-error"><?= $e($fieldError) ?></p><?php endif; ?></div>
                    <?php $fieldError = $bookingFieldError('guest_phone'); ?><div><label for="guest_phone">Phone</label><input id="guest_phone" name="guest_phone" type="tel" maxlength="30" autocomplete="tel" value="<?= $e($values['guestPhone']) ?>" required<?= $fieldError !== null ? ' aria-invalid="true" aria-describedby="guest_phone_error"' : '' ?>><?php if ($fieldError !== null): ?><p id="guest_phone_error" class="tg-field-error"><?= $e($fieldError) ?></p><?php endif; ?></div>
                    <?php $fieldError = $bookingFieldError('adults'); ?><div><label for="adults">Adults</label><input id="adults" name="adults" type="number" min="1" step="1" value="<?= $e($values['adults']) ?>" required<?= $fieldError !== null ? ' aria-invalid="true" aria-describedby="adults_error"' : '' ?>><?php if ($fieldError !== null): ?><p id="adults_error" class="tg-field-error"><?= $e($fieldError) ?></p><?php endif; ?></div>
                    <?php $fieldError = $bookingFieldError('children'); ?><div><label for="children">Children</label><input id="children" name="children" type="number" min="0" step="1" value="<?= $e($values['children']) ?>" required<?= $fieldError !== null ? ' aria-invalid="true" aria-describedby="children_error"' : '' ?>><?php if ($fieldError !== null): ?><p id="children_error" class="tg-field-error"><?= $e($fieldError) ?></p><?php endif; ?></div>
                    <?php $fieldError = $bookingFieldError('special_request'); ?><div class="tg-field--wide"><label for="special_request">Special request <span>(optional)</span></label><textarea id="special_request" name="special_request" rows="4" maxlength="1000"<?= $fieldError !== null ? ' aria-invalid="true" aria-describedby="special_request_error"' : '' ?>><?= $e($values['specialRequest']) ?></textarea><?php if ($fieldError !== null): ?><p id="special_request_error" class="tg-field-error"><?= $e($fieldError) ?></p><?php endif; ?></div>
                    <?php $fieldError = $bookingFieldError('terms_accepted'); ?><div class="tg-field--wide"><div class="tg-terms"><input id="terms_accepted" name="terms_accepted" type="checkbox" value="1" <?= $values['termsAccepted'] ? 'checked' : '' ?> required<?= $fieldError !== null ? ' aria-invalid="true" aria-describedby="terms_accepted_error"' : '' ?>><label for="terms_accepted">I confirm that the booking details are correct and accept the booking policy.</label></div><?php if ($fieldError !== null): ?><p id="terms_accepted_error" class="tg-field-error"><?= $e($fieldError) ?></p><?php endif; ?></div>
                </div>
                <button type="submit" class="tg-button tg-button--emerald tg-button--full" data-ripple>Submit Booking Request</button>
                <p class="tg-form-note">Submitting creates a pending request. It does not guarantee or confirm the room.</p>
            </form>

            <aside class="tg-stay-summary" aria-labelledby="stay-summary-title">
                <img src="<?= $e($room['primaryImageUrl']) ?>" alt="<?= $e($room['name']) ?>" loading="eager">
                <div><p class="tg-eyebrow"><?= $e($room['roomTypeName']) ?></p><h2 id="stay-summary-title"><?= $e($room['name']) ?></h2><p><strong>NPR <?= $e($room['pricePerNight']) ?></strong> per night</p><dl><div><dt>Check-in</dt><dd><?= $e($values['checkIn'] !== '' ? $values['checkIn'] : 'Choose a date') ?></dd></div><div><dt>Check-out</dt><dd><?= $e($values['checkOut'] !== '' ? $values['checkOut'] : 'Choose a date') ?></dd></div><div><dt>Guests</dt><dd><?= $e($values['adults']) ?> adult<?= $values['adults'] === '1' ? '' : 's' ?>, <?= $e($values['children']) ?> child<?= $values['children'] === '1' ? '' : 'ren' ?></dd></div></dl><p class="tg-form-note">Your final NPR total is calculated securely when the request is submitted.</p></div>
            </aside>
        </div>
    <?php endif; ?>
</div>
