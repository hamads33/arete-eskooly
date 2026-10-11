<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

final class InstallController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('install/index', [
            'pageTitle' => 'Install ' . brand()['short'],
            'pageDescription' => 'Install ' . brand()['name'] . ' for fast, secure access from your home screen.',
            'hideInstallBanner' => true,
        ], 'layouts/install');
    }
}
