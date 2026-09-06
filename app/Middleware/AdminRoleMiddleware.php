<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Core\{App,Request,Response,View};
use Closure;
final class AdminRoleMiddleware
{
    public function __construct(private readonly App $app) {}
    public function handle(Request $request,Closure $next): Response {return hasRole('admin')?$next($request):Response::html(View::render('errors/403',['pageTitle'=>'Access Denied'],'layouts/error'),403);}
}
