<?php declare(strict_types=1); namespace App\Services; interface NativeProcessRunner { public function run(NativeProcessRequest $request):NativeProcessResult; }
