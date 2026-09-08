<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Controllers\Concerns\ResolvesLiveClasses;
use App\Core\{Request, Response};
use App\Repositories\PakistanErpRepository;
use App\Support\Flash;
use DomainException;

/**
 * The institute's Google Calendar connection. Administrators only.
 *
 * The routes sit under /admin/integrations, which config/permissions.php maps to
 * google-calendar.manage — a permission granted to no role except admin's '*'. Teachers therefore
 * cannot reach these actions at all, and no action here ever renders a token, a client secret, or
 * a Google exception trace (§4, §11).
 */
final class GoogleIntegrationController extends AdminPageController
{
    use ResolvesLiveClasses;

    /** Sends the admin to Google's consent screen with a session-bound state parameter. */
    public function connect(Request $r): Response
    {
        if (!$this->googleFactory()->configured()) {
            Flash::add('error', 'Google integration is not configured on this server. Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and GOOGLE_REDIRECT_URI, then try again.');
            return Response::redirect(url('/admin/live-classes'));
        }
        return Response::redirect($this->googleOAuth()->authorizationUrl($this->instituteId()));
    }

    /**
     * Google's redirect back. The state is validated against this admin's session before the code
     * is exchanged, so a callback replayed or forged from elsewhere is refused.
     */
    public function callback(Request $r): Response
    {
        $institute = $this->instituteId();
        if ((string) $r->query('error', '') !== '') {
            // The admin declined consent, or Google refused. Neither is an application error.
            Flash::add('error', 'Google authorization was not completed.');
            return Response::redirect(url('/admin/live-classes'));
        }
        try {
            $result = $this->googleOAuth()->completeCallback((string) $r->query('code', ''), (string) $r->query('state', ''));
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
            return Response::redirect(url('/admin/live-classes'));
        } catch (\Throwable $e) {
            $this->app->logger()->error('Google OAuth callback failed', ['reason' => $e->getMessage()]);
            Flash::add('error', 'Google Calendar could not be connected. Please try again.');
            return Response::redirect(url('/admin/live-classes'));
        }
        // Audit metadata records the account, never a token (§35).
        (new PakistanErpRepository($this->app->database()))->audit($institute, (int) user()['id'], 'google_calendar.connected', 'google_connection', null, ['account_email' => $result['account_email']]);
        Flash::add('success', 'Google Calendar connected' . ($result['account_email'] ? ' as ' . $result['account_email'] : '') . '.');
        return Response::redirect(url('/admin/live-classes'));
    }

    /**
     * Clears the stored tokens. Existing meetings keep their Meet links and their history — the
     * institute simply cannot create or change Google events until it reconnects.
     */
    public function disconnect(Request $r): Response
    {
        $institute = $this->instituteId();
        $this->googleOAuth()->disconnect($institute);
        (new PakistanErpRepository($this->app->database()))->audit($institute, (int) user()['id'], 'google_calendar.disconnected', 'google_connection', null);
        Flash::add('success', 'Google Calendar disconnected. Scheduled classes keep their existing links.');
        return Response::redirect(url('/admin/live-classes'));
    }
}
