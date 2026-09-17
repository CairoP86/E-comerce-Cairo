<?php

namespace App\Contracts;

interface CartStore
{
    public function read(): array;

    public function write(array $cart): void;
}
