<?php

namespace App\Services;

interface DnsVerifier
{
    /**
     * Return the TXT record values found at the given host.
     *
     * @return string[]
     */
    public function txtRecords(string $host): array;
}
