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
            'recipients' => $this->allowedRecipients($identity, $repo),
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
            'recipients' => $this->allowedRecipients($identity, $repo),
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
        $repo = new WorkflowRepository($this->app->database());
        if (!in_array($recipient, array_map('intval', array_column($this->allowedRecipients($identity, $repo), 'id')), true)) {
            Flash::add('error', 'You can only message people connected to your classes, or the administration.');
            return Response::redirect(url('/portal/messages'));
        }
        try {
            $id = $repo
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

    /**
     * Who this portal user may start a conversation with. Teachers reach the students of their
     * assigned classes; students reach their own class's teachers; both can reach the
     * administration. Other staff keep the institute-wide list.
     */
    private function allowedRecipients(array $identity, WorkflowRepository $repo): array
    {
        $db = $this->app->database();
        $i = $identity['instituteId'];
        $admins = $db->query("SELECT iu.user_id FROM institute_users iu JOIN user_roles ur ON ur.user_id=iu.user_id JOIN roles r ON r.id=ur.role_id WHERE iu.institute_id=:i AND r.slug IN('admin','principal')", ['i' => $i])->fetchAll(\PDO::FETCH_COLUMN);
        if ($identity['audience'] === 'student') {
            $allowed = $db->query("SELECT DISTINCT ea.user_id FROM teacher_assignments ta JOIN employee_accounts ea ON ea.employee_id=ta.employee_id WHERE ta.institute_id=:i AND ta.status='active' AND ta.class_id=:c",
                ['i' => $i, 'c' => (int) $identity['record']['class_id']])->fetchAll(\PDO::FETCH_COLUMN);
        } elseif ($identity['audience'] === 'employee' && ($classes = $db->query("SELECT DISTINCT class_id FROM teacher_assignments WHERE employee_id=:e AND institute_id=:i AND status='active'", ['e' => (int) $identity['record']['id'], 'i' => $i])->fetchAll(\PDO::FETCH_COLUMN))) {
            $marks = implode(',', array_map('intval', $classes));
            $allowed = $db->query("SELECT sa.user_id FROM student_accounts sa JOIN students s ON s.id=sa.student_id WHERE s.institute_id=:i AND s.class_id IN ({$marks})", ['i' => $i])->fetchAll(\PDO::FETCH_COLUMN);
        } else {
            return $repo->messageRecipients($i, $this->userId());
        }
        // Filtered in the query itself: the list is capped, so filtering afterwards could drop the very people allowed.
        return $repo->messageRecipients($i, $this->userId(), array_merge($allowed, $admins));
    }
}
