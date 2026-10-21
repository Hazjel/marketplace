<?php

namespace App\Services\E2E;

use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

class FakeGoogleProvider extends GoogleProvider
{
    public function redirect(): RedirectResponse
    {
        $query = isset($this->parameters['state']) ? '?'.http_build_query(['state' => $this->parameters['state']]) : '';

        return redirect($this->redirectUrl.$query);
    }

    public function user(): User
    {
        return (new User)->map([
            'id' => 'e2e-buyer',
            'name' => 'E2E Buyer',
            'email' => 'buyer@e2e.test',
            'avatar' => null,
        ]);
    }
}
