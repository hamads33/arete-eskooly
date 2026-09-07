<?php

declare(strict_types=1);

/**
 * Permission requirements for the /admin area, resolved by AdminAreaMiddleware.
 *
 * Keys are the first path segment after /admin. Each module declares the permission needed to
 * read it ('view') and to change it ('manage'); the middleware picks by HTTP method. A module
 * absent from this map is admin-only, so a new route is closed by default rather than open.
 *
 * Holding '*' (the admin role) satisfies every check.
 */
return [
    'dashboard'    => ['view' => 'dashboard.view',     'manage' => 'dashboard.view'],
    'settings'     => ['view' => 'settings.view',      'manage' => 'settings.manage'],
    'students'     => ['view' => 'students.view',      'manage' => 'students.manage'],
    'employees'    => ['view' => 'employees.view',     'manage' => 'employees.manage'],
    'classes'      => ['view' => 'classes.view',       'manage' => 'classes.manage'],
    'subjects'     => ['view' => 'subjects.view',      'manage' => 'subjects.manage'],
    'accounts'     => ['view' => 'accounts.view',      'manage' => 'accounts.manage'],
    'fees'         => ['view' => 'fees.view',          'manage' => 'fees.manage'],
    'salary'       => ['view' => 'salary.view',        'manage' => 'salary.manage'],
    'attendance'   => ['view' => 'attendance.view',    'manage' => 'attendance.manage'],
    'homework'     => ['view' => 'homework.view',      'manage' => 'homework.manage'],
    'exams'        => ['view' => 'exams.view',         'manage' => 'exams.manage'],
    'class-tests'  => ['view' => 'class-tests.view',   'manage' => 'class-tests.manage'],
    'behaviour'    => ['view' => 'behaviour.view',     'manage' => 'behaviour.manage'],
    'reports'      => ['view' => 'reports.view',       'manage' => 'reports.view'],
    'certificates' => ['view' => 'certificates.view',  'manage' => 'certificates.manage'],
    'communication'=> ['view' => 'communication.view', 'manage' => 'communication.manage'],
    'roles'        => ['view' => 'roles.view',         'manage' => 'roles.manage'],
    'guardians'    => ['view' => 'students.view',      'manage' => 'students.manage'],
    'teacher-assignments' => ['view' => 'classes.view','manage' => 'teacher-assignments.manage'],
    'notes'        => ['view' => 'notes.view',          'manage' => 'notes.manage'],
    'whatsapp'     => ['view' => 'communication.view',  'manage' => 'whatsapp.manage'],
];
