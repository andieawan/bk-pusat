<?php

namespace App\Http\Middleware;

use App\Services\BkService;
use Closure;
use Illuminate\Http\Request;

/** Batas akses area: "buka" (boleh membuka modul BK), "konselor" (Guru BK/Admin), "admin". */
class HanyaBk
{
    public function handle(Request $request, Closure $next, string $level = 'buka')
    {
        $u = $request->user();
        $ok = match ($level) {
            'konselor' => $u && BkService::isCounselor($u),
            'admin' => $u && $u->isAdmin(),
            default => $u && BkService::canOpen($u),
        };
        abort_unless($ok, 403, 'Anda tidak memiliki akses ke bagian ini.');

        return $next($request);
    }
}
