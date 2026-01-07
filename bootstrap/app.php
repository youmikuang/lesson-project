<?php

use App\Exceptions\BusinessException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 处理业务异常
        $exceptions->render(function (BusinessException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json($e->toArray(), $e->getHttpStatusCode());
            }
        });
        // 处理业务异常
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'ret' => false,
                    'msg' => "数据未找到，参数错误",
                    'data' => [],
                ], 404);
            }
        });

        // 处理模型未找到异常
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $modelName = class_basename($e->getModel());
                $modelNameMap = [
                    'Course' => '课程',
                    'Reservation' => '预约记录',
                    'User' => '用户',
                ];
                $name = $modelNameMap[$modelName] ?? $modelName;

                return response()->json([
                    'ret' => false,
                    'msg' => "{$name}不存在",
                    'data' => [],
                ], 404);
            }
        });

        // 处理验证异常
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'ret' => false,
                    'msg' => '请求参数验证失败',
                    'data' => [],
                ], 422);
            }
        });

        // 处理认证异常
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'ret' => false,
                    'msg' => '未认证，请先登录',
                    'data' => [],
                ], 401);
            }
        });
    })->create();
