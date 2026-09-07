<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Request,Response};
use App\Repositories\{AcademicRepository,AcademicStructureRepository,SettingsRepository,StudentRepository};
use App\Support\Flash;
use DomainException;

/**
 * Class sections and academic streams/groups administration.
 *
 * The schema (class_sections, academic_streams, students.section_id, students.stream_id)
 * existed with no way to configure or assign it. This is that surface.
 */
final class AcademicStructureController extends AdminPageController
{
    private function i(): int
    {
        return (int) (new SettingsRepository($this->app->database()))->institute()['id'];
    }

    private function repo(): AcademicStructureRepository
    {
        return new AcademicStructureRepository($this->app->database());
    }

    private function denied(): ?Response
    {
        return can('settings.view') ? null : Response::html('Forbidden', 403);
    }

    public function index(Request $request): Response
    {
        if ($x = $this->denied()) {
            return $x;
        }
        $institute = $this->i();
        $classId = (int) $request->query('class', 0);
        return $this->renderPage('academic-structure', [
            'classes' => (new AcademicRepository($this->app->database()))->classes($institute),
            'selectedClass' => $classId,
            'sections' => $this->repo()->sections($institute, $classId),
            'streams' => $this->repo()->streams($institute),
        ]);
    }

    /* ------------------------------------------------------------------ sections */

    public function storeSection(Request $request): Response
    {
        try {
            $this->repo()->createSection($this->i(), (int) $request->input('class_id'), (string) $request->input('name'));
            Flash::add('success', 'Section created.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/admin/settings/academic-structure'));
    }

    public function sectionStatus(Request $request): Response
    {
        try {
            $this->repo()->setSectionStatus($this->i(), (int) $request->route('id'), (string) $request->input('status'));
            Flash::add('success', 'Section status updated.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/admin/settings/academic-structure'));
    }

    public function deleteSection(Request $request): Response
    {
        $result = $this->repo()->deleteSection($this->i(), (int) $request->route('id'));
        Flash::add($result === 'deleted' ? 'success' : 'error', match ($result) {
            'deleted' => 'Section deleted.',
            'in_use' => 'Students are assigned to this section. Move them first, or mark it inactive.',
            default => 'Section not found.',
        });
        return Response::redirect(url('/admin/settings/academic-structure'));
    }

    /* ------------------------------------------------------------------- streams */

    public function storeStream(Request $request): Response
    {
        try {
            $this->repo()->createStream($this->i(), (string) $request->input('name'));
            Flash::add('success', 'Stream created.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/admin/settings/academic-structure'));
    }

    public function streamStatus(Request $request): Response
    {
        try {
            $this->repo()->setStreamStatus($this->i(), (int) $request->route('id'), (string) $request->input('status'));
            Flash::add('success', 'Stream status updated.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/admin/settings/academic-structure'));
    }

    public function deleteStream(Request $request): Response
    {
        $result = $this->repo()->deleteStream($this->i(), (int) $request->route('id'));
        Flash::add($result === 'deleted' ? 'success' : 'error', match ($result) {
            'deleted' => 'Stream deleted.',
            'in_use' => 'Students are assigned to this stream. Move them first, or mark it inactive.',
            default => 'Stream not found.',
        });
        return Response::redirect(url('/admin/settings/academic-structure'));
    }

    /* -------------------------------------------------- student placement screen */

    public function placement(Request $request): Response
    {
        if ($x = $this->denied()) {
            return $x;
        }
        $institute = $this->i();
        $classId = (int) $request->query('class', 0);
        return $this->renderPage('academic-placement', [
            'classes' => (new AcademicRepository($this->app->database()))->classes($institute),
            'selectedClass' => $classId,
            'students' => $classId > 0
                ? (new StudentRepository($this->app->database()))->paginate($institute, '', $classId, 300)
                : [],
            'sections' => $classId > 0 ? $this->repo()->sectionOptions($institute, $classId) : [],
            'streams' => $this->repo()->streamOptions($institute),
        ]);
    }

    public function savePlacement(Request $request): Response
    {
        $institute = $this->i();
        $classId = (int) $request->input('class');
        $sections = (array) $request->input('section', []);
        $streams = (array) $request->input('stream', []);
        $saved = 0;
        $errors = 0;
        foreach ($sections as $studentId => $sectionId) {
            try {
                $this->repo()->assignStudent(
                    $institute,
                    (int) $studentId,
                    ((int) $sectionId) ?: null,
                    ((int) ($streams[$studentId] ?? 0)) ?: null
                );
                $saved++;
            } catch (DomainException) {
                $errors++;
            }
        }
        Flash::add($errors > 0 ? 'error' : 'success', $errors > 0
            ? $saved . ' student(s) updated, ' . $errors . ' rejected as invalid.'
            : $saved . ' student(s) placed.');
        return Response::redirect(url('/admin/settings/academic-placement?class=' . $classId));
    }
}
