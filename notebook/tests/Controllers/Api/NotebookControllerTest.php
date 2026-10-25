<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Notebook\Controllers\Api;

use kintai\Bundles\Installed\Notebook\Controllers\Api\NotebookController;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Bundles\Installed\Notebook\Repositories\NotebookEntryRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Request;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class NotebookControllerTest extends TestCase
{
    private NotebookEntryRepositoryInterface&Stub $notes;
    private RoleAssignmentRepositoryInterface&Stub $assignments;
    private RoleRepositoryInterface&Stub $roles;
    private ?NotebookController $controller = null;

    protected function setUp(): void
    {
        $this->notes       = $this->createStub(NotebookEntryRepositoryInterface::class);
        $this->assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $this->roles       = $this->createStub(RoleRepositoryInterface::class);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): NotebookController
    {
        if ($this->controller === null) {
            $this->controller = new NotebookController(
                $this->notes,
                new PermissionService($this->assignments, $this->roles),
            );
        }

        return $this->controller;
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    private function grantStoreScoped(int $userId, int $storeId, array $permissions): void
    {
        $this->assignments->method('findByUser')->willReturnCallback(
            fn(int $uid) => $uid === $userId
                ? [['id' => 1, 'user_id' => $uid, 'role_id' => 5, 'scope_type' => 'store', 'scope_id' => $storeId]]
                : []
        );
        $this->roles->method('findById')->willReturnCallback(fn($a0) => $a0 === 5 ? ['id' => 5, 'is_system' => 0] : null);
        $this->roles->method('getPermissions')->willReturnCallback(fn($a0) => $a0 === 5 ? $permissions : []);
        $this->roles->method('getGlobalPermissionKeys')->willReturnCallback(fn($a0) => $a0 === 5 ? [] : []);
    }

    /** Rôle système en portée globale (Owner) : aucune restriction de magasin. */
    private function grantGlobal(int $userId): void
    {
        $this->assignments->method('findByUser')->willReturnCallback(
            fn(int $uid) => $uid === $userId
                ? [['id' => 1, 'user_id' => $uid, 'role_id' => 1, 'scope_type' => 'global', 'scope_id' => null]]
                : []
        );
        $this->roles->method('findById')->willReturnCallback(fn($a0) => $a0 === 1 ? ['id' => 1, 'is_system' => 1] : null);
        $this->roles->method('getGlobalPermissionKeys')->willReturnCallback(fn($a0) => $a0 === 1 ? [] : []);
    }

    private function requestFor(int $userId): Request
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => $userId]);
        return $req;
    }

    private function withJsonBody(Request $req, array $body): Request
    {
        $ref = new \ReflectionProperty(Request::class, 'jsonBody');
        $ref->setAccessible(true);
        $ref->setValue($req, $body);
        return $req;
    }

    public function testIndexRestrictsResultsToTheUsersGrantedScope(): void
    {
        $this->grantStoreScoped(9, 1, ['notebook.view']);
        $this->notes->method('findAll')->willReturn([
            ['id' => 1, 'store_id' => 1, 'content' => 'a'],
            ['id' => 2, 'store_id' => 2, 'content' => 'b'],
        ]);

        $response = $this->controller()->index($this->requestFor(9));
        $data     = json_decode($response->body(), true);

        $this->assertSame([1], array_column($data['data'], 'id'));
    }

    public function testShowThrowsNotFoundForAnUnknownId(): void
    {
        $this->grantStoreScoped(9, 1, ['notebook.view']);
        $this->notes->method('findById')->willReturn(null);

        $req = $this->requestFor(9);
        $req->setRouteParams(['id' => '999']);

        $this->expectException(NotFoundException::class);
        $this->controller()->show($req);
    }

    public function testShowIsAllowedForTheAuthorEvenWithoutPermission(): void
    {
        $this->grantStoreScoped(9, 1, []); // aucune permission accordée
        $this->notes->method('findById')->willReturn(['id' => 5, 'author_id' => 9, 'store_id' => 1]);

        $req = $this->requestFor(9);
        $req->setRouteParams(['id' => '5']);

        $response = $this->controller()->show($req);
        $data     = json_decode($response->body(), true);

        $this->assertSame(5, $data['id']);
    }

    public function testShowForbidsANonAuthorWithoutThePermission(): void
    {
        $this->grantStoreScoped(2, 1, []); // pas notebook.view
        $this->notes->method('findById')->willReturn(['id' => 5, 'author_id' => 9, 'store_id' => 1]);

        $req = $this->requestFor(2);
        $req->setRouteParams(['id' => '5']);

        $this->expectException(ForbiddenException::class);
        $this->controller()->show($req);
    }

    public function testStoreSetsTheAuthenticatedUserAsAuthorRegardlessOfPayload(): void
    {
        $this->notes = $this->createMock(NotebookEntryRepositoryInterface::class);
        $this->grantGlobal(9); // note « organisation » : portée globale requise
        $this->notes->method('save')->willReturnCallback(fn(array $data) => $data + ['id' => 1]);
        $this->notes->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['author_id'] === 9 && $data['pinned'] === 0
        ));

        $req = $this->withJsonBody($this->requestFor(9), ['content' => 'hello', 'author_id' => 999]);

        $response = $this->controller()->store($req);

        $this->assertSame(201, $response->status());
    }

    public function testUpdateStripsAuthorIdFromThePayloadSoItCanNeverChangeOwnership(): void
    {
        $this->notes = $this->createMock(NotebookEntryRepositoryInterface::class);
        $this->grantStoreScoped(9, 1, []); // auteur, pas besoin de permission
        $this->notes->method('findById')->willReturn(['id' => 5, 'author_id' => 9, 'store_id' => 1]);
        $this->notes->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => !array_key_exists('author_id', $data) && $data['content'] === 'updated'
        ));

        $req = $this->withJsonBody($this->requestFor(9), ['content' => 'updated', 'author_id' => 999]);
        $req->setRouteParams(['id' => '5']);

        $this->controller()->update($req);
    }

    public function testDestroyForbidsANonAuthorWithoutNotebookCreatePermission(): void
    {
        $this->notes = $this->createMock(NotebookEntryRepositoryInterface::class);
        $this->grantStoreScoped(2, 1, []); // pas notebook.create
        $this->notes->method('findById')->willReturn(['id' => 5, 'author_id' => 9, 'store_id' => 1]);
        $this->notes->expects($this->never())->method('delete');

        $req = $this->requestFor(2);
        $req->setRouteParams(['id' => '5']);

        $this->expectException(ForbiddenException::class);
        $this->controller()->destroy($req);
    }

    public function testDestroyAllowsAUserHoldingNotebookCreateOnTheStore(): void
    {
        $this->notes = $this->createMock(NotebookEntryRepositoryInterface::class);
        $this->grantStoreScoped(2, 1, ['notebook.create']);
        $this->notes->method('findById')->willReturn(['id' => 5, 'author_id' => 9, 'store_id' => 1]);
        $this->notes->expects($this->once())->method('delete')->with(5);

        $req = $this->requestFor(2);
        $req->setRouteParams(['id' => '5']);

        $response = $this->controller()->destroy($req);

        $this->assertSame(204, $response->status());
    }

    // --- Audit du 03/10/2026 : liste blanche à la création et à la modification ---

    public function testStoreIgnoresIdSoItCannotOverwriteSomeoneElsesNote(): void
    {
        $this->notes = $this->createMock(NotebookEntryRepositoryInterface::class);
        $this->grantStoreScoped(9, 1, ['notebook.create']);
        $this->notes->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => !array_key_exists('id', $data)
                && $data['author_id'] === 9
                && $data['pinned'] === 0
                && $data['store_id'] === 1
        ))->willReturn(['id' => 50]);

        $req = $this->withJsonBody($this->requestFor(9), ['id' => 5, 'content' => 'x', 'store_id' => 1, 'pinned' => 1, 'author_id' => 1]);

        $this->assertSame(201, $this->controller()->store($req)->status());
    }

    public function testStoreForbidsAnOrganisationWideNoteWithoutGlobalScope(): void
    {
        $this->notes = $this->createMock(NotebookEntryRepositoryInterface::class);
        $this->grantStoreScoped(9, 1, ['notebook.create']);
        $this->notes->expects($this->never())->method('save');

        $this->expectException(ForbiddenException::class);
        $this->controller()->store($this->withJsonBody($this->requestFor(9), ['content' => 'pour tous']));
    }

    public function testStoreForbidsANoteInAStoreTheCallerDoesNotManage(): void
    {
        $this->notes = $this->createMock(NotebookEntryRepositoryInterface::class);
        $this->grantStoreScoped(9, 1, ['notebook.create']);
        $this->notes->expects($this->never())->method('save');

        $this->expectException(ForbiddenException::class);
        $this->controller()->store($this->withJsonBody($this->requestFor(9), ['content' => 'x', 'store_id' => 2]));
    }

    public function testUpdateCannotMoveTheNoteNorPinItNorChangeItsCreationDate(): void
    {
        $this->notes = $this->createMock(NotebookEntryRepositoryInterface::class);
        $this->grantStoreScoped(9, 1, []); // auteur, sans notebook.manage
        $this->notes->method('findById')->willReturn(['id' => 5, 'author_id' => 9, 'store_id' => 1]);
        $this->notes->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data === ['id' => 5, 'content' => 'updated']
        ));

        $req = $this->withJsonBody($this->requestFor(9), ['content' => 'updated', 'store_id' => null, 'pinned' => 1, 'created_at' => '2000-01-01', 'id' => 99]);
        $req->setRouteParams(['id' => '5']);

        $this->controller()->update($req);
    }
}
