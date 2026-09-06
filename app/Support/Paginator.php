<?php

declare(strict_types=1);

namespace App\Support;

final readonly class Paginator
{
    public function __construct(public int $total, public int $perPage, public int $currentPage) {}
    public function lastPage(): int { return max(1, (int) ceil($this->total / max(1, $this->perPage))); }
    public function offset(): int { return max(0, ($this->currentPage - 1) * $this->perPage); }
    public function hasPrevious(): bool { return $this->currentPage > 1; }
    public function hasNext(): bool { return $this->currentPage < $this->lastPage(); }
}
