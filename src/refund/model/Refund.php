<?php

namespace refund\model;


use pocketmine\item\Item;
use refund\utils\Utils;

class Refund
{
    public function __construct(
        private int $id,
        private string $name,
        private int $time,
        private array $items,
        private string $position,
        private bool $isRefunded = false,
        private string $killedBy = "unknown",
        private int $experience = 0,
        private string $refundedBy = "",
        private int $refundedAt = 0,
        private string $issuedBy = "",
        private string $receivedBy = "",
    ) {
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getItem(string $type): array
    {
        return $this->items[$type] ?? [];
    }

    /**
     * @return string
     */
    public function getKilledBy(): string
    {
        return $this->killedBy;
    }

    public function getExperience(): int
    {
        return $this->experience;
    }

    public function getRefundedBy(): string
    {
        return $this->refundedBy;
    }

    public function getRefundedAt(): int
    {
        return $this->refundedAt;
    }

    public function getIssuedBy(): string
    {
        return $this->issuedBy;
    }

    public function getReceivedBy(): string
    {
        return $this->receivedBy;
    }

    public function hasClaimProgress(): bool
    {
        return $this->issuedBy !== "" || $this->receivedBy !== "";
    }

    public function setClaimProgress(array $items, int $experience, string $issuedBy, string $receivedBy, bool $isRefunded, int $refundedAt): void
    {
        $this->items = $items;
        $this->experience = $experience;
        $this->issuedBy = $issuedBy;
        $this->receivedBy = $receivedBy;
        $this->isRefunded = $isRefunded;
        $this->refundedBy = $isRefunded ? $issuedBy : "";
        $this->refundedAt = $refundedAt;
    }

    /**
     * @return int
     */
    public function getTime(): int
    {
        return $this->time;
    }

    /**
     * @return bool
     */
    public function isRefunded(): bool
    {
        return $this->isRefunded;
    }

    /**
     * @param bool $isRefunded
     */
    public function setIsRefunded(bool $isRefunded): void
    {
        $this->isRefunded = $isRefunded;
    }

    /**
     * @return string
     */
    public function getPosition(): string
    {
        return $this->position;
    }

    public function getSerializedData(): array
    {
        return [
            "name" => $this->getName(),
            "time" => $this->getTime(),
            "items" => [
                "armor" => Utils::serialize($this->items["armor"] ?? []),
                "inventory" => Utils::serialize($this->items["inventory"] ?? []),
                "offHand" => Utils::serialize($this->items["offHand"] ?? [])
            ],
            "experience" => $this->getExperience(),
            "position" => $this->getPosition(),
            "refunded" => $this->isRefunded(),
            "refundedBy" => $this->getRefundedBy(),
            "refundedAt" => $this->getRefundedAt(),
            "issuedBy" => $this->getIssuedBy(),
            "receivedBy" => $this->getReceivedBy(),
            "killedBy" => $this->getKilledBy()
        ];
    }

    public function getItemCount(): int
    {
        $count = 0;
        foreach (["armor", "inventory", "offHand"] as $section) {
            foreach ($this->items[$section] ?? [] as $item) {
                if ($item instanceof Item && !$item->isNull()) {
                    $count++;
                }
            }
        }
        return $count;
    }

    public function getSerialisedData(): array
    {
        return $this->getSerializedData();
    }

}