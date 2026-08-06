<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DatabaseBackupRoot;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DatabaseBackupRootTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-backup-root-' . bin2hex(random_bytes(8));
        mkdir($this->base); mkdir($this->storage()); mkdir($this->public());
    }
    protected function tearDown(): void { $this->remove($this->base); }

    public function testValidStorageCreatesDirectBackupsChildAndResolvesGeneratedFiles(): void
    {
        $root = new DatabaseBackupRoot($this->storage(), $this->public());
        $backupDirectory = $this->storage() . DIRECTORY_SEPARATOR . 'backups';
        self::assertDirectoryExists($backupDirectory);
        if (DIRECTORY_SEPARATOR !== '\\') {
            self::assertSame(0, ((int) fileperms($backupDirectory)) & 0077);
        }
        foreach (['tara-guest-house-20260801T143000Z-a1b2c3d4e5f6.sql', 'tara-guest-house-20260801T143000Z-a1b2c3d4e5f6.manifest.json'] as $file) self::assertSame(realpath($backupDirectory) . DIRECTORY_SEPARATOR . $file, $root->resolve($file));
    }
    public function testGeneratedBackupFilesAreProtectedInsideThePrivateRoot(): void
    {
        $root = new DatabaseBackupRoot($this->storage(), $this->public());
        $path = $root->resolve('tara-guest-house-20260801T143000Z-a1b2c3d4e5f6.sql');
        file_put_contents($path, 'backup');

        $root->protectFile($path);

        self::assertFileIsReadable($path);
        self::assertFileIsWritable($path);
        if (DIRECTORY_SEPARATOR !== '\\') {
            self::assertSame(0, ((int) fileperms($path)) & 0077);
        }
    }

    public function testProtectFileRejectsAFileOutsideThePrivateBackupRoot(): void
    {
        $root = new DatabaseBackupRoot($this->storage(), $this->public());
        $outside = $this->storage() . DIRECTORY_SEPARATOR . 'outside.sql';
        file_put_contents($outside, 'backup');

        try {
            $root->protectFile($outside);
            self::fail('Expected outside backup-file rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Backup file permissions are unsafe.', $exception->getMessage());
            self::assertStringNotContainsString($this->base, $exception->getMessage());
        }
    }

    public function testExistingBackupsDirectoryIsReused(): void { mkdir($this->storage() . DIRECTORY_SEPARATOR . 'backups'); new DatabaseBackupRoot($this->storage(), $this->public()); self::assertDirectoryExists($this->storage() . DIRECTORY_SEPARATOR . 'backups'); }
    public function testBackupsRegularFileAndStorageUnderPublicAreRejected(): void
    {
        file_put_contents($this->storage() . DIRECTORY_SEPARATOR . 'backups', 'x'); $this->assertUnsafe(fn()=>new DatabaseBackupRoot($this->storage(),$this->public()));
        $inside=$this->public().DIRECTORY_SEPARATOR.'storage'; mkdir($inside); $this->assertUnsafe(fn()=>new DatabaseBackupRoot($inside,$this->public()));
    }
    #[DataProvider('unsafeChildren')]
    public function testUnsafeChildrenAreRejected(string $name): void { $root=new DatabaseBackupRoot($this->storage(),$this->public()); $this->assertUnsafe(fn()=>$root->resolve($name)); }
    public static function unsafeChildren(): iterable { foreach(['','.', '..','../backup.sql','..\\backup.sql','nested/backup.sql','nested\\backup.sql','/absolute.sql','C:\\absolute.sql','\\\\server\\share\\backup.sql',"a\0b","a\rb","a\nb"] as $name) yield $name=>[$name]; }
    public function testSymlinkEscapeIsRejectedWhenSupported(): void { $link=$this->storage().DIRECTORY_SEPARATOR.'backups'; if(!@symlink($this->public(),$link)) self::markTestSkipped('Current Windows/filesystem permissions do not permit directory symlink creation.'); $this->assertUnsafe(fn()=>new DatabaseBackupRoot($this->storage(),$this->public())); }
    private function assertUnsafe(callable $callable): void { try{$callable();self::fail('Expected unsafe path rejection.');}catch(InvalidArgumentException $e){self::assertSame('Backup path is unsafe.',$e->getMessage());self::assertStringNotContainsString($this->base,$e->getMessage());} }
    private function storage(): string{return $this->base.DIRECTORY_SEPARATOR.'storage';} private function public(): string{return $this->base.DIRECTORY_SEPARATOR.'public';}
    private function remove(string $path):void { if(is_dir($path)&&!is_link($path)){foreach(scandir($path)?:[] as $n)if($n!=='.'&&$n!=='..')$this->remove($path.DIRECTORY_SEPARATOR.$n);rmdir($path);}elseif(file_exists($path)||is_link($path))unlink($path); }
}
