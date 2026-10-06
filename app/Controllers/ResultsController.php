<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,Response};use App\Repositories\{AcademicRepository,OnlineTestRepository,PakistanErpRepository,SettingsRepository};use App\Support\Flash;

/**
 * Results Center: a dedicated, class-scoped place to publish and export online test results,
 * separate from the test builder. Gated by online-tests.results, which an admin can grant or
 * revoke independently of online-tests.manage (see config/permissions.php).
 */
final class ResultsController extends AdminPageController
{
    private function i():int{return(int)(new SettingsRepository($this->app->database()))->institute()['id'];}
    private function r():OnlineTestRepository{return new OnlineTestRepository($this->app->database());}
    private function employee():?int{$v=$this->app->database()->query('SELECT employee_id FROM employee_accounts WHERE user_id=:u',['u'=>(int)user()['id']])->fetchColumn();return$v?(int)$v:null;}
    private function owns(int $test):bool{return can('*')||(bool)$this->app->database()->query('SELECT 1 FROM online_tests WHERE id=:id AND created_by=:u',['id'=>$test,'u'=>(int)user()['id']])->fetchColumn();}

    /** Tests a teacher may manage results for: ones they created, or ones for a class they are assigned to. */
    private function scopedTests(int $i, array $tests): array
    {
        if (can('*')) return $tests;
        $employee = $this->employee();
        $allowedClasses = [];
        if ($employee) {
            foreach ((new PakistanErpRepository($this->app->database()))->assignments($i) as $a) {
                if ((int) $a['employee_id'] === $employee) $allowedClasses[(int) $a['class_id']] = true;
            }
        }
        return array_values(array_filter($tests, fn($t) =>
            (int) $t['created_by'] === (int) user()['id'] || isset($allowedClasses[(int) $t['class_id']])
        ));
    }

    public function index(Request $r): Response
    {
        $i = $this->i();
        $tests = $this->scopedTests($i, $this->r()->all($i));

        $classFilter = (int) $r->query('class', 0);
        $status = (string) $r->query('status', '');
        if ($classFilter) $tests = array_values(array_filter($tests, fn($t) => (int) $t['class_id'] === $classFilter));
        if ($status === 'needs_publishing') $tests = array_values(array_filter($tests, fn($t) => (int) $t['unpublished_count'] > 0));
        elseif ($status === 'needs_marking') $tests = array_values(array_filter($tests, fn($t) => (int) $t['pending_count'] > 0));
        elseif ($status === 'fully_published') $tests = array_values(array_filter($tests, fn($t) => (int) $t['submission_count'] > 0 && (int) $t['unpublished_count'] === 0 && (int) $t['pending_count'] === 0));

        return $this->view('results/index', [
            'pageTitle' => 'Results Center',
            'activeMenu' => 'online-tests',
            'activeSubmenu' => 'results',
            'tests' => $tests,
            'classes' => (new AcademicRepository($this->app->database()))->classes($i),
            'classFilter' => $classFilter,
            'statusFilter' => $status,
            'canManageAll' => can('*'),
        ]);
    }

    public function publish(Request $r): Response
    {
        $id = (int) $r->route('id');
        if (!$this->owns($id)) return Response::html('Forbidden', 403);
        try {
            $visible = (string) $r->input('visible_to', 'both');
            $count = $this->r()->publishResults($this->i(), $id, $visible, (int) user()['id']);
            $label = ['student' => 'students only', 'parent' => 'parents only', 'both' => 'students and parents'][$visible] ?? 'students and parents';
            Flash::add('success', $count > 0 ? "{$count} result(s) published to {$label}." : 'There are no marked results waiting to be published.');
        } catch (\DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/admin/results'));
    }
}
