<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Request,Response};
use App\Repositories\WorkflowRepository;
use App\Support\Flash;
use DomainException;

/**
 * Internal messaging for portal users — the equivalent of eSkooly's StudentPortal/MessageBox.php.
 *
 * Reuses WorkflowRepository, whose messaging methods already enforce participant access and
 * institute scoping, so a portal user cannot read or post into a conversation they are not in.
 */
final class PortalMessagesController extends PortalController
{
    public function index(Request $request): Response
    {
        if (!$identity = $this->identity()) {
            return $this->forbidden();
        }
        $repo = new WorkflowRepository($this->app->database());
        return $this->portalView('portal/messages', $identity['audience'], [
            'pageTitle' => 'Messages',
            'activeMenu' => 'portal-messages',
            'instituteId' => $identity['instituteId'],
            'conversations' => $repo->conversations($identity['instituteId'], $this->userId()),
            'recipients' => $repo->messageRecipients($identity['instituteId'], $this->userId()),
            'thread' => null,
        ]);
    }

    public function show(Request $request): Response
    {
        if (!$identity = $this->identity()) {
            return $this->forbidden();
        }
        $repo = new WorkflowRepository($this->app->database());
        $thread = $repo->conversation($identity['instituteId'], (int) $request->route('id'), $this->userId());
        if ($thread === null) {
            return Response::html('Conversation not found.', 404);
        }
        return $this->portalView('portal/messages', $identity['audience'], [
            'pageTitle' => 'Messages',
            'activeMenu' => 'portal-messages',
            'instituteId' => $identity['instituteId'],
            'conversations' => $repo->conversations($identity['instituteId'], $this->userId()),
            'recipients' => $repo->messageRecipients($identity['instituteId'], $this->userId()),
            'thread' => $thread,
        ]);
    }

    public function start(Request $request): Response
    {
        if (!$identity = $this->identity()) {
            return $this->forbidden();
        }
        $recipient = (int) $request->input('recipient_id');
        $body = trim((string) $request->input('body'));
        $subject = trim((string) $request->input('subject')) ?: null;
        if ($recipient <= 0 || $body === '') {
            Flash::add('error', 'Choose a recipient and write a message.');
            return Response::redirect(url('/portal/messages'));
        }
        try {
            $id = (new WorkflowRepository($this->app->database()))
                ->createConversation($identity['instituteId'], $this->userId(), $recipient, $body, $subject);
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
            return Response::redirect(url('/portal/messages'));
        }
        Flash::add('success', 'Message sent.');
        return Response::redirect(url('/portal/messages/' . $id));
    }

    public function reply(Request $request): Response
    {
        if (!$identity = $this->identity()) {
            return $this->forbidden();
        }
        $id = (int) $request->route('id');
        $body = trim((string) $request->input('body'));
        if ($body === '') {
            Flash::add('error', 'Write a message before sending.');
            return Response::redirect(url('/portal/messages/' . $id));
        }
        try {
            (new WorkflowRepository($this->app->database()))
                ->sendMessage($identity['instituteId'], $id, $this->userId(), $body);
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
            return Response::redirect(url('/portal/messages'));
        }
        Flash::add('success', 'Reply sent.');
        return Response::redirect(url('/portal/messages/' . $id));
    }
}
