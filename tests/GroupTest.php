<?php

declare(strict_types=1);

namespace Tests;

class GroupTest extends TestCase
{
    private array $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser('testuser');
    }

    public function testCreateGroup(): void
    {
        $request = $this->createRequest(
            'POST',
            '/groups',
            ['name' => 'Test Group'],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('group', $data);
        $this->assertEquals('Test Group', $data['group']['name']);
        $this->assertEquals($this->user['id'], $data['group']['created_by']);
        $this->assertEquals(1, $data['group']['member_count']);
    }

    public function testCreateGroupWithMissingName(): void
    {
        $request = $this->createRequest(
            'POST',
            '/groups',
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreateGroupWithDuplicateName(): void
    {
        $this->createGroup('Existing Group', $this->user['id']);

        $request = $this->createRequest(
            'POST',
            '/groups',
            ['name' => 'Existing Group'],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testListGroups(): void
    {
        $this->createGroup('Group 1', $this->user['id']);
        $this->createGroup('Group 2', $this->user['id']);

        $request = $this->createRequest(
            'GET',
            '/groups',
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('groups', $data);
        $this->assertCount(2, $data['groups']);
    }

    public function testGetGroupDetails(): void
    {
        $group = $this->createGroup('Test Group', $this->user['id']);

        $request = $this->createRequest(
            'GET',
            '/groups/' . $group['id'],
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('group', $data);
        $this->assertEquals('Test Group', $data['group']['name']);
        $this->assertArrayHasKey('members', $data['group']);
    }

    public function testGetNonExistentGroup(): void
    {
        $request = $this->createRequest(
            'GET',
            '/groups/9999',
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testJoinGroup(): void
    {
        $creator = $this->createUser('creator');
        $group = $this->createGroup('Test Group', $creator['id']);

        $request = $this->createRequest(
            'POST',
            '/groups/' . $group['id'] . '/join',
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertEquals('Successfully joined the group', $data['message']);
        $this->assertEquals(2, $data['group']['member_count']);
    }

    public function testJoinGroupAlreadyMember(): void
    {
        $group = $this->createGroup('Test Group', $this->user['id']);

        $request = $this->createRequest(
            'POST',
            '/groups/' . $group['id'] . '/join',
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertEquals('Already a member of this group', $data['message']);
    }

    public function testJoinNonExistentGroup(): void
    {
        $request = $this->createRequest(
            'POST',
            '/groups/9999/join',
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGetGroupMembers(): void
    {
        $group = $this->createGroup('Test Group', $this->user['id']);
        $user2 = $this->createUser('user2');
        $this->addGroupMember($group['id'], $user2['id']);

        $request = $this->createRequest(
            'GET',
            '/groups/' . $group['id'] . '/members',
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('members', $data);
        $this->assertCount(2, $data['members']);
    }
}
