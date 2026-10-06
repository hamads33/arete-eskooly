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
    'online-tests' => ['view' => 'online-tests.manage','manage' => 'online-tests.manage'],
    // A separate permission from online-tests.manage on purpose: an admin can grant a teacher
    // "manage results" (mark, publish, download) without also granting "create/delete tests",
    // or the reverse. Scoping to the teacher's own assigned classes happens the same way it does
    // for online-tests.manage (see ResultsController), driven by Teacher Assignments.
    'results'      => ['view' => 'online-tests.results','manage' => 'online-tests.results'],
    'behaviour'    => ['view' => 'behaviour.view',     'manage' => 'behaviour.manage'],
    'reports'      => ['view' => 'reports.view',       'manage' => 'reports.view'],
    'certificates' => ['view' => 'certificates.view',  'manage' => 'certificates.manage'],
    'communication'=> ['view' => 'communication.view', 'manage' => 'communication.manage'],
    'roles'        => ['view' => 'roles.view',         'manage' => 'roles.manage'],
    'guardians'    => ['view' => 'students.view',      'manage' => 'students.manage'],
    'teacher-assignments' => ['view' => 'classes.view','manage' => 'teacher-assignments.manage'],
    'notes'        => ['view' => 'notes.view',          'manage' => 'notes.manage'],
    // Upload is granted to no role by default: only the admin or a role the admin enables.
    'recorded-lectures' => ['view' => 'recorded-lectures.view', 'manage' => 'recorded-lectures.manage'],
    'whatsapp'     => ['view' => 'communication.view',  'manage' => 'whatsapp.manage'],
    'live-classes' => ['view' => 'live-classes.view',   'manage' => 'live-classes.manage'],
    // SMS Center: the gate admits sms.view; each action then checks its own sms.* permission
    // (sms.compose, sms.bulk_send, sms.templates.manage, sms.queue.manage, sms.settings.manage,
    // sms.gateway.manage, sms.logs.view) inside SmsCenterController.
    'sms'          => ['view' => 'sms.view',            'manage' => 'sms.view'],
    // The Google connection is administrator-only: 'google-calendar.manage' is granted to no role,
    // so only the admin wildcard satisfies it and no teacher can reach the OAuth handshake.
    'integrations' => ['view' => 'google-calendar.manage', 'manage' => 'google-calendar.manage'],
];
