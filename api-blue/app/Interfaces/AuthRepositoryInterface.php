<?php

namespace App\Interfaces;

interface AuthRepositoryInterface
{
    public function register(array $data);

    public function login(array $data);

    public function me();

    public function updateProfile(array $data);

    public function updateSettings(array $data);

    public function logout();
}
