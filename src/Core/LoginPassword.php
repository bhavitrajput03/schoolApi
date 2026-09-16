<?php
declare(strict_types=1);
namespace App\Core;

/** Case-insensitive login password compatibility requested for teacher and parent accounts. */
final class LoginPassword
{
    public static function normalize(string $password):string
    {
        return mb_strtolower($password,'UTF-8');
    }

    public static function matches(string $provided,string $stored):bool
    {
        $info=password_get_info($stored);
        if(($info['algoName']??'unknown')==='unknown'){
            return hash_equals(self::normalize($stored),self::normalize($provided));
        }
        // The exact check keeps old mixed-case hashes working once; normalized
        // hashes make all subsequent logins case-insensitive.
        return password_verify(self::normalize($provided),$stored)
            || password_verify($provided,$stored);
    }

    public static function hash(string $password):string
    {
        $algorithm=defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;
        return password_hash(self::normalize($password),$algorithm);
    }

    public static function needsUpgrade(string $provided,string $stored):bool
    {
        $info=password_get_info($stored);
        $algorithm=defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;
        return ($info['algoName']??'unknown')==='unknown'
            || !password_verify(self::normalize($provided),$stored)
            || password_needs_rehash($stored,$algorithm);
    }
}
