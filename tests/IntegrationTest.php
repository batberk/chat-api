<?php

declare(strict_types=1);

namespace Tests;

class IntegrationTest extends TestCase
{
    public function testCompleteUserFlow(): void
    {
        $request = $this->createRequest('POST', '/users', ['username' => 'alice']);
        $response = $this->app->handle($request);
        $this->assertEquals(201, $response->getStatusCode());
        $aliceData = $this->getResponseData($response);
        $aliceToken = $aliceData['user']['token'];
        $aliceId = $aliceData['user']['id'];

        $request = $this->createRequest('POST', '/users', ['username' => 'bob']);
        $response = $this->app->handle($request);
        $this->assertEquals(201, $response->getStatusCode());
        $bobData = $this->getResponseData($response);
        $bobToken = $bobData['user']['token'];

        $request = $this->createRequest(
            'POST',
            '/groups',
            ['name' => 'General Chat'],
            ['X-User-Token' => $aliceToken]
        );
        $response = $this->app->handle($request);
        $this->assertEquals(201, $response->getStatusCode());
        $groupData = $this->getResponseData($response);
        $groupId = $groupData['group']['id'];
        $this->assertEquals('General Chat', $groupData['group']['name']);
        $this->assertEquals($aliceId, $groupData['group']['created_by']);

        $request = $this->createRequest('GET', '/groups', [], ['X-User-Token' => $aliceToken]);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $groupsData = $this->getResponseData($response);
        $this->assertCount(1, $groupsData['groups']);

        $request = $this->createRequest(
            'POST',
            "/groups/{$groupId}/join",
            [],
            ['X-User-Token' => $bobToken]
        );
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $joinData = $this->getResponseData($response);
        $this->assertEquals('Successfully joined the group', $joinData['message']);
        $this->assertEquals(2, $joinData['group']['member_count']);

        $request = $this->createRequest(
            'POST',
            "/groups/{$groupId}/messages",
            ['content' => 'Hello everyone!'],
            ['X-User-Token' => $aliceToken]
        );
        $response = $this->app->handle($request);
        $this->assertEquals(201, $response->getStatusCode());
        $messageData = $this->getResponseData($response);
        $this->assertEquals('Hello everyone!', $messageData['data']['content']);
        $this->assertEquals('alice', $messageData['data']['username']);
        $firstMessageId = $messageData['data']['id'];

        $request = $this->createRequest(
            'POST',
            "/groups/{$groupId}/messages",
            ['content' => 'Hi Alice! Nice to meet you.'],
            ['X-User-Token' => $bobToken]
        );
        $response = $this->app->handle($request);
        $this->assertEquals(201, $response->getStatusCode());

        $request = $this->createRequest(
            'GET',
            "/groups/{$groupId}/messages",
            [],
            ['X-User-Token' => $aliceToken]
        );
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $messagesData = $this->getResponseData($response);
        $this->assertCount(2, $messagesData['messages']);
        $this->assertEquals(2, $messagesData['total']);

        $request = $this->createRequest(
            'GET',
            "/groups/{$groupId}/messages?after_id={$firstMessageId}",
            [],
            ['X-User-Token' => $aliceToken]
        );
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $newMessagesData = $this->getResponseData($response);
        $this->assertCount(1, $newMessagesData['messages']);
        $this->assertEquals('Hi Alice! Nice to meet you.', $newMessagesData['messages'][0]['content']);

        $request = $this->createRequest(
            'GET',
            "/groups/{$groupId}",
            [],
            ['X-User-Token' => $aliceToken]
        );
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $groupDetails = $this->getResponseData($response);
        $this->assertCount(2, $groupDetails['group']['members']);
    }

    public function testUnauthorizedAccessIsBlocked(): void
    {
        $user = $this->createUser('owner');
        $group = $this->createGroup('Private Group', $user['id']);
        $outsider = $this->createUser('outsider');

        $request = $this->createRequest(
            'GET',
            "/groups/{$group['id']}/messages",
            [],
            ['X-User-Token' => $outsider['token']]
        );
        $response = $this->app->handle($request);
        $this->assertEquals(403, $response->getStatusCode());
        $data = $this->getResponseData($response);
        $this->assertStringContainsString('member', $data['error']);

        $request = $this->createRequest(
            'POST',
            "/groups/{$group['id']}/messages",
            ['content' => 'I should not be able to post here'],
            ['X-User-Token' => $outsider['token']]
        );
        $response = $this->app->handle($request);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testValidationErrors(): void
    {
        $request = $this->createRequest('POST', '/users', ['username' => '']);
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('error', $data);

        $user = $this->createUser('testuser');

        $request = $this->createRequest(
            'POST',
            '/groups',
            ['name' => 'ab'],
            ['X-User-Token' => $user['token']]
        );
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());

        $group = $this->createGroup('Test Group', $user['id']);

        $request = $this->createRequest(
            'POST',
            "/groups/{$group['id']}/messages",
            ['content' => '   '],
            ['X-User-Token' => $user['token']]
        );
        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testMultipleGroupsAndMessages(): void
    {
        $user = $this->createUser('testuser');

        $groups = [];
        for ($i = 1; $i <= 3; $i++) {
            $request = $this->createRequest(
                'POST',
                '/groups',
                ['name' => "Group {$i}"],
                ['X-User-Token' => $user['token']]
            );
            $response = $this->app->handle($request);
            $this->assertEquals(201, $response->getStatusCode());
            $data = $this->getResponseData($response);
            $groups[] = $data['group'];
        }

        foreach ($groups as $index => $group) {
            for ($j = 1; $j <= 5; $j++) {
                $request = $this->createRequest(
                    'POST',
                    "/groups/{$group['id']}/messages",
                    ['content' => "Message {$j} in Group " . ($index + 1)],
                    ['X-User-Token' => $user['token']]
                );
                $this->app->handle($request);
            }
        }

        foreach ($groups as $group) {
            $request = $this->createRequest(
                'GET',
                "/groups/{$group['id']}/messages",
                [],
                ['X-User-Token' => $user['token']]
            );
            $response = $this->app->handle($request);
            $data = $this->getResponseData($response);
            $this->assertCount(5, $data['messages']);
            $this->assertEquals(5, $data['total']);
        }

        $request = $this->createRequest(
            'GET',
            "/groups/{$groups[0]['id']}/messages?limit=2&offset=2",
            [],
            ['X-User-Token' => $user['token']]
        );
        $response = $this->app->handle($request);
        $data = $this->getResponseData($response);
        $this->assertCount(2, $data['messages']);
        $this->assertEquals('Message 3 in Group 1', $data['messages'][0]['content']);
    }
}
