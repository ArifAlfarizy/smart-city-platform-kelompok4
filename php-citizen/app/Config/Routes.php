<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/health', function() {
    return service('response')
        ->setStatusCode(200)
        ->setJSON([
            'status' => 'ok',
            'service' => 'citizen-service',
            'timestamp' => date('c')
        ]);
});
$routes->get('/', 'Home::index');

$routes->group('api/citizens', ['filter' => 'jwt'], function ($routes) {

    $routes->post(
        'register',
        'CitizenController::register'
    );

    $routes->get(
        'profile',
        'CitizenController::profile'
    );

    $routes->put(
        'profile',
        'CitizenController::updateProfile'
    );

    $routes->post(
        'reports',
        'ReportController::create'
    );

    $routes->get(
        'reports',
        'ReportController::myReports'
    );

    $routes->get(
        'reports/all',
        'ReportController::allReports'
    );

    $routes->put(
        'reports/(:num)/status',
        'ReportController::updateStatus/$1'
    );

    $routes->get(
        'notifications',
        'NotificationController::index'
    );
});
