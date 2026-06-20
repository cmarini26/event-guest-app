<?php

namespace App\Services;

class SystemDnsVerifier implements DnsVerifier
{
    public function txtRecords(string $host): array
    {
        $records = @dns_get_record($host, DNS_TXT);

        if ($records === false || $records === null) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($r) => $r['txt'] ?? null,
            $records
        )));
    }
}
