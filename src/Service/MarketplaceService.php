<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\LearningClass;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class MarketplaceService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function listClasses(?string $category = null, ?float $maxPrice = null, ?string $keyword = null, ?int $userId = null): array
    {
        $sql = "SELECT c.*, cp.company_name, cp.rating AS provider_rating, cp.is_verified,
                       u.full_name AS provider_name,
                       (SELECT COUNT(*) FROM bookings b WHERE b.class_id = c.class_id AND b.status != 'cancelled') AS current_enrollment,
                       (SELECT AVG(b.rating) FROM bookings b WHERE b.class_id = c.class_id AND b.rating IS NOT NULL) AS rating";
        
        if ($userId) {
            $sql .= ", (SELECT COUNT(*) FROM bookings b WHERE b.class_id = c.class_id AND b.user_id = :userId AND b.status != 'cancelled') AS hasBooked";
        }
        
        $sql .= " FROM classes c
                JOIN class_providers cp ON c.provider_id = cp.provider_id
                JOIN users u ON cp.user_id = u.user_id
                WHERE cp.is_verified = 1";

        $params = [];
        if ($userId) {
            $params['userId'] = $userId;
        }
        if ($category) {
            $sql .= ' AND c.category = :category';
            $params['category'] = $category;
        }
        if ($maxPrice !== null) {
            $sql .= ' AND c.price <= :maxPrice';
            $params['maxPrice'] = $maxPrice;
        }
        if ($keyword) {
            $sql .= ' AND (c.title LIKE :kw OR c.description LIKE :kw OR c.category LIKE :kw)';
            $params['kw'] = '%' . $keyword . '%';
        }
        $sql .= ' ORDER BY cp.rating DESC, c.class_id DESC';

        return $this->em->getConnection()->fetchAllAssociative($sql, $params);
    }

    public function createBooking(int $classId, int $userId): Booking
    {
        $class = $this->em->getRepository(LearningClass::class)->find($classId);
        $user = $this->em->getRepository(User::class)->find($userId);

        $b = new Booking();
        $b->class = $class;
        $b->user = $user;
        $b->bookingDate = new \DateTime();
        $b->status = 'pending';
        $b->paymentStatus = 'pending';
        $b->totalAmount = $class?->price ?? 0;
        $b->watchProgress = 0;
        $this->em->persist($b);
        $this->em->flush();

        return $b;
    }

    public function markBookingPaid(int $bookingId, ?string $stripeSessionId = null): void
    {
        $this->em->getConnection()->update('bookings', [
            'payment_status' => 'paid',
            'status' => 'scheduled',
            'stripe_session_id' => $stripeSessionId,
        ], ['booking_id' => $bookingId]);
    }

    public function completeBooking(int $bookingId): void
    {
        $this->em->getConnection()->update('bookings', ['status' => 'completed'], ['booking_id' => $bookingId]);
    }

    public function cancelBooking(int $bookingId): void
    {
        $this->em->getConnection()->update('bookings', ['status' => 'cancelled'], ['booking_id' => $bookingId]);
    }

    public function rateBooking(int $bookingId, int $rating, ?string $review): void
    {
        $this->em->getConnection()->update('bookings', [
            'rating' => $rating,
            'review' => $review,
        ], ['booking_id' => $bookingId]);
    }

    public function listUserBookings(int $userId): array
    {
        $sql = "SELECT b.*, c.title AS class_title, c.category AS class_category, c.duration AS class_duration,
                       c.provider_id, u.username, u.full_name AS user_full_name, u.email AS user_email,
                       p.full_name AS provider_name
                FROM bookings b
                JOIN classes c ON b.class_id = c.class_id
                JOIN users u ON b.user_id = u.user_id
                JOIN class_providers cp ON c.provider_id = cp.provider_id
                JOIN users p ON cp.user_id = p.user_id
                WHERE b.user_id = :uid
                ORDER BY b.booking_date DESC";

        return $this->em->getConnection()->fetchAllAssociative($sql, ['uid' => $userId]);
    }

    public function listProviderBookings(int $providerId): array
    {
        $sql = "SELECT b.*, c.title AS class_title, c.category AS class_category, c.duration AS class_duration,
                       c.provider_id, u.username, u.full_name AS user_full_name, u.email AS user_email,
                       p.full_name AS provider_name
                FROM bookings b
                JOIN classes c ON b.class_id = c.class_id
                JOIN users u ON b.user_id = u.user_id
                JOIN class_providers cp ON c.provider_id = cp.provider_id
                JOIN users p ON cp.user_id = p.user_id
                WHERE c.provider_id = :pid
                ORDER BY b.booking_date DESC";

        return $this->em->getConnection()->fetchAllAssociative($sql, ['pid' => $providerId]);
    }

    public function getBookingById(int $bookingId): ?Booking
    {
        return $this->em->getRepository(Booking::class)->find($bookingId);
    }

    public function getClassById(int $classId): ?LearningClass
    {
        return $this->em->getRepository(LearningClass::class)->find($classId);
    }
}
