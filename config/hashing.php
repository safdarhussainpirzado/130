<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default hash driver that will be used to hash
    | passwords for your application. By default, Argon2id is used for
    | government-grade cryptographic security and GPU cracking resistance.
    |
    */

    'driver' => env('HASH_DRIVER', 'argon2id'),

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | Here you may specify the configuration options for when passwords are
    | hashed using the Bcrypt algorithm.
    |
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon2 Options (OWASP / Government Baseline)
    |--------------------------------------------------------------------------
    |
    | Here you may specify the configuration options for when passwords are
    | hashed using the Argon2 algorithm.
    |
    */

    'argon' => [
        'memory'  => env('HASH_ARGON_MEMORY', 19456),
        'threads' => env('HASH_ARGON_THREADS', 1),
        'time'    => env('HASH_ARGON_TIME', 2),
        'verify'  => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic Password Rehashing
    |--------------------------------------------------------------------------
    |
    | When enabled, Laravel will automatically upgrade legacy Bcrypt hashes to
    | Argon2id when users log in.
    |
    */

    'rehash_on_login' => true,

];
