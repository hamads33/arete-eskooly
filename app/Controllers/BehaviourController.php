<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Request,Response};
use App\Repositories\{AcademicRepository,BehaviourRepository,SettingsRepository,StudentRepository};
use App\Support\Flash;
use DomainException;

/**
 * Behaviour & Skills — rate the Affective and Psychomotor domains, record observations, and
 * report on both. Locked in eSkooly's web edition; available here.
 */
final class BehaviourController extends AdminPageController
{
    private function i(): int
    {
        return (int) (new SettingsRepository($this->app->database()))->institute()['id'];
    }

    private function repo(): BehaviourRepository
    {
        return new BehaviourRepository($this->app->database());
    }

    private function classes(): array
    {
        return (new AcademicRepository($this->app->database()))->classes($this->i());
    }

    /** Current month is the default rating period. */
    private function period(Request $request): string
    {
        $period = (string) $request->query('period', date('Y-m'));
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) ? $period : date('Y-m');
    }

    /* ------------------------------------------------------------------- traits */

    public function traits(Request $request): Response
    {
        return $this->renderPage('behaviour-traits', [
            'traits' => $this->repo()->traits($this->i()),
            'domains' => BehaviourRepository::DOMAINS,
        ]);
    }

    public function storeTrait(Request $request): Response
    {
        try {
            $this->repo()->createTrait(
                $this->i(),
                (string) $request->input('domain'),
                (string) $request->input('name'),
                (string) $request->input('description', ''),
                (int) $request->input('sort_order', 0)
            );
            Flash::add('success', 'Trait added.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/admin/behaviour/traits'));
    }

    public function traitStatus(Request $request): Response
    {
        try {
            $this->repo()->setTraitStatus($this->i(), (int) $request->route('id'), (string) $request->input('status'));
            Flash::add('success', 'Trait status updated.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/admin/behaviour/traits'));
    }

    public function deleteTrait(Request $request): Response
    {
        $result = $this->repo()->deleteTrait($this->i(), (int) $request->route('id'));
        Flash::add(
            $result === 'deleted' ? 'success' : 'error',
            match ($result) {
                'deleted' => 'Trait deleted.',
                'in_use' => 'This trait has ratings recorded and cannot be deleted. Mark it inactive instead.',
                default => 'Trait not found.',
            }
        );
        return Response::redirect(url('/admin/behaviour/traits'));
    }

    /* ------------------------------------------------------------------ ratings */

    public function rateBehaviours(Request $request): Response
    {
        return $this->rate($request, 'affective', 'behaviour-rate');
    }

    public function rateSkills(Request $request): Response
    {
        return $this->rate($request, 'psychomotor', 'behaviour-skills');
    }

    private function rate(Request $request, string $domain, string $page): Response
    {
        $institute = $this->i();
        $classId = (int) $request->query('class', 0);
        $period = $this->period($request);
        $traits = $this->repo()->traits($institute, $domain, true);
        $sheet = $classId > 0 ? $this->repo()->sheet($institute, $classId, $domain, $period) : [];

        return $this->renderPage($page, [
            'domain' => $domain,
            'classes' => $this->classes(),
            'selectedClass' => $classId,
            'period' => $period,
            'traits' => $traits,
            'sheet' => $sheet,
            'scale' => BehaviourRepository::SCALE,
        ]);
    }

    public function saveRatings(Request $request): Response
    {
        $domain = (string) $request->input('domain');
        $classId = (int) $request->input('class');
        $period = (string) $request->input('period');
        $target = $domain === 'psychomotor' ? '/admin/behaviour/skills' : '/admin/behaviour/rate';
        try {
            $saved = $this->repo()->saveRatings($this->i(), $classId, $domain, $period, (array) $request->input('ratings', []), (int) user()['id']);
            Flash::add('success', $saved . ' rating(s) saved.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url($target . '?class=' . $classId . '&period=' . urlencode($period)));
    }

    /* ------------------------------------------------------------- observations */

    public function observations(Request $request): Response
    {
        $institute = $this->i();
        $classId = (int) $request->query('class', 0);
        return $this->renderPage('behaviour-observations', [
            'classes' => $this->classes(),
            'selectedClass' => $classId,
            'students' => (new StudentRepository($this->app->database()))->paginate($institute, '', $classId ?: null, 200),
            'observations' => $this->repo()->observations($institute, $classId),
        ]);
    }

    public function storeObservation(Request $request): Response
    {
        try {
            $this->repo()->addObservation(
                $this->i(),
                (int) $request->input('student_id'),
                (string) $request->input('observation_date'),
                (string) $request->input('category'),
                (string) $request->input('note'),
                (int) user()['id']
            );
            Flash::add('success', 'Observation recorded.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/admin/behaviour/observations'));
    }

    public function deleteObservation(Request $request): Response
    {
        $deleted = $this->repo()->deleteObservation($this->i(), (int) $request->route('id'));
        Flash::add($deleted ? 'success' : 'error', $deleted ? 'Observation deleted.' : 'Observation not found.');
        return Response::redirect(url('/admin/behaviour/observations'));
    }

    /* ------------------------------------------------------------------ reports */

    public function affectiveReport(Request $request): Response
    {
        return $this->domainReport($request, 'affective', 'behaviour-report-affective');
    }

    public function psychomotorReport(Request $request): Response
    {
        return $this->domainReport($request, 'psychomotor', 'behaviour-report-psychomotor');
    }

    private function domainReport(Request $request, string $domain, string $page): Response
    {
        $institute = $this->i();
        $classId = (int) $request->query('class', 0);
        $period = $this->period($request);
        return $this->renderPage($page, [
            'domain' => $domain,
            'classes' => $this->classes(),
            'selectedClass' => $classId,
            'period' => $period,
            'rows' => $this->repo()->report($institute, $domain, $period, $classId),
            'scale' => BehaviourRepository::SCALE,
        ]);
    }
}
