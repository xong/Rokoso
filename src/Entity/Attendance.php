<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttendanceStatus;
use Doctrine\ORM\Mapping as ORM;

/**
 * Attendance of a member at a meeting.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['meeting_id', 'user_id'])]
class Attendance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'attendances')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Meeting $meeting,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(length: 20, enumType: AttendanceStatus::class)]
        private AttendanceStatus $status = AttendanceStatus::Present,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMeeting(): Meeting
    {
        return $this->meeting;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getStatus(): AttendanceStatus
    {
        return $this->status;
    }

    public function setStatus(AttendanceStatus $status): static
    {
        $this->status = $status;

        return $this;
    }
}
