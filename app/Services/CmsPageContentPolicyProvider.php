<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Environment;
use App\Exceptions\CmsException;
use Throwable;

/** Resolves the required, explicitly approved CMS page-content policy. */
final class CmsPageContentPolicyProvider
{
    private ?CmsPageContentSanitizer $sanitizer = null;

    public function __construct(
        private readonly string $basePath,
        private readonly ?Environment $environment = null,
    ) {
    }

    public function sanitizer(): CmsPageContentSanitizer
    {
        if ($this->sanitizer instanceof CmsPageContentSanitizer) {
            return $this->sanitizer;
        }

        try {
            $descriptor = $this->descriptor();
            $environment = $this->environment ?? Environment::load($this->basePath);
            $json = $environment->literalString($descriptor['policy_environment_key'], '');

            if ($json === '' || $json !== trim($json)) {
                throw CmsException::failure();
            }

            $decoded = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
            $keys = is_array($decoded) ? array_keys($decoded) : [];
            sort($keys, SORT_STRING);

            if (
                !is_array($decoded)
                || $keys !== ['format', 'maximum_length']
                || !is_string($decoded['format'])
                || !is_int($decoded['maximum_length'])
                || !in_array($decoded['format'], $descriptor['supported_formats'], true)
            ) {
                throw CmsException::failure();
            }

            return $this->sanitizer = match ($decoded['format']) {
                'plain_text' => new PlainTextCmsPageContentSanitizer($decoded['maximum_length']),
                default => throw CmsException::failure(),
            };
        } catch (CmsException $exception) {
            throw $exception->isSystemFailure() ? $exception : CmsException::failure($exception);
        } catch (Throwable $failure) {
            throw CmsException::failure($failure);
        }
    }

    /** @return array{policy_environment_key: string, supported_formats: list<string>} */
    private function descriptor(): array
    {
        $path = $this->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cms.php';

        if (!is_file($path)) {
            throw CmsException::failure();
        }

        $configuration = require $path;
        $content = is_array($configuration) ? ($configuration['page_content'] ?? null) : null;

        if (
            !is_array($content)
            || array_keys($content) !== ['policy_environment_key', 'supported_formats']
            || $content['policy_environment_key'] !== 'CMS_PAGE_CONTENT_POLICY_JSON'
            || $content['supported_formats'] !== ['plain_text']
        ) {
            throw CmsException::failure();
        }

        return $content;
    }
}
