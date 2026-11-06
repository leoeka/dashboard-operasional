<?php

test('registration screen is disabled and returns 404', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});

test('public registration endpoint is disabled and returns 404', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'username' => 'test-user',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
    $this->assertGuest();
});

