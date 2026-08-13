<?php

namespace App\Services\Phonebook;

class ContactImportRow
{
    public function __construct(
        public readonly int $rowNumber,
        public readonly ?string $name,
        public readonly ?string $channel,
        public readonly ?string $handle,
        public readonly ?string $phone,
        public readonly ?string $email,
    ) {}
}
