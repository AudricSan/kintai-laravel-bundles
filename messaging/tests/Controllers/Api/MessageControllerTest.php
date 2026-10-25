<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Messaging\Controllers\Api;


use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Bundles\Installed\Messaging\Controllers\Api\MessageController;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Bundles\Installed\Messaging\Repositories\MessageRepositoryInterface;
use kintai\Core\Request;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Régression : ApiMessageController n'avait aucune vérification d'appartenance
 * malgré .wiki/API-Reference.md documentant messages.* comme "strictly scoped
 * to the token holder" — n'importe quel token valide pouvait lire/supprimer
 * les threads et messages de n'importe quel autre utilisateur.
 */
final class MessageControllerTest extends TestCase
{
    private MessageRepositoryInterface&Stub $messages;
    private ?MessageController $controller = null;
    private StoreUserRepositoryInterface&Stub $storeUsers;

    protected function setUp(): void
    {
        $this->messages  = $this->createStub(MessageRepositoryInterface::class);
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): MessageController
    {
        if ($this->controller === null) {
            $this->controller = new MessageController($this->messages, $this->storeUsers);
        }

        return $this->controller;
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    private function requestAs(int $userId, array $json = []): Request
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => $userId]);
        if ($json !== []) {
            $ref = new \ReflectionProperty(Request::class, 'jsonBody');
            $ref->setAccessible(true);
            $ref->setValue($req, $json);
        }
        return $req;
    }

    // ── listThreads ──────────────────────────────────────────────────────────

    public function testListThreadsAlwaysUsesTokenHolderRegardlessOfUserIdQuery(): void
    {
        $this->messages = $this->createMock(MessageRepositoryInterface::class);
        $_GET = ['user_id' => '999'];
        $req  = $this->requestAs(42);

        $this->messages->expects($this->once())
            ->method('findParticipationsByUser')
            ->with(42)
            ->willReturn([]);

        $response = $this->controller()->listThreads($req);
        $this->assertSame(200, $response->status());
    }

    // ── createThread ─────────────────────────────────────────────────────────

    public function testCreateThreadForcesCreatorIdFromTokenAndAddsSelfAsParticipant(): void
    {
        $this->messages = $this->createMock(MessageRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        // L'appelant est membre du magasin 3, comme le participant ajouté.
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(3, 42)->willReturn(['store_id' => 3, 'user_id' => 42]);
        $this->storeUsers->method('findByUser')->willReturn([['store_id' => 3]]);

        $req = $this->requestAs(42, [
            'store_id'         => 3,
            'subject'          => 'Sujet',
            'creator_id'       => 999, // tentative de spoof, doit être ignorée
            'participant_ids'  => [7, 42], // 42 (soi-même) ne doit pas être dupliqué
        ]);

        $this->messages->expects($this->once())
            ->method('saveThread')
            ->with($this->callback(fn($data) => $data['creator_id'] === 42 && $data['store_id'] === 3))
            ->willReturn(['id' => 5, 'creator_id' => 42, 'store_id' => 3]);

        $participantCalls = [];
        $this->messages->expects($this->exactly(2))
            ->method('saveParticipant')
            ->willReturnCallback(function (array $data) use (&$participantCalls) {
                $participantCalls[] = $data['user_id'];
                return $data;
            });

        $response = $this->controller()->createThread($req);

        $this->assertSame(201, $response->status());
        $this->assertSame([42, 7], $participantCalls);
    }

    // ── getThread ─────────────────────────────────────────────────────────────

    public function testGetThreadReturns404ForNonParticipant(): void
    {
        $this->messages = $this->createMock(MessageRepositoryInterface::class);
        $req = $this->requestAs(42);
        $req->setRouteParams(['id' => '10']);

        $this->messages->expects($this->atLeastOnce())->method('findThreadById')->with(10)->willReturn(['id' => 10, 'store_id' => 3]);
        $this->messages->expects($this->atLeastOnce())->method('findParticipant')->with(10, 42)->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->controller()->getThread($req);
    }

    public function testGetThreadReturns404ForUnknownThread(): void
    {
        $this->messages = $this->createMock(MessageRepositoryInterface::class);
        $req = $this->requestAs(42);
        $req->setRouteParams(['id' => '999']);

        $this->messages->expects($this->atLeastOnce())->method('findThreadById')->with(999)->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->controller()->getThread($req);
    }

    public function testGetThreadSucceedsForParticipant(): void
    {
        $this->messages = $this->createMock(MessageRepositoryInterface::class);
        $req = $this->requestAs(42);
        $req->setRouteParams(['id' => '10']);

        $this->messages->expects($this->atLeastOnce())->method('findThreadById')->with(10)->willReturn(['id' => 10, 'store_id' => 3]);
        $this->messages->expects($this->atLeastOnce())->method('findParticipant')->with(10, 42)->willReturn(['thread_id' => 10, 'user_id' => 42]);

        $response = $this->controller()->getThread($req);
        $this->assertSame(200, $response->status());
    }

    // ── deleteThread ─────────────────────────────────────────────────────────

    public function testDeleteThreadRejectsNonParticipant(): void
    {
        $this->messages = $this->createMock(MessageRepositoryInterface::class);
        $req = $this->requestAs(42);
        $req->setRouteParams(['id' => '10']);

        $this->messages->expects($this->atLeastOnce())->method('findThreadById')->with(10)->willReturn(['id' => 10]);
        $this->messages->expects($this->atLeastOnce())->method('findParticipant')->with(10, 42)->willReturn(null);
        $this->messages->expects($this->never())->method('deleteThread');

        $this->expectException(NotFoundException::class);
        $this->controller()->deleteThread($req);
    }

    // ── listMessages / addMessage ───────────────────────────────────────────

    public function testListMessagesRejectsNonParticipant(): void
    {
        $this->messages = $this->createMock(MessageRepositoryInterface::class);
        $req = $this->requestAs(42);
        $req->setRouteParams(['id' => '10']);

        $this->messages->expects($this->atLeastOnce())->method('findThreadById')->with(10)->willReturn(['id' => 10]);
        $this->messages->expects($this->atLeastOnce())->method('findParticipant')->with(10, 42)->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->controller()->listMessages($req);
    }

    public function testAddMessageForcesSenderIdFromToken(): void
    {
        $this->messages = $this->createMock(MessageRepositoryInterface::class);
        $req = $this->requestAs(42, ['body' => 'salut', 'sender_id' => 999]);
        $req->setRouteParams(['id' => '10']);

        $this->messages->expects($this->atLeastOnce())->method('findThreadById')->with(10)->willReturn(['id' => 10]);
        $this->messages->expects($this->atLeastOnce())->method('findParticipant')->with(10, 42)->willReturn(['thread_id' => 10, 'user_id' => 42]);

        $this->messages->expects($this->once())
            ->method('saveMessage')
            ->with($this->callback(fn($data) => $data['sender_id'] === 42 && $data['thread_id'] === 10))
            ->willReturn(['id' => 1]);

        $response = $this->controller()->addMessage($req);
        $this->assertSame(201, $response->status());
    }

    // ── deleteMessage ────────────────────────────────────────────────────────

    public function testDeleteMessageRejectsNonSender(): void
    {
        $this->messages = $this->createMock(MessageRepositoryInterface::class);
        $req = $this->requestAs(42);
        $req->setRouteParams(['id' => '7']);

        $this->messages->expects($this->atLeastOnce())->method('findMessageById')->with(7)->willReturn(['id' => 7, 'sender_id' => 999]);
        $this->messages->expects($this->never())->method('deleteMessage');

        $this->expectException(ForbiddenException::class);
        $this->controller()->deleteMessage($req);
    }

    public function testDeleteMessageSucceedsForSender(): void
    {
        $this->messages = $this->createMock(MessageRepositoryInterface::class);
        $req = $this->requestAs(42);
        $req->setRouteParams(['id' => '7']);

        $this->messages->expects($this->atLeastOnce())->method('findMessageById')->with(7)->willReturn(['id' => 7, 'sender_id' => 42]);
        $this->messages->expects($this->once())->method('deleteMessage')->with(7);

        $response = $this->controller()->deleteMessage($req);
        $this->assertSame(204, $response->status());
    }

    // ── participants ─────────────────────────────────────────────────────────

    public function testListParticipantsRejectsNonParticipant(): void
    {
        $this->messages = $this->createMock(MessageRepositoryInterface::class);
        $req = $this->requestAs(42);
        $req->setRouteParams(['id' => '10']);

        $this->messages->expects($this->atLeastOnce())->method('findThreadById')->with(10)->willReturn(['id' => 10]);
        $this->messages->expects($this->atLeastOnce())->method('findParticipant')->with(10, 42)->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->controller()->listParticipants($req);
    }

    public function testAddParticipantRejectsNonParticipant(): void
    {
        $this->messages = $this->createMock(MessageRepositoryInterface::class);
        $req = $this->requestAs(42, ['user_id' => 7]);
        $req->setRouteParams(['id' => '10']);

        $this->messages->expects($this->atLeastOnce())->method('findThreadById')->with(10)->willReturn(['id' => 10]);
        $this->messages->expects($this->atLeastOnce())->method('findParticipant')->with(10, 42)->willReturn(null);
        $this->messages->expects($this->never())->method('saveParticipant');

        $this->expectException(NotFoundException::class);
        $this->controller()->addParticipant($req);
    }

    public function testGetParticipantRejectsCallerNotInThread(): void
    {
        $this->messages = $this->createMock(MessageRepositoryInterface::class);
        $req = $this->requestAs(42);
        $req->setRouteParams(['id' => '10', 'user_id' => '7']);

        $this->messages->expects($this->atLeastOnce())->method('findThreadById')->with(10)->willReturn(['id' => 10]);
        $this->messages->expects($this->atLeastOnce())->method('findParticipant')->with(10, 42)->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->controller()->getParticipant($req);
    }
}
