<?php

declare(strict_types=1);

use App\Controllers\SmsGatewayApiController;

/**
 * Android SMS bridge API. Device bearer-token auth (not the admin session), no CSRF, JSON only.
 * See docs/android-sms-gateway.md.
 */
$prefix = '/api/sms-gateway/v1';
$router->post($prefix . '/register', [SmsGatewayApiController::class, 'register']);
$router->post($prefix . '/heartbeat', [SmsGatewayApiController::class, 'heartbeat']);
$router->post($prefix . '/device-info', [SmsGatewayApiController::class, 'deviceInfo']);
$router->get($prefix . '/config', [SmsGatewayApiController::class, 'getConfig']);
$router->get($prefix . '/messages', [SmsGatewayApiController::class, 'messages']);
$router->post($prefix . '/messages/bulk-status', [SmsGatewayApiController::class, 'bulkStatus']);
$router->post($prefix . '/messages/{id}/processing', [SmsGatewayApiController::class, 'processing']);
$router->post($prefix . '/messages/{id}/sent', [SmsGatewayApiController::class, 'sent']);
$router->post($prefix . '/messages/{id}/failed', [SmsGatewayApiController::class, 'failed']);
foreach (['GET', 'POST'] as $method) {
    $router->{strtolower($method)}($prefix . '/{any}', [SmsGatewayApiController::class, 'notFound']);
}
