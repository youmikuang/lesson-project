<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Exceptions\CourseException;
use App\Exceptions\ReservationException;
use App\Models\Course;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReservationService
{
    public function createReservation(Course $course, int $userId): array
    {
        return DB::transaction(function () use ($course, $userId) {
            $course = Course::lockForUpdate()->find($course->id);

            $this->ensureCourseIsOpen($course);
            $this->ensureUserNotAlreadyReserved($course->id, $userId);

            $confirmedCount = Reservation::forCourse($course->id)
                ->confirmed()
                ->lockForUpdate()
                ->count();

            return $confirmedCount < $course->capacity
                ? $this->confirmReservation($course, $userId, $confirmedCount)
                : $this->waitlistReservation($course, $userId);
        });
    }

    public function cancelReservation(Reservation $reservation): array
    {
        if ($reservation->isCancelled()) {
            throw ReservationException::alreadyCancelled();
        }

        return DB::transaction(function () use ($reservation) {
            $wasConfirmed = $reservation->isConfirmed();
            $courseId = $reservation->course_id;
            $originalPosition = $reservation->waitlist_position;

            $reservation = Reservation::lockForUpdate()->find($reservation->id);
            $reservation->cancel();

            Log::info('预约已取消', [
                'reservation_id' => $reservation->id,
                'user_id' => $reservation->user_id,
                'course_id' => $courseId,
                'was_confirmed' => $wasConfirmed,
            ]);

            $promotedReservation = $wasConfirmed
                ? $this->promoteFirstWaitlisted($courseId)
                : tap(null, fn () => $this->updateWaitlistPositions($courseId, $originalPosition));

            return [
                'reservation' => $reservation,
                'promoted_reservation' => $promotedReservation,
            ];
        });
    }

    private function ensureCourseIsOpen(Course $course): void
    {
        if (!$course->isOpen()) {
            throw CourseException::notAvailable();
        }
    }

    private function ensureUserNotAlreadyReserved(int $courseId, int $userId): void
    {
        $existing = Reservation::forUser($userId)
            ->forCourse($courseId)
            ->active()
            ->first();

        if ($existing) {
            throw ReservationException::alreadyReserved(
                $existing->id,
                $existing->status->value
            );
        }
    }

    private function confirmReservation(Course $course, int $userId, int $confirmedCount): array
    {
        $reservation = Reservation::create([
            'user_id' => $userId,
            'course_id' => $course->id,
            'status' => ReservationStatus::Confirmed,
            'waitlist_position' => null,
            'reserved_at' => now(),
        ]);

        $reservation->load('course');

        Log::info('预约成功', [
            'reservation_id' => $reservation->id,
            'user_id' => $userId,
            'course_id' => $course->id,
            'status' => 'confirmed',
        ]);

        return [
            'reservation' => $reservation,
            'remaining_slots' => $course->capacity - $confirmedCount - 1,
            'msg' => '预约成功',
        ];
    }

    private function waitlistReservation(Course $course, int $userId): array
    {
        $nextPosition = Reservation::forCourse($course->id)
            ->waitlisted()
            ->max('waitlist_position') + 1;

        $reservation = Reservation::create([
            'user_id' => $userId,
            'course_id' => $course->id,
            'status' => ReservationStatus::Waitlisted,
            'waitlist_position' => $nextPosition,
            'reserved_at' => now(),
        ]);

        $reservation->load('course');

        Log::info('加入候补名单', [
            'reservation_id' => $reservation->id,
            'user_id' => $userId,
            'course_id' => $course->id,
            'waitlist_position' => $reservation->waitlist_position,
        ]);

        return [
            'reservation' => $reservation,
            'msg' => '课程已满，已加入候补名单',
        ];
    }

    private function promoteFirstWaitlisted(int $courseId): ?Reservation
    {
        $first = Reservation::forCourse($courseId)
            ->waitlisted()
            ->orderByPosition()
            ->lockForUpdate()
            ->first();

        if (!$first) {
            return null;
        }

        $previousPosition = $first->waitlist_position;
        $first->promote();

        Log::info('候补用户已递补', [
            'reservation_id' => $first->id,
            'user_id' => $first->user_id,
            'course_id' => $courseId,
            'previous_position' => $previousPosition,
        ]);

        $this->updateWaitlistPositions($courseId, $previousPosition);

        return $first;
    }

    private function updateWaitlistPositions(int $courseId, ?int $removedPosition): void
    {
        if ($removedPosition === null) {
            return;
        }

        Reservation::forCourse($courseId)
            ->waitlisted()
            ->where('waitlist_position', '>', $removedPosition)
            ->decrement('waitlist_position');
    }
}
