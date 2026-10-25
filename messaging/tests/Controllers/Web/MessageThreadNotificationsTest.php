<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Messaging\Controllers\Web;

use kintai\Bundles\Installed\Messaging\Controllers\Web\MessageController;
use kintai\Bundles\Installed\Messaging\Repositories\MessageRepositoryInterface;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Data\StoreUserRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Services\AuditLogger;
use kintai\Contract\Services\NotificationService;
use kintai\Contract\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

/** Ouvrir un fil marque comme lues les notifications « nouveau message » de ce fil, et seulement les siennes. */
final class MessageThreadNotificationsTest extends TestCase
{
    private function controller(MessageRepositoryInterface $repo, NotificationService $notifs): MessageController
    {
        $dir = sys_get_temp_dir() . '/kintai-messaging-views';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        touch($dir . '/messages-thread.php');
        if (!is_dir(sys_get_temp_dir() . '/layout')) {
            mkdir(sys_get_temp_dir() . '/layout', 0777, true);
        }
        touch(sys_get_temp_dir() . '/layout/app.php');

        $view = new ViewRenderer(sys_get_temp_dir());
        $view->addNamespace('messaging', $dir);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findAll')->willReturn([]);
        $stores = $this->createStub(StoreRepositoryInterface::class);
        $stores->method('findAll')->willReturn([]);

        return new MessageController(
            $view,
            $users,
            $stores,
            $this->createStub(StoreUserRepositoryInterface::class),
            $repo,
            new AuditLogger(),
            $notifs,
        );
    }

    private function request(int $threadId): Request
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9, 'is_admin' => 1]);
        $req->setRouteParams(['id' => (string) $threadId]);

        return $req;
    }

    public function testOpeningAThreadMarksItsNotificationsAsRead(): void
    {
        $repo = $this->createStub(MessageRepositoryInterface::class);
        $repo->method('findThreadById')->willReturn(['id' => 12, 'store_id' => 1, 'subject' => 'Planning']);
        $repo->method('findParticipant')->willReturn(['thread_id' => 12, 'user_id' => 9, 'is_read' => 1]);
        $repo->method('findMessagesByThread')->willReturn([]);
        $repo->method('findParticipantsByThread')->willReturn([]);

        $notifs = $this->createMock(NotificationService::class);
        $notifs->expects($this->once())->method('markReadFor')->with(9, 'message_thread', 12);

        $this->assertSame(200, $this->controller($repo, $notifs)->messagesThread($this->request(12))->status());
    }

    public function testAThreadTheUserDoesNotBelongToMarksNothing(): void
    {
        $repo = $this->createStub(MessageRepositoryInterface::class);
        $repo->method('findThreadById')->willReturn(['id' => 12, 'store_id' => 1]);
        $repo->method('findParticipant')->willReturn(null);

        $notifs = $this->createMock(NotificationService::class);
        $notifs->expects($this->never())->method('markReadFor');

        $this->expectException(\kintai\Contract\Exceptions\ForbiddenException::class);
        $this->controller($repo, $notifs)->messagesThread($this->request(12));
    }
}
