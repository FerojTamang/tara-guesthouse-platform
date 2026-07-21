<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\ViewRenderer;
use App\Exceptions\ViewException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

final class ViewRendererTest extends TestCase
{
    private string $fixtureRoot;

    private string $viewsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-views-' . bin2hex(random_bytes(8));
        $this->viewsPath = $this->fixtureRoot . DIRECTORY_SEPARATOR . 'views';

        self::assertTrue(mkdir($this->viewsPath . DIRECTORY_SEPARATOR . 'nested', 0700, true));
        self::assertTrue(mkdir($this->viewsPath . DIRECTORY_SEPARATOR . 'layouts', 0700));
        self::assertTrue(mkdir($this->viewsPath . DIRECTORY_SEPARATOR . 'components', 0700));

        $this->writeView('plain.php', 'Hello <?= $e($name) ?>');
        $this->writeView('nested/page.php', 'Nested <?= $e($details[\'label\']) ?>');
        $this->writeView('scope.php', '<?= isset($message) ? $e($message) : \'none\' ?>');
        $this->writeView(
            'with-component.php',
            '<?= $partial(\'components.badge\', [\'label\' => $label]) ?>',
        );
        $this->writeView(
            'component-isolation.php',
            '<?= $partial(\'components.isolated\') ?><?= $e($secret) ?>',
        );
        $this->writeView('components/badge.php', '<span><?= $e($label) ?></span>');
        $this->writeView('components/isolated.php', '<?= isset($secret) ? \'leaked\' : \'isolated\' ?>');
        $this->writeView(
            'components/recurse.php',
            '<?= $remaining === 0 ? \'done\' : $partial(\'components.recurse\', [\'remaining\' => $remaining - 1]) ?>',
        );
        $this->writeView(
            'layouts/wrapper.php',
            '<title><?= $e($title) ?></title><main><?= $content ?></main><?= $partial(\'components.badge\', [\'label\' => $title]) ?>',
        );
        $this->writeView(
            'layouts/recursive.php',
            '<?= $content ?><?= $partial(\'components.recurse\', [\'remaining\' => $remaining]) ?>',
        );
        $this->writeView('open-buffer.php', 'before<?php ob_start(); ?>inside');
        $this->writeView('warning.php', 'partial<?php trigger_error(\'unsafe template warning\', E_USER_WARNING); ?>');
        $this->writeView('exception.php', '<?php throw new RuntimeException(\'secret path: \' . __FILE__); ?>');
        $this->writeView('error.php', '<?php undefined_view_function(); ?>');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixtureRoot);

        parent::tearDown();
    }

    public function testItRendersAValidViewWithEscapedData(): void
    {
        $renderer = new ViewRenderer($this->viewsPath);

        self::assertSame('Hello &lt;Guest&gt;', $renderer->render('plain', ['name' => '<Guest>']));
    }

    public function testItRendersNestedDotNotationAndNestedPresentationArrays(): void
    {
        $renderer = new ViewRenderer($this->viewsPath);

        self::assertSame(
            'Nested Garden &amp; River',
            $renderer->render('nested.page', ['details' => ['label' => 'Garden & River']]),
        );
    }

    public function testItRendersAViewWithALayoutAndRendererOwnedContent(): void
    {
        $renderer = new ViewRenderer($this->viewsPath);
        $result = $renderer->render('plain', [
            'name' => '<Guest>',
            'title' => 'Safe & Sound',
        ], 'layouts.wrapper');

        self::assertSame(
            '<title>Safe &amp; Sound</title><main>Hello &lt;Guest&gt;</main><span>Safe &amp; Sound</span>',
            $result,
        );
    }

    public function testItRendersComponentsWithExplicitData(): void
    {
        $renderer = new ViewRenderer($this->viewsPath);

        self::assertSame('<span>&lt;New&gt;</span>', $renderer->partial('components.badge', ['label' => '<New>']));
        self::assertSame('<span>&lt;New&gt;</span>', $renderer->render('with-component', ['label' => '<New>']));
    }

    public function testComponentsDoNotInheritParentVariables(): void
    {
        $renderer = new ViewRenderer($this->viewsPath);

        self::assertSame('isolatedparent', $renderer->render('component-isolation', ['secret' => 'parent']));
    }

    public function testRenderScopesDoNotLeakAndCallerDataIsNotMutated(): void
    {
        $renderer = new ViewRenderer($this->viewsPath);
        $data = ['message' => 'first'];
        $original = $data;

        self::assertSame('first', $renderer->render('scope', $data));
        self::assertSame('none', $renderer->render('scope'));
        self::assertSame($original, $data);
    }

    #[DataProvider('invalidData')]
    public function testItRejectsInvalidData(array $data): void
    {
        $renderer = new ViewRenderer($this->viewsPath);

        $this->expectException(ViewException::class);
        $renderer->render('plain', $data);
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function invalidData(): iterable
    {
        yield 'numeric key' => [[0 => 'value']];
        yield 'invalid identifier' => [['invalid-key' => 'value']];
        yield 'reserved content' => [['content' => 'value']];
        yield 'reserved helper' => [['e' => 'value']];
        yield 'callable string' => [['name' => 'strlen']];
        yield 'callable array' => [['name' => [self::class, 'invalidData']]];
        yield 'closure' => [['name' => static fn (): string => 'value']];
        yield 'object' => [['name' => new stdClass()]];
        yield 'stringable object' => [['name' => new class implements \Stringable {
            public function __toString(): string
            {
                return 'unsafe';
            }
        }]];
        yield 'nested object' => [['name' => ['nested' => new stdClass()]]];
    }

    public function testItRejectsCyclicPresentationArraysWithoutRecursingIndefinitely(): void
    {
        $cyclic = [];
        $cyclic['self'] = &$cyclic;

        $this->expectException(ViewException::class);
        (new ViewRenderer($this->viewsPath))->render('plain', ['name' => $cyclic]);
    }

    public function testItRejectsReferenceBackedDataWithoutMutatingTheCaller(): void
    {
        $name = 'original';
        $data = ['name' => &$name];

        try {
            (new ViewRenderer($this->viewsPath))->render('plain', $data);
            self::fail('Reference-backed view data should be rejected.');
        } catch (ViewException) {
            self::assertSame('original', $name);
            self::assertSame('original', $data['name']);
        }
    }

    public function testItRejectsResourceData(): void
    {
        $resource = fopen('php://memory', 'rb');
        self::assertIsResource($resource);

        try {
            $this->expectException(ViewException::class);
            (new ViewRenderer($this->viewsPath))->render('plain', ['name' => $resource]);
        } finally {
            fclose($resource);
        }
    }

    #[DataProvider('invalidViewNames')]
    public function testItRejectsInvalidTemplateNames(string $view): void
    {
        $renderer = new ViewRenderer($this->viewsPath);

        $this->expectException(ViewException::class);
        $renderer->render($view);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidViewNames(): iterable
    {
        yield 'empty name' => [''];
        yield 'leading dot' => ['.plain'];
        yield 'trailing dot' => ['plain.'];
        yield 'parent traversal' => ['../plain'];
        yield 'consecutive dots' => ['nested..page'];
        yield 'digit-leading segment' => ['nested.1page'];
        yield 'php suffix' => ['plain.php'];
        yield 'duplicate extension' => ['plain.php.php'];
        yield 'forward slash' => ['nested/page'];
        yield 'backslash' => ['nested\\page'];
        yield 'absolute unix path' => ['/tmp/plain'];
        yield 'windows drive path' => ['C:\\views\\plain'];
        yield 'unc path' => ['\\\\server\\share\\plain'];
        yield 'stream wrapper' => ['php://filter'];
        yield 'url' => ['https://example.test/view'];
        yield 'colon' => ['nested:page'];
        yield 'null byte' => ["plain\0ignored"];
        yield 'control character' => ["plain\n"];
        yield 'uppercase' => ['Plain'];
        yield 'leading whitespace' => [' plain'];
        yield 'trailing whitespace' => ['plain '];
    }

    public function testItRejectsMissingViewsLayoutsAndComponents(): void
    {
        $renderer = new ViewRenderer($this->viewsPath);

        foreach (
            [
                static fn (): string => $renderer->render('missing'),
                static fn (): string => $renderer->render('plain', ['name' => 'Guest'], 'layouts.missing'),
                static fn (): string => $renderer->partial('components.missing'),
            ] as $operation
        ) {
            try {
                $operation();
                self::fail('A missing template should fail.');
            } catch (ViewException $exception) {
                self::assertStringNotContainsString($this->viewsPath, $exception->getMessage());
            }
        }
    }

    public function testItRequiresLayoutAndComponentPrefixes(): void
    {
        $renderer = new ViewRenderer($this->viewsPath);

        try {
            $renderer->render('plain', ['name' => 'Guest'], 'plain');
            self::fail('A non-layout template should be rejected as a layout.');
        } catch (ViewException) {
            self::assertTrue(true);
        }

        $this->expectException(ViewException::class);
        $renderer->partial('plain', ['name' => 'Guest']);
    }

    public function testConstructorRejectsMissingBaseDirectory(): void
    {
        $this->expectException(ViewException::class);
        new ViewRenderer($this->fixtureRoot . DIRECTORY_SEPARATOR . 'missing');
    }

    public function testConstructorRejectsAFileAsTheBasePath(): void
    {
        $file = $this->fixtureRoot . DIRECTORY_SEPARATOR . 'not-a-directory';
        self::assertNotFalse(file_put_contents($file, 'not a view root'));

        $this->expectException(ViewException::class);
        new ViewRenderer($file);
    }

    public function testConstructorRejectsSymlinkedBaseWhenSupported(): void
    {
        $link = $this->fixtureRoot . DIRECTORY_SEPARATOR . 'linked-views';

        if (!@symlink($this->viewsPath, $link)) {
            self::markTestSkipped('The operating system cannot create the required directory symlink.');
        }

        $this->expectException(ViewException::class);
        new ViewRenderer($link);
    }

    public function testItRejectsSymlinkedFilesWhenSupported(): void
    {
        $target = $this->fixtureRoot . DIRECTORY_SEPARATOR . 'external.php';
        self::assertNotFalse(file_put_contents($target, 'external'));
        $link = $this->viewsPath . DIRECTORY_SEPARATOR . 'linked.php';

        if (!@symlink($target, $link)) {
            self::markTestSkipped('The operating system cannot create the required file symlink.');
        }

        $this->expectException(ViewException::class);
        (new ViewRenderer($this->viewsPath))->render('linked');
    }

    public function testItRejectsBrokenSymlinksWhenSupported(): void
    {
        $missingTarget = $this->fixtureRoot . DIRECTORY_SEPARATOR . 'missing-target.php';
        $link = $this->viewsPath . DIRECTORY_SEPARATOR . 'broken.php';

        if (!@symlink($missingTarget, $link)) {
            self::markTestSkipped('The operating system cannot create the required broken symlink.');
        }

        $this->expectException(ViewException::class);
        (new ViewRenderer($this->viewsPath))->render('broken');
    }

    public function testItRejectsSymlinkedDirectoriesEvenWhenTheyPointInsideWhenSupported(): void
    {
        $target = $this->viewsPath . DIRECTORY_SEPARATOR . 'nested';
        $link = $this->viewsPath . DIRECTORY_SEPARATOR . 'linked-nested';

        if (!@symlink($target, $link)) {
            self::markTestSkipped('The operating system cannot create the required directory symlink.');
        }

        $this->expectException(ViewException::class);
        (new ViewRenderer($this->viewsPath))->render('linked-nested.page');
    }

    public function testItRejectsSymlinkEscapesAndCanonicalPathsOutsideViewsWhenSupported(): void
    {
        $outside = $this->fixtureRoot . DIRECTORY_SEPARATOR . 'outside';
        self::assertTrue(mkdir($outside, 0700));
        self::assertNotFalse(file_put_contents($outside . DIRECTORY_SEPARATOR . 'page.php', 'outside'));
        $link = $this->viewsPath . DIRECTORY_SEPARATOR . 'outside-link';

        if (!@symlink($outside, $link)) {
            self::markTestSkipped('The operating system cannot create the required directory symlink.');
        }

        $this->expectException(ViewException::class);
        (new ViewRenderer($this->viewsPath))->render('outside-link.page');
    }

    public function testItAllowsExactlyEightActiveRenderLevels(): void
    {
        $renderer = new ViewRenderer($this->viewsPath);

        self::assertSame('done', $renderer->partial('components.recurse', ['remaining' => 7]));
    }

    public function testItRejectsRenderingBeyondEightLevelsAndRestoresRecursionState(): void
    {
        $renderer = new ViewRenderer($this->viewsPath);

        try {
            $renderer->partial('components.recurse', ['remaining' => 8]);
            self::fail('A ninth active render should be rejected.');
        } catch (ViewException) {
            self::assertSame('done', $renderer->partial('components.recurse', ['remaining' => 0]));
        }
    }

    public function testLayoutRenderingCountsAsOneActiveLevel(): void
    {
        $renderer = new ViewRenderer($this->viewsPath);

        self::assertSame(
            'Hello Guestdone',
            $renderer->render('plain', ['name' => 'Guest', 'remaining' => 6], 'layouts.recursive'),
        );

        $this->expectException(ViewException::class);
        $renderer->render('plain', ['name' => 'Guest', 'remaining' => 7], 'layouts.recursive');
    }

    public function testItCollectsAndCleansRendererOpenedBuffersAfterSuccess(): void
    {
        $renderer = new ViewRenderer($this->viewsPath);
        $level = ob_get_level();

        self::assertSame('beforeinside', $renderer->render('open-buffer'));
        self::assertSame($level, ob_get_level());
    }

    public function testSuccessfulRenderingPreservesCallerBufferContents(): void
    {
        $renderer = new ViewRenderer($this->viewsPath);
        $level = ob_get_level();
        ob_start();
        echo 'caller';

        try {
            self::assertSame('Hello Guest', $renderer->render('plain', ['name' => 'Guest']));
            self::assertSame('caller', ob_get_contents());
            self::assertSame($level + 1, ob_get_level());
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    public function testItCleansOutputAfterTemplateFailureAndPreservesCallerBuffers(): void
    {
        $renderer = new ViewRenderer($this->viewsPath);
        $level = ob_get_level();
        ob_start();
        echo 'caller-before';

        try {
            try {
                $renderer->render('warning');
                self::fail('A template warning should fail rendering.');
            } catch (ViewException $exception) {
                self::assertSame('The view could not be rendered.', $exception->getMessage());
                self::assertSame($level + 1, ob_get_level());
                echo '-caller-after';
            }

            self::assertSame('caller-before-caller-after', ob_get_contents());
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    public function testTemplateExceptionsBecomeGenericAndDoNotExposePaths(): void
    {
        $renderer = new ViewRenderer($this->viewsPath);

        try {
            $renderer->render('exception');
            self::fail('A template exception should fail rendering.');
        } catch (ViewException $exception) {
            self::assertSame('The view could not be rendered.', $exception->getMessage());
            self::assertStringNotContainsString($this->viewsPath, $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testPhpErrorsBecomeGenericAndDoNotExposePaths(): void
    {
        try {
            (new ViewRenderer($this->viewsPath))->render('error');
            self::fail('A template Error should fail rendering.');
        } catch (ViewException $exception) {
            self::assertSame('The view could not be rendered.', $exception->getMessage());
            self::assertStringNotContainsString($this->viewsPath, $exception->getMessage());
        }
    }

    public function testItRestoresTheCallerErrorHandlerAfterSuccessAndFailure(): void
    {
        $handledWarnings = 0;
        $callerHandler = static function () use (&$handledWarnings): bool {
            $handledWarnings++;

            return true;
        };
        set_error_handler($callerHandler);
        $renderer = new ViewRenderer($this->viewsPath);

        try {
            $renderer->render('plain', ['name' => 'Guest']);
            trigger_error('caller warning after success', E_USER_WARNING);
            self::assertSame(1, $handledWarnings);

            try {
                $renderer->render('warning');
                self::fail('A template warning should fail rendering.');
            } catch (ViewException) {
                trigger_error('caller warning after failure', E_USER_WARNING);
                self::assertSame(2, $handledWarnings);
            }
        } finally {
            restore_error_handler();
        }
    }

    private function writeView(string $relativePath, string $contents): void
    {
        $path = $this->viewsPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        self::assertNotFalse(file_put_contents($path, $contents));
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = $path . DIRECTORY_SEPARATOR . $entry;

            if (is_link($entryPath) || is_file($entryPath)) {
                unlink($entryPath);
            } elseif (is_dir($entryPath)) {
                $this->removeDirectory($entryPath);
            }
        }

        rmdir($path);
    }
}
