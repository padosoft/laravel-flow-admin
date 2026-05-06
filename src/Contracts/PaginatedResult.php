<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Contracts;

/**
 * Common page container returned by read-model list methods.
 *
 * @template T of mixed
 */
final readonly class PaginatedResult
{
    /**
     * @param  list<T>  $items
     */
    public function __construct(
        /** @var list<T> */
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}

    public function totalPages(): int
    {
        if ($this->perPage < 1) {
            return 0;
        }

        return (int) ceil($this->total / $this->perPage);
    }
}
