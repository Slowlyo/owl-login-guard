<?php

namespace Slowlyo\SlowLoginGuard\Http\Middleware;

use Arr;
use Closure;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Slowlyo\SlowAdmin\Admin;
use Slowlyo\SlowAdmin\Traits\ErrorTrait;
use Slowlyo\SlowLoginGuard\SlowLoginGuardServiceProvider;

class LoginBeforeMiddleware
{
    use ErrorTrait;

    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->is(config('admin.route.prefix') . '/login')) {
            $this->check($request->input('username'));

            if ($this->hasError()) {
                return Admin::response()->fail($this->getError());
            }
        }

        return $next($request);
    }

    /**
     * @param $username
     *
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function check($username)
    {
        $record = app('cache')->get($this->getCacheKey($username));

        if ($record) {
            $maxTryCount = $this->config('max_try_count', 10);
            $lockTime    = $this->config('lock_time', 5);
            $tryCount    = Arr::get($record, 'tryCount', 0);

            if ($tryCount >= $maxTryCount) {
                $lastTryTime = Arr::get($record, 'lastTryTime', 0);
                $releaseTime = Carbon::createFromTimestamp($lastTryTime)->addMinutes($lockTime);

                if ($releaseTime->gt(now())) {
                    $this->setError($this->trans('login.error_message', ['time' => $releaseTime->diffForHumans()]));
                }
            }
        }
    }

    private function getCacheKey($username)
    {
        return SlowLoginGuardServiceProvider::loginRestrictionCacheKey($username);
    }

    private function config($key, $default = null)
    {
        return SlowLoginGuardServiceProvider::setting($key, $default);
    }

    private function trans($key, $replace = [])
    {
        return SlowLoginGuardServiceProvider::trans($key, $replace);
    }
}
