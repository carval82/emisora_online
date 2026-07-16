<?php

namespace App\Support;

class LanHelper
{
    /**
     * Obtiene las IPs locales útiles para acceder desde otros equipos en la red.
     *
     * @return string[]
     */
    public static function addresses(): array
    {
        $ips = [];

        if (function_exists('socket_create')) {
            $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($sock) {
                @socket_connect($sock, '8.8.8.8', 80);
                @socket_getsockname($sock, $addr);
                @socket_close($sock);
                if (! empty($addr) && self::isUseful($addr)) {
                    $ips[] = $addr;
                }
            }
        }

        $hostname = gethostname();
        if ($hostname) {
            $resolved = gethostbynamel($hostname) ?: [];
            foreach ($resolved as $ip) {
                if (self::isUseful($ip)) {
                    $ips[] = $ip;
                }
            }
        }

        $ips = array_values(array_unique($ips));
        usort($ips, fn ($a, $b) => self::sortScore($b) <=> self::sortScore($a));

        return $ips;
    }

    public static function playerUrl(?string $ip = null, int $port = 8000): string
    {
        $ip = $ip ?: (self::addresses()[0] ?? 'TU_IP');

        return "http://{$ip}:{$port}";
    }

    private static function isUseful(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        if ($ip === '127.0.0.1' || str_starts_with($ip, '127.')) {
            return false;
        }

        // Interfaces virtuales típicas (VirtualBox, etc.)
        if (str_starts_with($ip, '169.254.') || $ip === '192.168.56.1') {
            return false;
        }

        return true;
    }

    private static function sortScore(string $ip): int
    {
        if (str_starts_with($ip, '192.168.')) {
            return 3;
        }
        if (str_starts_with($ip, '10.')) {
            return 2;
        }

        return 1;
    }
}
