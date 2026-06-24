<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('api/citizens', function ($routes) {

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
