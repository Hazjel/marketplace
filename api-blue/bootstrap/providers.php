<?php

use App\Providers\AppServiceProvider;
use App\Providers\RepositoryServiceProvider;
use MongoDB\Laravel\MongoDBServiceProvider;

return [
    AppServiceProvider::class,
    RepositoryServiceProvider::class,
    MongoDBServiceProvider::class,
];
