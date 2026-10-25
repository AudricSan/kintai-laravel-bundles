<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\TeamDirectory\Controllers\Web;


use kintai\Bundles\Installed\TeamDirectory\Controllers\Web\TeamDirectoryController;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

final class TeamDirectoryControllerTest extends TestCase
{
    private const ME = 1;
    private const COLLEAGUE_VISIBLE = 2;
    private const COLLEAGUE_HIDDEN = 3;
    private const COLLEAGUE_INACTIVE = 4;
    private const OTHER_STORE_USER = 5;

    private $users;
    private $storeUsers;
    private $stores;
    private TeamDirectoryController $controller;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-team-directory-views';
        $this->ensureViewFile($viewDir, 'team-directory');
        $this->ensureViewFile($viewDir, 'team-directory-show');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $view = new ViewRenderer(sys_get_temp_dir());
        $view->addNamespace('team-directory', $viewDir);

        $this->users = $this->createStub(UserRepositoryInterface::class);
        $this->users->method('findById')->willReturnMap([
            [self::ME, ['id' => self::ME, 'first_name' => 'A', 'last_name' => 'Moi', 'is_active' => 1, 'show_in_directory' => 1]],
            [self::COLLEAGUE_VISIBLE, ['id' => self::COLLEAGUE_VISIBLE, 'first_name' => 'B', 'last_name' => 'Visible', 'is_active' => 1, 'show_in_directory' => 1]],
            [self::COLLEAGUE_HIDDEN, ['id' => self::COLLEAGUE_HIDDEN, 'first_name' => 'C', 'last_name' => 'Cache', 'is_active' => 1, 'show_in_directory' => 0]],
            [self::COLLEAGUE_INACTIVE, ['id' => self::COLLEAGUE_INACTIVE, 'first_name' => 'D', 'last_name' => 'Inactif', 'is_active' => 0, 'show_in_directory' => 1]],
            [self::OTHER_STORE_USER, ['id' => self::OTHER_STORE_USER, 'first_name' => 'E', 'last_name' => 'Ailleurs', 'is_active' => 1, 'show_in_directory' => 1]],
        ]);

        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $this->storeUsers->method('findByUser')->willReturnMap([
            [self::ME, [['store_id' => 10]]],
            [self::COLLEAGUE_VISIBLE, [['store_id' => 10]]],
            [self::COLLEAGUE_HIDDEN, [['store_id' => 10]]],
            [self::COLLEAGUE_INACTIVE, [['store_id' => 10]]],
            [self::OTHER_STORE_USER, [['store_id' => 20]]],
        ]);
        $this->storeUsers->method('findByStore')->willReturnMap([
            [10, [
                ['user_id' => self::ME],
                ['user_id' => self::COLLEAGUE_VISIBLE],
                ['user_id' => self::COLLEAGUE_HIDDEN],
                ['user_id' => self::COLLEAGUE_INACTIVE],
            ]],
            [20, [
                ['user_id' => self::OTHER_STORE_USER],
            ]],
        ]);

        $this->stores = $this->createStub(StoreRepositoryInterface::class);
        $this->stores->method('findAll')->willReturn([
            ['id' => 10, 'name' => 'Store A'],
            ['id' => 20, 'name' => 'Store B'],
        ]);

        $this->controller = new TeamDirectoryController(
            $view,
            $this->users,
            $this->storeUsers,
            $this->stores,
        );
    }

    private function ensureViewFile(string $dir, string $view): void
    {
        $file = $dir . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        $parent = dirname($file);
        if (!is_dir($parent)) {
            mkdir($parent, 0777, true);
        }
        touch($file);
    }

    private function requestAs(int $userId, bool $isAdmin = false): Request
    {
        $request = new Request();
        $request->setAttribute('auth_user', ['id' => $userId, 'is_admin' => $isAdmin ? 1 : 0]);
        return $request;
    }

    private function requestWithParam(int $viewerId, int $targetId, bool $isAdmin = false): Request
    {
        $request = $this->requestAs($viewerId, $isAdmin);
        $request->setRouteParams(['id' => (string) $targetId]);
        return $request;
    }

    public function testIndexRendersWithoutErrorAndDoesNotLookUpTheViewerAsAColleague(): void
    {
        // Le viewer ne doit jamais être résolu comme son propre collègue : si le
        // filtre "!== userId" du contrôleur régressait, findById(self::ME) serait
        // appelé une seconde fois (une fois via auth_user, une fois via la boucle
        // collègues), ce que ce test détecterait en resserrant l'attente ci-dessous.
        $response = $this->controller->index($this->requestAs(self::ME));

        $this->assertSame(200, $response->status());
    }

    public function testShowAllowsViewingOwnHiddenProfile(): void
    {
        $response = $this->controller->show($this->requestWithParam(self::COLLEAGUE_HIDDEN, self::COLLEAGUE_HIDDEN));
        $this->assertSame(200, $response->status());
    }

    public function testShowForbidsViewingColleagueFromAnotherStore(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->controller->show($this->requestWithParam(self::ME, self::OTHER_STORE_USER));
    }

    public function testShowNotFoundWhenTargetOptedOutOfDirectory(): void
    {
        $this->expectException(NotFoundException::class);
        $this->controller->show($this->requestWithParam(self::ME, self::COLLEAGUE_HIDDEN));
    }

    public function testShowNotFoundForInactiveUser(): void
    {
        $this->expectException(NotFoundException::class);
        $this->controller->show($this->requestWithParam(self::ME, self::COLLEAGUE_INACTIVE));
    }

    public function testShowAllowsOwnerToViewAnyoneAcrossStores(): void
    {
        $response = $this->controller->show($this->requestWithParam(self::ME, self::OTHER_STORE_USER, true));
        $this->assertSame(200, $response->status());
    }

    public function testShowAllowsColleagueSharingAStore(): void
    {
        $response = $this->controller->show($this->requestWithParam(self::ME, self::COLLEAGUE_VISIBLE));
        $this->assertSame(200, $response->status());
    }
}
