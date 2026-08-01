<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ViewRenderer;
use App\Exceptions\MailException;
use SensitiveParameter;
use Throwable;

/** Renders paired plain-text and HTML transactional-email templates. */
final readonly class MailTemplateRenderer
{
    public function __construct(private ViewRenderer $views)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{text: string, html: string}
     */
    public function render(string $template, #[SensitiveParameter] array $data): array
    {
        if (preg_match('/^[a-z][a-z0-9-]*$/D', $template) !== 1) {
            throw MailException::template();
        }

        try {
            $text = trim($this->views->render('emails.text.' . $template, $data));
            $html = trim($this->views->render('emails.html.' . $template, $data));

            if ($text === '' || $html === '') {
                throw MailException::template();
            }

            return ['text' => $text, 'html' => $html];
        } catch (MailException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw MailException::template();
        }
    }
}
