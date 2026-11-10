<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/login', 'Auth::login');
$routes->post('/login', 'Auth::attempt');
$routes->get('/logout', 'Auth::logout');
$routes->get('/dev-login', 'Auth::devLogin'); // development environment only (404 otherwise)

$routes->get('/', 'Dashboard::index', ['filter' => 'auth']);
$routes->post('/year/switch', 'Dashboard::switchYear', ['filter' => 'auth']);
$routes->get('/photos/(:num)', 'Students::photo/$1', ['filter' => 'auth']);

$routes->group('students', ['filter' => 'area:students'], static function ($r) {
    $r->get('/', 'Students::index');
    $r->get('new', 'Students::create');
    $r->post('/', 'Students::store');
    $r->get('sync-moodle', 'Students::syncMoodle');
    $r->post('sync-moodle', 'Students::syncMoodle');
    $r->get('(:num)', 'Students::show/$1');
    $r->get('(:num)/edit', 'Students::edit/$1');
    $r->post('(:num)', 'Students::update/$1');
    $r->post('(:num)/photo', 'Students::uploadPhoto/$1');
    $r->post('(:num)/contacts', 'Students::addContact/$1');
    $r->post('(:num)/contacts/(:num)/delete', 'Students::deleteContact/$1/$2');
    $r->post('(:num)/vehicles', 'Students::addVehicle/$1');
    $r->post('(:num)/vehicles/(:num)/delete', 'Students::deleteVehicle/$1/$2');
    $r->get('(:num)/grades', 'Students::grades/$1');
});

$routes->group('tuition', ['filter' => 'area:tuition'], static function ($r) {
    $r->get('/', 'Tuition::index');
    $r->get('(:num)', 'Tuition::student/$1');
    $r->post('(:num)/account', 'Tuition::saveAccount/$1');
    $r->post('(:num)/payments', 'Tuition::addPayment/$1');
    $r->post('(:num)/payments/(:num)/delete', 'Tuition::deletePayment/$1/$2');
});

$routes->group('housing', ['filter' => 'area:housing'], static function ($r) {
    $r->get('/', 'HostHomes::index');
    $r->post('/', 'HostHomes::store');
    $r->post('(:num)', 'HostHomes::update/$1');
    $r->post('(:num)/delete', 'HostHomes::delete/$1');
    $r->post('assign', 'HostHomes::assign');
});

$routes->group('mentors', ['filter' => 'area:mentors'], static function ($r) {
    $r->get('/', 'Mentors::index');
    $r->post('/', 'Mentors::store');
    $r->post('(:num)', 'Mentors::update/$1');
    $r->post('(:num)/delete', 'Mentors::delete/$1');
    $r->post('assign', 'Mentors::assign');
});

$routes->group('groups', ['filter' => 'area:groups'], static function ($r) {
    $r->get('/', 'Groups::index');
    $r->post('sets', 'Groups::saveSet');
    $r->post('sets/(:num)/delete', 'Groups::deleteSet/$1');
    $r->get('sets/(:num)', 'Groups::set/$1');
    $r->post('sets/(:num)/groups', 'Groups::saveGroup/$1');
    $r->post('sets/(:num)/groups/(:num)/delete', 'Groups::deleteGroup/$1/$2');
    $r->post('sets/(:num)/assign', 'Groups::assign/$1');
    $r->get('sets/(:num)/print', 'Groups::printSet/$1');
});

$routes->group('transcripts', ['filter' => 'area:transcripts'], static function ($r) {
    $r->get('/', 'Transcripts::index');
    $r->get('catalog', 'Transcripts::catalog');
    $r->post('catalog', 'Transcripts::saveCourse');
    $r->post('catalog/(:num)/delete', 'Transcripts::deleteCourse/$1');
    $r->get('(:num)', 'Transcripts::student/$1');
    $r->post('(:num)/grades', 'Transcripts::saveGrades/$1');
    $r->post('(:num)/pull', 'Transcripts::pullMoodle/$1');
    $r->get('(:num)/pdf', 'Transcripts::pdf/$1');
});

$routes->group('admin', ['filter' => 'area:admin'], static function ($r) {
    $r->get('users', 'Admin::users');
    $r->post('users', 'Admin::saveUser');
    $r->post('users/(:num)/delete', 'Admin::deleteUser/$1');
    $r->get('years', 'Admin::years');
    $r->post('years', 'Admin::saveYear');
    $r->get('moodle', 'Admin::moodle');
});
