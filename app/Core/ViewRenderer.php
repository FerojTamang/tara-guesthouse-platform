<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ViewException;
use App\Helpers\Escape;
use Closure;
use ErrorException;
use ReflectionReference;
use Throwable;

/**
 * Renders trusted project PHP templates from one canonical views directory.
 */
final class ViewRenderer
{
    private const MAX_RENDER_DEPTH = 8;

    /** @var list<string> */
    private const RESERVED_DATA_KEYS = [
        'content',
        'data',
        'e',
        'layout',
        'partial',
        'renderer',
        'templatePath',
        'viewsPath',
        'GLOBALS',
        'this',
        '_SERVER',
        '_GET',
        '_POST',
        '_FILES',
        '_COOKIE',
        '_SESSION',
        '_REQUEST',
        '_ENV',
    ];

    private readonly string $viewsPath;

    private int $renderDepth = 0;

    public function __construct(string $viewsPath)
    {
        $inputPath = rtrim($viewsPath, '/\\');

        if (
            $inputPath === ''
            || is_link($inputPath)
            || !is_dir($inputPath)
            || !is_readable($inputPath)
        ) {
            throw new ViewException('The view renderer could not be initialized.');
        }

        $resolvedPath = realpath($inputPath);

        if ($resolvedPath === false || !is_dir($resolvedPath) || !is_readable($resolvedPath)) {
            throw new ViewException('The view renderer could not be initialized.');
        }

        $this->viewsPath = rtrim($resolvedPath, '/\\');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = [], ?string $layout = null): string
    {
        $this->validateTemplateName($view);
        $this->validateData($data);

        $content = $this->execute($view, $data);

        if ($layout === null) {
            return $content;
        }

        $this->validateTemplateName($layout, 'layouts.');

        return $this->execute($layout, [...$data, 'content' => $content]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function partial(string $view, array $data = []): string
    {
        $this->validateTemplateName($view, 'components.');
        $this->validateData($data);

        return $this->execute($view, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function execute(string $view, array $data): string
    {
        if ($this->renderDepth >= self::MAX_RENDER_DEPTH) {
            throw new ViewException('The view could not be rendered.');
        }

        $templatePath = $this->resolveTemplate($view);
        $bufferLevel = ob_get_level();
        $errorHandlerInstalled = false;
        $this->renderDepth++;

        try {
            if (!ob_start()) {
                throw new ViewException('The view could not be rendered.');
            }

            set_error_handler(
                static function (int $severity, string $message, string $file, int $line): never {
                    throw new ErrorException($message, 0, $severity, $file, $line);
                },
            );
            $errorHandlerInstalled = true;

            $renderer = $this;
            $e = static fn (mixed $value): string => Escape::html($value);
            $partial = static fn (string $name, array $partialData = []): string => $renderer->partial($name, $partialData);

            $renderTemplate = static function (
                string $templatePath,
                array $data,
                Closure $e,
                Closure $partial,
            ): void {
                extract($data, EXTR_SKIP);
                require $templatePath;
            };

            $renderTemplate($templatePath, $data, $e, $partial);
            restore_error_handler();
            $errorHandlerInstalled = false;

            while (ob_get_level() > $bufferLevel + 1) {
                $nestedOutput = ob_get_clean();

                if ($nestedOutput === false) {
                    throw new ViewException('The view could not be rendered.');
                }

                echo $nestedOutput;
            }

            $output = ob_get_clean();

            if ($output === false || ob_get_level() !== $bufferLevel) {
                throw new ViewException('The view could not be rendered.');
            }

            return $output;
        } catch (Throwable) {
            if ($errorHandlerInstalled) {
                restore_error_handler();
            }

            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            throw new ViewException('The view could not be rendered.');
        } finally {
            $this->renderDepth--;
        }
    }

    private function validateTemplateName(string $view, ?string $requiredPrefix = null): void
    {
        if (
            preg_match('/^[a-z][a-z0-9_-]*(?:\.[a-z][a-z0-9_-]*)*$/D', $view) !== 1
            || ($requiredPrefix !== null && !str_starts_with($view, $requiredPrefix))
        ) {
            throw new ViewException('The view name is invalid.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateData(array $data): void
    {
        foreach (array_keys($data) as $key) {
            if (
                !is_string($key)
                || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $key) !== 1
                || in_array($key, self::RESERVED_DATA_KEYS, true)
                || ReflectionReference::fromArrayElement($data, $key) !== null
            ) {
                throw new ViewException('The view data is invalid.');
            }

            $this->validatePresentationValue($data[$key]);
        }
    }

    private function validatePresentationValue(mixed $value): void
    {
        if (is_callable($value) || is_resource($value) || is_object($value)) {
            throw new ViewException('The view data is invalid.');
        }

        if (is_array($value)) {
            foreach (array_keys($value) as $key) {
                if (ReflectionReference::fromArrayElement($value, $key) !== null) {
                    throw new ViewException('The view data is invalid.');
                }

                $this->validatePresentationValue($value[$key]);
            }

            return;
        }

        if ($value !== null && !is_scalar($value)) {
            throw new ViewException('The view data is invalid.');
        }
    }

    private function resolveTemplate(string $view): string
    {
        $segments = explode('.', $view);
        $segments[array_key_last($segments)] .= '.php';
        $candidate = $this->viewsPath . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
        $currentPath = $this->viewsPath;

        foreach ($segments as $segment) {
            $currentPath .= DIRECTORY_SEPARATOR . $segment;

            if (is_link($currentPath)) {
                throw new ViewException('The requested view is unavailable.');
            }
        }

        $resolvedPath = realpath($candidate);

        if (
            $resolvedPath === false
            || !is_file($resolvedPath)
            || !is_readable($resolvedPath)
            || pathinfo($resolvedPath, PATHINFO_EXTENSION) !== 'php'
            || !$this->containsPath($resolvedPath)
        ) {
            throw new ViewException('The requested view is unavailable.');
        }

        return $resolvedPath;
    }

    private function containsPath(string $path): bool
    {
        $basePath = $this->viewsPath . DIRECTORY_SEPARATOR;

        if (PHP_OS_FAMILY === 'Windows') {
            return str_starts_with(strtolower($path), strtolower($basePath));
        }

        return str_starts_with($path, $basePath);
    }
}
