<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Messaging\Controllers\Api;

use kintai\Bundles\Installed\Messaging\Controllers\Api\MessageController;
use kintai\Bundles\Installed\Messaging\Repositories\MessageRepositoryInterface;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Régression (audit du 03/10/2026), repris du Core à l'extraction : les créations de l'API fusionnaient le JSON brut du
 * client dans save(), qui fait un upsert dès qu'un « id » est présent. Un POST portant un « id » écrasait donc le
 * message ou la participation de quelqu'un d'autre.
 *
 * L'appelant (id 2) est membre du magasin 5 ; le magasin 6 ne le concerne pas.
 */
final class MessageMassAssignmentTest extends TestCase
{
    private const ME       = 2;
    private const MY_STORE = 5;
    private const OTHER    = 6;

    private StoreUserRepositoryInterface $storeUsers;

    protected function setUp(): void
    {
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $this->storeUsers->method('findMembership')->willReturnCallback(
            fn(int $storeId, int $userId): ?array => $storeId === self::MY_STORE && $userId === self::ME
                ? ['store_id' => $storeId, 'user_id' => $userId]
                : null
        );
        $this->storeUsers->method('findByUser')->willReturn([['store_id' => self::MY_STORE]]);
    }

    private function request(array $json, array $params = []): Request
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => self::ME]);
        $req->setRouteParams($params);
        (new \ReflectionProperty(Request::class, 'jsonBody'))->setValue($req, $json);
        return $req;
    }

    public function testMessageCreationIgnoresIdSoItCannotHijackAnotherThreadsMessage(): void
    {
        $repo = $this->createMock(MessageRepositoryInterface::class);
        $repo->method('findThreadById')->willReturn(['id' => 10]);
        $repo->method('findParticipant')->willReturn(['thread_id' => 10, 'user_id' => self::ME]);
        $repo->expects($this->once())->method('saveMessage')->with($this->callback(
            fn(array $d): bool => !array_key_exists('id', $d)
                && $d['thread_id'] === 10
                && $d['sender_id'] === self::ME
                && $d['body'] === 'bonjour'
        ))->willReturn(['id' => 77]);

        (new MessageController($repo, $this->storeUsers))
            ->addMessage($this->request(['id' => 55, 'thread_id' => 99, 'sender_id' => 1, 'body' => 'bonjour'], ['id' => '10']));
    }

    public function testParticipantCreationIgnoresIdAndOtherFields(): void
    {
        $repo = $this->createMock(MessageRepositoryInterface::class);
        $repo->method('findThreadById')->willReturn(['id' => 10]);
        $repo->method('findParticipant')->willReturn(['thread_id' => 10, 'user_id' => self::ME]);
        $repo->expects($this->once())->method('saveParticipant')->with($this->callback(
            fn(array $d): bool => !array_key_exists('id', $d)
                && $d['thread_id'] === 10
                && $d['user_id'] === self::ME
                && $d['is_read'] === 0
        ))->willReturn(['id' => 4]);

        (new MessageController($repo, $this->storeUsers))
            ->addParticipant($this->request(['id' => 9, 'thread_id' => 99, 'user_id' => self::ME, 'is_read' => 1], ['id' => '10']));
    }

    public function testThreadCreationInAStoreTheCallerDoesNotBelongToIsForbidden(): void
    {
        $repo = $this->createMock(MessageRepositoryInterface::class);
        $repo->expects($this->never())->method('saveThread');

        $this->expectException(ForbiddenException::class);
        (new MessageController($repo, $this->storeUsers))->createThread($this->request(['store_id' => self::OTHER, 'subject' => 's']));
    }
}
