<?php

namespace Slowlyo\SlowLoginGuard\Http\Middleware;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Slowlyo\SlowLoginGuard\SlowLoginGuardServiceProvider;

class LoginAfterMiddleware
{
    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($request->is(config('admin.route.prefix') . '/login') && $request->has(['username', 'password'])) {
            if ($response instanceof \Illuminate\Http\JsonResponse && $response->getData()->msg == __('admin.login_failed')) {
                $this->record($request->input('username'), $response->getData()->status == 0);
            }
        }

        return $response;
    }

    /**
     * @param $username
     * @param bool $forget
     *
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function record($username, bool $forget = false)
    {
        if ($forget) {
            app('cache')->forget($this->getCacheKey($username));
            return;
        }

        $record = app('cache')->get($this->getCacheKey($username));

        $value = [
            'tryCount'    => 1,
            'lastTryTime' => time(),
        ];

        if ($record) {
            $value['tryCount'] = Arr::get($record, 'tryCount', 0) + 1;
        }

        app('cache')->put($this->getCacheKey($username), $value);
    }

    private function getCacheKey($username)
    {
        return SlowLoginGuardServiceProvider::loginRestrictionCacheKey($username);
    }
}
