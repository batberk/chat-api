<?php

declare(strict_types=1);

namespace Tests;

class UserTest extends TestCase
{
    public function testCreateUser(): void
    {
        $request = $this->createRequest('POST', '/users', ['username' => 'newuser']);
        $response = $this->app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals('newuser', $data['user']['username']);
        $this->assertArrayHasKey('token', $data['user']);
        $this->assertEquals(64, strlen($data['user']['token']));
    }

    public function testCreateUserWithMissingUsername(): void
    {
        $request = $this->createRequest('POST', '/users', []);
        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('error', $data);
    }

    public function testCreateUserWithShortUsername(): void
    {
        $request = $this->createRequest('POST', '/users', ['username' => 'ab']);
        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('error', $data);
    }

    public function testCreateUserWithInvalidCharacters(): void
    {
        $request = $this->createRequest('POST', '/users', ['username' => 'user@name']);
        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('error', $data);
    }

    public function testCreateUserWithDuplicateUsername(): void
    {
        $this->createUser('existinguser');

        $request = $this->createRequest('POST', '/users', ['username' => 'existinguser']);
        $response = $this->app->handle($request);

        $this->assertEquals(409, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('error', $data);
    }

    public function testGetCurrentUser(): void
    {
        $user = $this->createUser('testuser');

        $request = $this->createRequest('GET', '/users/me', [], ['X-User-Token' => $user['token']]);
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals('testuser', $data['user']['username']);
        $this->assertArrayNotHasKey('token', $data['user']);
    }

    public function testGetCurrentUserWithoutToken(): void
    {
        $request = $this->createRequest('GET', '/users/me');
        $response = $this->app->handle($request);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetCurrentUserWithInvalidToken(): void
    {
        $request = $this->createRequest('GET', '/users/me', [], ['X-User-Token' => 'invalidtoken']);
        $response = $this->app->handle($request);

        $this->assertEquals(401, $response->getStatusCode());
    }
}
