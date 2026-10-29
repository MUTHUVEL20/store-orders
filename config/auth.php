<?php

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| The brief does not ask for authentication, so this application has no users
| table, no login and no protected routes (a documented limitation). The
| defaults below are the framework's standard guard and provider so the
| auth manager can still be resolved; App\Models\User does not exist.
|
*/

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
        ],
    ],

];
