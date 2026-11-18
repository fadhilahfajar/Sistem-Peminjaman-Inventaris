<?php

namespace App\Support;

class BuatUsernameDariEmail
{
    /**
     * Bentuk username dasar dari email (tanpa penanganan bentrok).
     */
    public static function dasar(string $email): string
    {
        $lokal = strstr($email, '@', true);
        if ($lokal === false) {
            $lokal = $email;
        }

        $lokal = mb_strtolower($lokal);
        $lokal = preg_replace('/[^a-z0-9._-]+/', '_', $lokal) ?? '';

        if (mb_strlen($lokal) < 3) {
            $lokal = str_pad($lokal, 3, 'user');
        }

        if (mb_strlen($lokal) > 50) {
            $lokal = mb_substr($lokal, 0, 50);
        }

        // Pastikan tetap memenuhi minimal 3 setelah pemotongan (teoretis tidak perlu, tapi aman).
        if (mb_strlen($lokal) < 3) {
            $lokal = str_pad($lokal, 3, 'u');
        }

        return $lokal;
    }

    /**
     * Hasilkan username unik: bila $sudahAda mengembalikan true, tambahkan "-<id>".
     *
     * @param  callable(string): bool  $sudahAda
     */
    public static function dariEmail(string $email, int $id, callable $sudahAda): string
    {
        $username = self::dasar($email);

        if ($sudahAda($username)) {
            $sufiks = '-'.$id;
            $batas = 50 - mb_strlen($sufiks);
            if ($batas < 1) {
                $batas = 1;
            }
            $username = mb_substr($username, 0, $batas).$sufiks;
        }

        return $username;
    }
}
