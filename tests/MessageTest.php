<?php

declare(strict_types=1);

namespace Tests;

class MessageTest extends TestCase
{
    private array $user;
    private array $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser('testuser');
        $this->group = $this->createGroup('Test Group', $this->user['id']);
    }

    public function testSendMessage(): void
    {
        $request = $this->createRequest(
            'POST',
            '/groups/' . $this->group['id'] . '/messages',
            ['content' => 'Hello, World!'],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals('Hello, World!', $data['data']['content']);
        $this->assertEquals($this->user['id'], $data['data']['user_id']);
        $this->assertEquals($this->user['username'], $data['data']['username']);
    }

    public function testSendMessageWithMissingContent(): void
    {
        $request = $this->createRequest(
            'POST',
            '/groups/' . $this->group['id'] . '/messages',
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testSendMessageToNonExistentGroup(): void
    {
        $request = $this->createRequest(
            'POST',
            '/groups/9999/messages',
            ['content' => 'Hello'],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSendMessageToGroupNotMemberOf(): void
    {
        $otherUser = $this->createUser('otheruser');
        $otherGroup = $this->createGroup('Other Group', $otherUser['id']);

        $request = $this->createRequest(
            'POST',
            '/groups/' . $otherGroup['id'] . '/messages',
            ['content' => 'Hello'],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGetMessages(): void
    {
        $this->createMessage($this->group['id'], $this->user['id'], 'Message 1');
        $this->createMessage($this->group['id'], $this->user['id'], 'Message 2');
        $this->createMessage($this->group['id'], $this->user['id'], 'Message 3');

        $request = $this->createRequest(
            'GET',
            '/groups/' . $this->group['id'] . '/messages',
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('messages', $data);
        $this->assertCount(3, $data['messages']);
        $this->assertEquals(3, $data['total']);
        $this->assertEquals('Message 1', $data['messages'][0]['content']);
    }

    public function testGetMessagesWithLimit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createMessage($this->group['id'], $this->user['id'], "Message $i");
        }

        $request = $this->createRequest(
            'GET',
            '/groups/' . $this->group['id'] . '/messages?limit=2',
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertCount(2, $data['messages']);
        $this->assertEquals(5, $data['total']);
    }

    public function testGetMessagesWithOffset(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createMessage($this->group['id'], $this->user['id'], "Message $i");
        }

        $request = $this->createRequest(
            'GET',
            '/groups/' . $this->group['id'] . '/messages?limit=2&offset=2',
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertCount(2, $data['messages']);
        $this->assertEquals('Message 3', $data['messages'][0]['content']);
    }

    public function testGetMessagesFromGroupNotMemberOf(): void
    {
        $otherUser = $this->createUser('otheruser');
        $otherGroup = $this->createGroup('Other Group', $otherUser['id']);

        $request = $this->createRequest(
            'GET',
            '/groups/' . $otherGroup['id'] . '/messages',
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGetMessagesFromNonExistentGroup(): void
    {
        $request = $this->createRequest(
            'GET',
            '/groups/9999/messages',
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGetMessagesAfterId(): void
    {
        $this->createMessage($this->group['id'], $this->user['id'], 'Message 1');
        $this->createMessage($this->group['id'], $this->user['id'], 'Message 2');

        $request = $this->createRequest(
            'GET',
            '/groups/' . $this->group['id'] . '/messages',
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);
        $data = $this->getResponseData($response);
        $lastMessageId = end($data['messages'])['id'];

        $this->createMessage($this->group['id'], $this->user['id'], 'Message 3');
        $this->createMessage($this->group['id'], $this->user['id'], 'Message 4');

        $request = $this->createRequest(
            'GET',
            '/groups/' . $this->group['id'] . '/messages?after_id=' . $lastMessageId,
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertCount(2, $data['messages']);
        $this->assertEquals('Message 3', $data['messages'][0]['content']);
        $this->assertEquals('Message 4', $data['messages'][1]['content']);
    }

    public function testGetMessagesAfterIdReturnsEmptyWhenNoNewMessages(): void
    {
        $this->createMessage($this->group['id'], $this->user['id'], 'Message 1');

        $request = $this->createRequest(
            'GET',
            '/groups/' . $this->group['id'] . '/messages',
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);
        $data = $this->getResponseData($response);
        $lastMessageId = $data['messages'][0]['id'];

        $request = $this->createRequest(
            'GET',
            '/groups/' . $this->group['id'] . '/messages?after_id=' . $lastMessageId,
            [],
            ['X-User-Token' => $this->user['token']]
        );
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $data = $this->getResponseData($response);
        $this->assertCount(0, $data['messages']);
    }

    private function createMessage(int $groupId, int $userId, string $content): int
    {
        $stmt = $this->db->prepare('INSERT INTO messages (group_id, user_id, content) VALUES (?, ?, ?)');
        $stmt->execute([$groupId, $userId, $content]);
        return (int) $this->db->lastInsertId();
    }
}
