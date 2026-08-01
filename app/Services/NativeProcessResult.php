<?php declare(strict_types=1); namespace App\Services; final readonly class NativeProcessResult { public function __construct(public int $exitCode,public string $stderr){} }
